<?php

namespace App\Modules\QuanLyBanHang;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use Exception;
use Illuminate\Support\Facades\DB;

class QuanLyBanHangService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = DonHang::query()->with('images');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['don_hangs.*'] // Columns cần select
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: '.$e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = DonHang::with('khachHang', 'chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')->find($id);
        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới dữ liệu
     * - Ưu tiên dùng giá chốt (price_snapshot) nếu FE gửi
     * - Vẫn giữ fallback cũ nếu không có snapshot
     */
    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $tongTienHang = 0;

            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                // NEW: nếu FE đã gửi sẵn snapshot + mode => dùng luôn
                if (isset($item['price_snapshot']) && is_numeric($item['price_snapshot'])) {
                    $snapshot = (int) $item['price_snapshot'];
                    $data['danh_sach_san_pham'][$index]['don_gia']    = $snapshot;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $snapshot;

                    // Cho phép lưu thêm metadata mới nếu FE gửi
                    $data['danh_sach_san_pham'][$index]['price_mode']       = $item['price_mode']       ?? 'INSTANT';
                    $data['danh_sach_san_pham'][$index]['is_custom']        = $item['is_custom']        ?? false;
                    $data['danh_sach_san_pham'][$index]['custom_sku']       = $item['custom_sku']       ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_name']      = $item['custom_name']      ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_desc']      = $item['custom_desc']      ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_image_url'] = $item['custom_image_url'] ?? null;

                } else {
                    // Fallback CŨ: lấy giá từ lô nhập hoặc công thức gia_nhap_mac_dinh + lợi nhuận
                    $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'] ?? 0)
                        ->where('don_vi_tinh_id', $item['don_vi_tinh_id'] ?? 0)
                        ->orderBy('id', 'asc')->first();

                    if ($loSanPham) {
                        $data['danh_sach_san_pham'][$index]['don_gia']    = $loSanPham->gia_ban_le_don_vi;
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $loSanPham->gia_ban_le_don_vi;
                    } else {
                        $sanPham = SanPham::find($item['san_pham_id'] ?? 0);
                        if ($sanPham) {
                            $donGia = $sanPham->gia_nhap_mac_dinh + ($sanPham->gia_nhap_mac_dinh * $sanPham->muc_loi_nhuan / 100);
                            $data['danh_sach_san_pham'][$index]['don_gia']    = $donGia;
                            $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $donGia;
                        } else {
                            throw new Exception('Sản phẩm '.($item['san_pham_id'] ?? 'NULL').' không tồn tại');
                        }
                    }
                }

                $tongTienHang += $data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $tongTienCanThanhToan = $tongTienHang - ($data['giam_gia'] ?? 0) + ($data['chi_phi'] ?? 0);

            if (($data['so_tien_da_thanh_toan'] ?? 0) > $tongTienCanThanhToan) {
                throw new Exception('Số tiền đã thanh toán không được lớn hơn tổng tiền cần thanh toán');
            }

            $data['tong_tien_hang']            = $tongTienHang;
            $data['tong_tien_can_thanh_toan']  = $tongTienCanThanhToan;
            $data['tong_so_luong_san_pham']    = count($data['danh_sach_san_pham']);
            $data['trang_thai_thanh_toan']     = (($data['so_tien_da_thanh_toan'] ?? 0) == $tongTienCanThanhToan) ? 1 : 0;

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai']  = $khachHang->so_dien_thoai;
                }
            }

            $dataDonHang = $data;
            unset($dataDonHang['danh_sach_san_pham']);
            $donHang = DonHang::create($dataDonHang);

            foreach ($data['danh_sach_san_pham'] as $item) {
                $item['don_hang_id'] = $donHang->id;

                // NEW: nếu có snapshot/mode/custom thì lưu xuống bảng
                if (isset($item['price_snapshot']))        $item['price_snapshot'] = (int)$item['price_snapshot'];
                if (isset($item['price_mode']))            $item['price_mode'] = (string)$item['price_mode'];
                if (isset($item['is_custom']))             $item['is_custom'] = (bool)$item['is_custom'];
                if (!empty($item['is_custom']))            $item['product_id'] = $item['product_id'] ?? null;

                ChiTietDonHang::create($item);
            }

            DB::commit();

            return $donHang;
        } catch (Exception $e) {
            DB::rollBack();
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     * - Tương tự create(): ưu tiên price_snapshot nếu có
     */
    public function update($id, array $data)
    {
        DB::beginTransaction();
        $donHang = $this->getById($id);

        if ($donHang->phieuThu()->exists() || $donHang->chiTietPhieuThu()->exists()) {
            throw new Exception('Đơn hàng đã có phiếu thu, không thể cập nhật');
        }

        try {
            $tongTienHang = 0;

            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                if (isset($item['price_snapshot']) && is_numeric($item['price_snapshot'])) {
                    $snapshot = (int) $item['price_snapshot'];
                    $data['danh_sach_san_pham'][$index]['don_gia']    = $snapshot;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $snapshot;

                    $data['danh_sach_san_pham'][$index]['price_mode']       = $item['price_mode']       ?? 'INSTANT';
                    $data['danh_sach_san_pham'][$index]['is_custom']        = $item['is_custom']        ?? false;
                    $data['danh_sach_san_pham'][$index]['custom_sku']       = $item['custom_sku']       ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_name']      = $item['custom_name']      ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_desc']      = $item['custom_desc']      ?? null;
                    $data['danh_sach_san_pham'][$index]['custom_image_url'] = $item['custom_image_url'] ?? null;

                } else {
                    $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'] ?? 0)
                        ->where('don_vi_tinh_id', $item['don_vi_tinh_id'] ?? 0)
                        ->orderBy('id', 'asc')->first();

                    if ($loSanPham) {
                        $data['danh_sach_san_pham'][$index]['don_gia']    = $loSanPham->gia_ban_le_don_vi;
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $loSanPham->gia_ban_le_don_vi;
                    } else {
                        $sanPham = SanPham::find($item['san_pham_id'] ?? 0);
                        if ($sanPham) {
                            $donGia = $sanPham->gia_nhap_mac_dinh + ($sanPham->gia_nhap_mac_dinh * $sanPham->muc_loi_nhuan / 100);
                            $data['danh_sach_san_pham'][$index]['don_gia']    = $donGia;
                            $data['danh_sach_san_pham'][$index]['thanh_tien'] = ((int)$item['so_luong']) * $donGia;
                        } else {
                            throw new Exception('Sản phẩm '.($item['san_pham_id'] ?? 'NULL').' không tồn tại');
                        }
                    }
                }

                $tongTienHang += $data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $tongTienCanThanhToan = $tongTienHang - ($data['giam_gia'] ?? 0) + ($data['chi_phi'] ?? 0);

            if (($data['so_tien_da_thanh_toan'] ?? 0) > $tongTienCanThanhToan) {
                throw new Exception('Số tiền đã thanh toán không được lớn hơn tổng tiền cần thanh toán');
            }

            $data['tong_tien_hang']           = $tongTienHang;
            $data['tong_tien_can_thanh_toan'] = $tongTienCanThanhToan;
            $data['tong_so_luong_san_pham']   = count($data['danh_sach_san_pham']);
            $data['trang_thai_thanh_toan']    = (($data['so_tien_da_thanh_toan'] ?? 0) == $tongTienCanThanhToan) ? 1 : 0;

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai']  = $khach_hang->so_dien_thoai;
                }
            }

            $dataDonHang = $data;
            unset($dataDonHang['danh_sach_san_pham']);
            $donHang->update($dataDonHang);

            // Xóa hết rồi tạo lại (theo flow sẵn có)
            $donHang->chiTietDonHangs()->delete();

            foreach ($data['danh_sach_san_pham'] as $item) {
                $item['don_hang_id'] = $donHang->id;

                if (isset($item['price_snapshot']))        $item['price_snapshot'] = (int)$item['price_snapshot'];
                if (isset($item['price_mode']))            $item['price_mode'] = (string)$item['price_mode'];
                if (isset($item['is_custom']))             $item['is_custom'] = (bool)$item['is_custom'];
                if (!empty($item['is_custom']))            $item['product_id'] = $item['product_id'] ?? null;

                ChiTietDonHang::create($item);
            }

            DB::commit();

            return $donHang->refresh();
        } catch (Exception $e) {
            DB::rollBack();
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $donHang = $this->getById($id);

            if ($donHang->phieuThu()->exists() || $donHang->chiTietPhieuThu()->exists()) {
                throw new Exception('Đơn hàng đã có phiếu thu, không thể xóa');
            }

            $donHang->chiTietDonHangs()->delete();

            return $donHang->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách QuanLyBanHang dạng option
     */
    public function getOptions(array $params = [])
    {
        $query = DonHang::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['don_hangs.id as value', 'don_hangs.ma_don_hang as label']
        );

        return $result['collection'];
    }

    /**
     * Lấy giá bán sản phẩm (CŨ - vẫn giữ)
     */
    public function getGiaBanSanPham($sanPhamId, $donViTinhId)
    {
        $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $sanPhamId)->where('don_vi_tinh_id', $donViTinhId)->orderBy('id', 'asc')->first();

        if ($loSanPham) {
            return $loSanPham->gia_ban_le_don_vi;
        }

        $sanPham = SanPham::find($sanPhamId);

        if ($sanPham) {
            return $sanPham->gia_nhap_mac_dinh + ($sanPham->gia_nhap_mac_dinh * $sanPham->muc_loi_nhuan / 100);
        }

        return null;
    }

    /**
     * Xem trước hóa đơn (HTML)
     */
    public function xemTruocHoaDon($id)
    {
        try {
            $donHang = $this->getById($id);

            if (! $donHang) {
                return CustomResponse::error('Đơn hàng không tồn tại');
            }

            return view('hoa-don.template', compact('donHang'));
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xem trước hóa đơn: '.$e->getMessage());
        }
    }

    public function getSanPhamByDonHangId($donHangId)
    {
        return DonHang::with('chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')->where('id', $donHangId)->first();
    }

    public function getDonHangByKhachHangId($khachHangId)
    {
        return DonHang::with('khachHang')->where('khach_hang_id', $khachHangId)->where('trang_thai_thanh_toan', 0)->get();
    }

    public function getSoTienCanThanhToan($donHangId)
    {
        $donHang = $this->getById($donHangId);

        return $donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan;
    }

    /* =========================================================
     |  NEW: Thêm dòng vào đơn (catalog/custom) + snapshot
     |=========================================================*/

    /**
     * Tạo dòng CATALOG cho đơn hàng đã tồn tại:
     * - price_mode: PREORDER | INSTANT | CUSTOM
     * - Luôn ghi price_snapshot
     */
    public function addCatalogLine(int $orderId, array $payload)
    {
        $donHang = DonHang::findOrFail($orderId);

        $productId = (int) ($payload['product_id'] ?? 0);
        $qty       = max(1, (int) ($payload['qty'] ?? 1));
        $priceMode = strtoupper((string) ($payload['price_mode'] ?? 'INSTANT'));

        /** @var SanPham $sp */
        $sp = SanPham::findOrFail($productId);

        // Chọn snapshot theo mode
        if ($priceMode === 'PREORDER') {
            $snapshot = (int) ($sp->gia_chinh ?? 0);
        } elseif ($priceMode === 'INSTANT') {
            $snapshot = (int) ($sp->gia_dat_ngay ?? 0);
        } else { // CUSTOM
            $snapshot = (int) ($payload['price_snapshot'] ?? 0);
        }

        if ($snapshot <= 0) {
            throw new Exception('Giá chốt (snapshot) không hợp lệ');
        }

        $line = ChiTietDonHang::create([
            'don_hang_id'  => $donHang->id,
            'product_id'   => $sp->id,
            'so_luong'     => $qty,
            // legacy fields:
            'don_gia'      => $snapshot,
            'thanh_tien'   => $snapshot * $qty,
            // new fields:
            'price_snapshot'   => $snapshot,
            'price_mode'       => $priceMode,
            'is_custom'        => false,
            'custom_sku'       => null,
            'custom_name'      => null,
            'custom_desc'      => null,
            'custom_image_url' => null,
        ]);

        // Cập nhật tổng tiền đơn
        $this->recalcOrderTotals($donHang->id);

        return $line->fresh('sanPham');
    }

    /**
     * Tạo dòng CUSTOM cho đơn hàng đã tồn tại:
     * - product_id = null
     * - price_mode = CUSTOM
     * - bắt buộc price_snapshot > 0, custom_name
     */
    public function addCustomLine(int $orderId, array $payload)
    {
        $donHang = DonHang::findOrFail($orderId);

        $qty       = max(1, (int) ($payload['qty'] ?? 1));
        $snapshot  = (int) ($payload['price_snapshot'] ?? 0);
        $name      = trim((string) ($payload['custom_name'] ?? ''));
        $imageUrl  = $payload['custom_image_url'] ?? null;

        if ($snapshot <= 0 || $name === '') {
            throw new Exception('Dòng CUSTOM cần custom_name và price_snapshot > 0');
        }

        $customSku = $this->generateCustomSku();

        $line = ChiTietDonHang::create([
            'don_hang_id'      => $donHang->id,
            'product_id'       => null,
            'so_luong'         => $qty,
            // legacy fields:
            'don_gia'          => $snapshot,
            'thanh_tien'       => $snapshot * $qty,
            // new fields:
            'price_snapshot'   => $snapshot,
            'price_mode'       => 'CUSTOM',
            'is_custom'        => true,
            'custom_sku'       => $customSku,
            'custom_name'      => $name,
            'custom_desc'      => $payload['custom_desc'] ?? null,
            'custom_image_url' => $imageUrl,
        ]);

        $this->recalcOrderTotals($donHang->id);

        return $line;
    }

    /**
     * (Tuỳ chọn) Áp dụng plan giá cho toàn đơn (chỉ dòng catalog)
     * plan: PREORDER | INSTANT
     */
    public function applyPricePlan(int $orderId, string $plan)
    {
        $donHang = DonHang::with('chiTietDonHangs.sanPham')->findOrFail($orderId);
        $plan = strtoupper($plan);
        if (!in_array($plan, ['PREORDER','INSTANT'], true)) {
            throw new Exception('Plan không hợp lệ');
        }

        foreach ($donHang->chiTietDonHangs as $line) {
            if ($line->is_custom) continue; // bỏ qua CUSTOM

            $sp = $line->sanPham;
            if (!$sp) continue;

            $snapshot = ($plan === 'PREORDER')
                ? (int) ($sp->gia_chinh ?? 0)
                : (int) ($sp->gia_dat_ngay ?? 0);

            if ($snapshot <= 0) continue;

            $line->update([
                'price_mode'     => $plan,
                'price_snapshot' => $snapshot,
                'don_gia'        => $snapshot,
                'thanh_tien'     => $snapshot * (int)$line->so_luong,
            ]);
        }

        $this->recalcOrderTotals($donHang->id);

        return $this->getById($donHang->id);
    }

    /**
     * Recalc totals for order (giữ nguyên công thức của bạn)
     */
    private function recalcOrderTotals(int $orderId): void
    {
        $donHang = DonHang::with('chiTietDonHangs')->findOrFail($orderId);

        $tongTienHang = 0;
        foreach ($donHang->chiTietDonHangs as $line) {
            // Ưu tiên thanh_tien có sẵn; nếu không thì snapshot * qty
            $lineTotal = $line->thanh_tien ?? ((int)$line->price_snapshot * (int)$line->so_luong);
            $tongTienHang += (int) $lineTotal;
        }

        $giamGia   = (int) ($donHang->giam_gia ?? 0);
        $chiPhi    = (int) ($donHang->chi_phi ?? 0);

        $donHang->update([
            'tong_tien_hang'           => $tongTienHang,
            'tong_tien_can_thanh_toan' => $tongTienHang - $giamGia + $chiPhi,
            'tong_so_luong_san_pham'   => $donHang->chiTietDonHangs()->count(),
            'trang_thai_thanh_toan'    => ((int)($donHang->so_tien_da_thanh_toan ?? 0) == (int)($tongTienHang - $giamGia + $chiPhi)) ? 1 : 0,
        ]);
    }

    /**
     * Sinh mã CUSTOM SKU: CSTM-YYYYMMDD-#### (reset theo ngày)
     */
    private function generateCustomSku(): string
    {
        $date = date('Ymd');
        $countToday = ChiTietDonHang::whereDate('created_at', date('Y-m-d'))->count() + 1;
        return sprintf('CSTM-%s-%04d', $date, $countToday);
    }
}
