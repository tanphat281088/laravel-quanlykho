<?php

namespace App\Modules\SanPham;

use App\Models\SanPham;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\KhoTong;

class SanPhamService
{
    /**
     * Lấy tất cả dữ liệu (GIỮ NGUYÊN)
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản với JOIN và GROUP BY
            $query = SanPham::query()
                ->withoutGlobalScopes(['withUserNames'])
                ->with('images', 'danhMuc:id,ten_danh_muc')
                ->leftJoin('users as nguoi_tao', 'san_phams.nguoi_tao', '=', 'nguoi_tao.id')
                ->leftJoin('users as nguoi_cap_nhat', 'san_phams.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id')
                ->leftJoin('chi_tiet_phieu_nhap_khos', 'san_phams.id', '=', 'chi_tiet_phieu_nhap_khos.san_pham_id')
                ->leftJoin('kho_tongs', 'san_phams.id', '=', 'kho_tongs.san_pham_id')
                ->groupBy('san_phams.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                [
                    'san_phams.*',
                    'nguoi_tao.name as ten_nguoi_tao',
                    'nguoi_cap_nhat.name as ten_nguoi_cap_nhat',
                    DB::raw('COALESCE(SUM(chi_tiet_phieu_nhap_khos.so_luong_nhap), 0) as tong_so_luong_nhap'),
                    DB::raw('COALESCE(SUM(kho_tongs.so_luong_ton), 0) as tong_so_luong_thuc_te')
                ]
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                    'total_current' => $result['total_current']
                ]
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID (GIỮ NGUYÊN)
     */
    public function getById($id)
    {
        $data = SanPham::with([
            'images',
            'donViTinhs' => function ($query) {
                $query->withoutGlobalScope('withUserNames')->select('don_vi_tinhs.id as value', 'ten_don_vi as label');
            },
            'nhaCungCaps' => function ($query) {
                $query->withoutGlobalScope('withUserNames')->select('nha_cung_caps.id as value', 'ten_nha_cung_cap as label');
            },
            'danhMuc'
        ])->find($id);
        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return $data;
    }

    /**
     * Tạo mới dữ liệu (GIỮ NGUYÊN, chỉ chạm nhẹ phần ảnh)
     */
    public function create(array $data)
    {
        try {
            $result = SanPham::create([
                'ma_san_pham'       => $data['ma_san_pham'],
                'ten_san_pham'      => $data['ten_san_pham'],
                'danh_muc_id'       => $data['danh_muc_id'],
                'gia_nhap_mac_dinh' => $data['gia_nhap_mac_dinh'],
                'ty_le_chiet_khau'  => $data['ty_le_chiet_khau'],
                'muc_loi_nhuan'     => $data['muc_loi_nhuan'],
                'so_luong_canh_bao' => $data['so_luong_canh_bao'],
                'loai_san_pham'     => $data['loai_san_pham'],
                'ghi_chu'           => $data['ghi_chu'] ?? null,
                'trang_thai'        => $data['trang_thai'],
                // Nếu FE gửi kèm hai giá mới:
                'gia_chinh'         => $data['gia_chinh'] ?? 0,
                'gia_dat_ngay'      => $data['gia_dat_ngay'] ?? 0,
            ]);

            if (isset($data['don_vi_tinh_id'])) {
                $result->donViTinhs()->attach($data['don_vi_tinh_id'], [
                    'nguoi_tao' => Auth::user()->id,
                    'nguoi_cap_nhat' => Auth::user()->id
                ]);
            }

            if (isset($data['nha_cung_cap_id'])) {
                $result->nhaCungCaps()->attach($data['nha_cung_cap_id'], [
                    'nguoi_tao' => Auth::user()->id,
                    'nguoi_cap_nhat' => Auth::user()->id
                ]);
            }

            // Ảnh: ưu tiên url (Google) nếu FE cung cấp; fallback path cũ
            if (!empty($data['image_url'])) {
                $result->images()->create([
                    'url'        => $data['image_url'],
                    'is_primary' => 1,
                ]);
            } elseif (!empty($data['image'])) {
                $result->images()->create([
                    'path'       => $data['image'],
                    'is_primary' => 1,
                ]);
            }

            return $result;
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu (GIỮ NGUYÊN, chỉ chạm nhẹ phần ảnh)
     */
    public function update($id, array $data)
    {
        try {
            $model = SanPham::findOrFail($id);

            $sanPhamData = $data;
            unset($sanPhamData['don_vi_tinh_id'], $sanPhamData['nha_cung_cap_id'], $sanPhamData['image'], $sanPhamData['image_url']);

            $model->update($sanPhamData);

            // Ảnh: nếu có image_url → cập nhật/ tạo ảnh chính theo URL; nếu không, dùng path như cũ
            if (!empty($data['image_url'])) {
                // cố gắng lấy ảnh chính hiện có
                $primary = $model->images()->orderByDesc('is_primary')->first();
                if ($primary) {
                    $primary->update(['url' => $data['image_url'], 'is_primary' => 1]);
                } else {
                    $model->images()->create(['url' => $data['image_url'], 'is_primary' => 1]);
                }
            } elseif (!empty($data['image'])) {
                $model->images()->get()->each(function ($image) use ($data) {
                    $image->update([
                        'path' => $data['image'],
                    ]);
                });
            }

            if (isset($data['don_vi_tinh_id'])) {
                $model->donViTinhs()->sync($data['don_vi_tinh_id'], [
                    'nguoi_tao' => Auth::user()->id,
                    'nguoi_cap_nhat' => Auth::user()->id
                ]);
            }

            if (isset($data['nha_cung_cap_id'])) {
                $model->nhaCungCaps()->sync($data['nha_cung_cap_id'], [
                    'nguoi_tao' => Auth::user()->id,
                    'nguoi_cap_nhat' => Auth::user()->id
                ]);
            }

            return $model->fresh();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu (GIỮ NGUYÊN)
     */
    public function delete($id)
    {
        try {
            $model = SanPham::findOrFail($id);

            // Xóa ảnh
            $model->images()->get()->each(function ($image) {
                $image->delete();
            });

            $model->donViTinhs()->detach();
            $model->nhaCungCaps()->detach();

            return $model->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách SanPham dạng option (GIỮ NGUYÊN)
     */
    public function getOptions(array $params = [])
    {
        $query = SanPham::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['san_phams.id as value', DB::raw('CONCAT(san_phams.ten_san_pham, " (", san_phams.loai_san_pham, ")") as label')]
        );

        return $result['collection'];
    }

    /**
     * Lấy danh sách SanPham dạng option theo NhaCungCap (GIỮ NGUYÊN)
     */
    public function getOptionsByNhaCungCap($nhaCungCapId)
    {
        return SanPham::whereHas('nhaCungCaps', function ($query) use ($nhaCungCapId) {
            $query->withoutGlobalScope('withUserNames')
                ->where('nha_cung_caps.id', $nhaCungCapId)
                ->where('san_phams.loai_san_pham', '!=', 'SP_SAN_XUAT');
        })
            ->withoutGlobalScope('withUserNames')
            ->select('san_phams.id as value', DB::raw('CONCAT(san_phams.ten_san_pham, " (", san_phams.loai_san_pham, ")") as label'))
            ->get();
    }

    /**
     * Lấy danh sách LoSanPham dạng option theo SanPham (GIỮ NGUYÊN)
     */
    public function getOptionsLoSanPhamBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId)
    {
        $loSanPham = KhoTong::where('kho_tongs.san_pham_id', $sanPhamId)
            ->where('kho_tongs.don_vi_tinh_id', $donViTinhId)
            ->leftJoin('chi_tiet_phieu_nhap_khos', 'kho_tongs.ma_lo_san_pham', '=', 'chi_tiet_phieu_nhap_khos.ma_lo_san_pham')
            ->withoutGlobalScope('withUserNames')
            ->select(
                'kho_tongs.ma_lo_san_pham as value',
                DB::raw('CONCAT(kho_tongs.ma_lo_san_pham, " - NSX: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_san_xuat, "%d/%m/%Y"), " - HSD: ", DATE_FORMAT(chi_tiet_phieu_nhap_khos.ngay_het_han, "%d/%m/%Y"), " - SL Tồn: ", kho_tongs.so_luong_ton, " - HSD Còn lại: ", DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()), " ngày") as label'),
                DB::raw('DATEDIFF(chi_tiet_phieu_nhap_khos.ngay_het_han, CURDATE()) as hsd_con_lai')
            )
            ->orderBy('hsd_con_lai', 'asc')
            ->get();

        return $loSanPham;
    }

    /* =========================================================
     |  PHẦN MỚI: API phục vụ popup sản phẩm (2 giá + ảnh URL)
     |=========================================================*/

    /**
     * Trả danh sách sản phẩm (2 giá + ảnh chính + % chênh) cho popup FE.
     * Hỗ trợ: keyword, danh_muc_id, page, per_page
     */
    public function searchWithPricesAndPrimaryImage(array $filters = [])
    {
        $q = SanPham::query()
            ->with(['images' => function ($qq) {
                $qq->orderByDesc('is_primary')->orderBy('id');
            }]);

        if (!empty($filters['keyword'])) {
            $kw = trim($filters['keyword']);
            $q->where(function ($qq) use ($kw) {
                $qq->where('ten_san_pham', 'like', "%{$kw}%")
                   ->orWhere('ma_san_pham', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['danh_muc_id'])) {
            $q->where('danh_muc_id', (int) $filters['danh_muc_id']);
        }

        $perPage = isset($filters['per_page']) ? max(1, (int)$filters['per_page']) : 20;
        $page    = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;

        $paginator = $q->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(function (SanPham $sp) {
            return [
                'id'                => $sp->id,
                'ma_san_pham'       => $sp->ma_san_pham,
                'ten_san_pham'      => $sp->ten_san_pham,
                'gia_chinh'         => (int) ($sp->gia_chinh ?? 0),     // Giá đặt trước 3 ngày
                'gia_dat_ngay'      => (int) ($sp->gia_dat_ngay ?? 0),  // Giá đặt ngay
                'discount_percent'  => $sp->discount_percent ?? null,
                'image_primary_url' => $this->getPrimaryImageUrl($sp),
            ];
        });

        return [
            'data' => $items,
            'meta' => [
                'total'     => $paginator->total(),
                'per_page'  => $paginator->perPage(),
                'current'   => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Helper: chọn URL ảnh chính theo thứ tự ưu tiên
     */
    private function getPrimaryImageUrl(SanPham $sp): ?string
    {
        if (!$sp->relationLoaded('images')) {
            $sp->load('images');
        }

        // 1) is_primary + url
        $img = $sp->images->firstWhere('is_primary', 1);
        if ($img && !empty($img->url)) return $img->url;

        // 2) bất kỳ url
        $img = $sp->images->first(fn ($i) => !empty($i->url));
        if ($img) return $img->url;

        // 3) path local (nếu có dùng storage public)
        $img = $sp->images->first(fn ($i) => !empty($i->path));
        if ($img) {
            return asset('storage/' . ltrim($img->path, '/'));
        }

        return null;
    }
}
