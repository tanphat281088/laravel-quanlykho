<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKhachHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Lấy id từ route để bỏ qua unique khi cập nhật
        $id = $this->route('id');

        return [
            // Sửa mềm (không bắt nhập lại toàn bộ), email KHÔNG bắt buộc
            'ten_khach_hang'     => ['sometimes','string','max:255'],
            'email'              => ['sometimes','nullable','email','max:255',
                                     Rule::unique('khach_hangs','email')->ignore($id)
                                         ->where(fn($q) => $q->whereNotNull('email'))],
            'so_dien_thoai'      => ['sometimes','string','max:20',
                                     Rule::unique('khach_hangs','so_dien_thoai')->ignore($id)],
            'dia_chi'            => ['sometimes','nullable','string','max:500'],
            'ghi_chu'            => ['sometimes','nullable','string','max:2000'],

            // CRM (đổi pipeline -> tinh_trang_khach)
            'tinh_trang_khach'   => ['sometimes','nullable','string','max:100'],
            'kenh_lien_he'       => ['sometimes','nullable','string','max:150'],
            'staff_id'           => ['sometimes','nullable','integer','exists:users,id'],
            'loai_khach_hang_id' => ['sometimes','nullable','integer','exists:loai_khach_hangs,id'],

            // Trường hệ thống/khác
            'ma_khach_hang'      => ['sometimes','nullable','string','max:50'],
            'trang_thai'         => ['sometimes','nullable','integer','in:0,1'],
            'cong_no'            => ['sometimes','nullable','numeric'],
            'doanh_thu_tich_luy' => ['sometimes','nullable','integer'],
            'diem_tich_luy'      => ['sometimes','nullable','integer'],
            // BỎ: 'hang_thanh_vien'
            'ngay_cap_nhat_hang' => ['sometimes','nullable','date'],
            'ngay_lien_he'       => ['sometimes','nullable','date'],
        ];
    }
}
