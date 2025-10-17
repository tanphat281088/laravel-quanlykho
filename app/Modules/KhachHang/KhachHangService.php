<?php

namespace App\Modules\KhachHang;

use App\Models\KhachHang;
use App\Support\Phone;
use App\Support\CodeGen;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class KhachHangService
{
    /**
     * Lấy tất cả dữ liệu (giữ nguyên filter/paginate hiện có)
     */
    public function getAll(array $params = [])
    {
        try {
            $query = KhachHang::query()
                ->with('images', 'loaiKhachHang:id,ten_loai_khach_hang');

            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['khach_hangs.*'] // lấy đầy đủ cột để FE hiển thị
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page'  => $result['current_page'],
                    'last_page'     => $result['last_page'],
                    'from'          => $result['from'],
                    'to'            => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = KhachHang::with('images', 'loaiKhachHang:id,ten_loai_khach_hang')->find($id);
        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return $data;
    }

    /**
     * Tạo mới dữ liệu
     */
    public function create(array $data)
    {
        try {
            // Chuyển đổi tương thích: pipeline -> tinh_trang_khach (nếu FE cũ vẫn gửi pipeline)
            if (!empty($data['pipeline']) && empty($data['tinh_trang_khach'])) {
                $data['tinh_trang_khach'] = $data['pipeline'];
                unset($data['pipeline']);
            }

            // Chuẩn hoá SĐT
            if (!empty($data['so_dien_thoai'])) {
                $data['so_dien_thoai'] = Phone::normalize($data['so_dien_thoai']);
            }

            // staff_name từ users
            if (!empty($data['staff_id'])) {
                $u = DB::table('users')->select('id','name')->where('id', $data['staff_id'])->first();
                $data['staff_name'] = $u ? $u->name : null;
            }

            // Mã KH tự sinh nếu trống
            if (empty($data['ma_khach_hang'])) {
                $data['ma_khach_hang'] = CodeGen::next('KH');
            }

            // Mặc định trạng thái hoạt động nếu chưa set
            if (!isset($data['trang_thai'])) {
                $data['trang_thai'] = 1;
            }

            $kh = KhachHang::create($data);

            // TÍNH lại loại KH (loai_khach_hang_id) & ngày cập nhật hạng dựa trên doanh thu
            // (không còn dùng hang_thanh_vien)
            app(\App\Services\CustomerTotalsService::class)->refresh($kh->id);

            return $this->getById($kh->id);
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update($id, array $data)
    {
        try {
            $kh = KhachHang::findOrFail($id);

            // Chuyển đổi tương thích: pipeline -> tinh_trang_khach
            if (array_key_exists('pipeline', $data) && empty($data['tinh_trang_khach'])) {
                $data['tinh_trang_khach'] = $data['pipeline'];
                unset($data['pipeline']);
            }

            // Chuẩn hoá SĐT nếu có
            if (array_key_exists('so_dien_thoai', $data)) {
                $data['so_dien_thoai'] = Phone::normalize($data['so_dien_thoai']);
            }

            // staff_name từ users
            if (!empty($data['staff_id'])) {
                $u = DB::table('users')->select('id','name')->where('id', $data['staff_id'])->first();
                $data['staff_name'] = $u ? $u->name : null;
            }

            // Bổ sung mã KH nếu bản ghi cũ chưa có
            if (empty($kh->ma_khach_hang)) {
                $kh->ma_khach_hang = CodeGen::next('KH');
            }

            $kh->fill($data);
            $kh->save();

            // TÍNH lại loại KH & ngày cập nhật hạng sau cập nhật
            app(\App\Services\CustomerTotalsService::class)->refresh($kh->id);

            return $this->getById($kh->id);
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $model = KhachHang::findOrFail($id);
            return $model->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Tìm theo SĐT (phục vụ lookupByPhone)
     */
    public function findByPhone(string $phone)
    {
        return KhachHang::where('so_dien_thoai', $phone)->first();
    }

    /**
     * Options cho dropdown CRM
     * - Trả về cả 'statuses' (tên mới) và 'pipelines' (tương thích ngược)
     * - channels, staff, types giữ nguyên
     */
    public function getOptions()
    {
        $pipelines = DB::table('pipelines')->where('active',1)->orderBy('sort_order')->pluck('name')->values();
        $channels  = DB::table('channels')->where('active',1)->orderBy('id')->pluck('name')->values();
        $staff     = DB::table('users')->select('id','name')->orderBy('name')->get();
        $types     = DB::table('loai_khach_hangs')->select('id','ten_loai_khach_hang')->orderBy('nguong_doanh_thu')->get();

        // tên mới
        $statuses = $pipelines;

        return compact('statuses','pipelines','channels','staff','types');
    }
}
