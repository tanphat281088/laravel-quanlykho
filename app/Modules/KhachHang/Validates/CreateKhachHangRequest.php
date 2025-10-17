<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKhachHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cơ bản
            'ten_khach_hang'     => ['required','string','max:255'],
            'email'              => ['nullable','email','max:255', Rule::unique('khach_hangs','email')->where(function($q){ return $q->whereNotNull('email'); })],
            'so_dien_thoai'      => ['required','string','max:20', Rule::unique('khach_hangs','so_dien_thoai')],
            'dia_chi'            => ['nullable','string','max:500'],
            'ghi_chu'            => ['nullable','string','max:2000'],

            // CRM (đổi pipeline -> tinh_trang_khach)
            'tinh_trang_khach'   => ['nullable','string','max:100'],
            'kenh_lien_he'       => ['nullable','string','max:150'],
            'staff_id'           => ['nullable','integer','exists:users,id'],
            'loai_khach_hang_id' => ['nullable','integer','exists:loai_khach_hangs,id'],

            // Hệ thống/khác (không bị drop nếu có)
            'ma_khach_hang'      => ['nullable','string','max:50'],
            'trang_thai'         => ['nullable','integer','in:0,1'],
            'cong_no'            => ['nullable','numeric'],
            'doanh_thu_tich_luy' => ['nullable','integer'],
            'diem_tich_luy'      => ['nullable','integer'],
            // BỎ 'hang_thanh_vien'
            'ngay_cap_nhat_hang' => ['nullable','date'],
            'ngay_lien_he'       => ['nullable','date'],
        ];
    }

    public function messages(): array
    {
        return [
            'ten_khach_hang.required' => 'Tên khách hàng là bắt buộc',
            'ten_khach_hang.max'      => 'Tên khách hàng không vượt quá 255 ký tự',

            'email.email'             => 'Email không hợp lệ',
            'email.max'               => 'Email không vượt quá 255 ký tự',
            'email.unique'            => 'Email đã tồn tại',

            'so_dien_thoai.required'  => 'Số điện thoại là bắt buộc',
            'so_dien_thoai.max'       => 'Số điện thoại không vượt quá 20 ký tự',
            'so_dien_thoai.unique'    => 'Số điện thoại đã tồn tại',

            'dia_chi.max'             => 'Địa chỉ không vượt quá 500 ký tự',
            'ghi_chu.max'             => 'Ghi chú tối đa 2000 ký tự',
        ];
    }
}
