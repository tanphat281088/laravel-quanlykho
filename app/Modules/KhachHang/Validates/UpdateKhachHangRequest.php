<?php

namespace App\Modules\KhachHang\Validates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKhachHangRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      // Thêm các quy tắc validation cho cập nhật KhachHang ở đây
      'ten_khach_hang' => 'sometimes|required|string|max:255',
      'email' => 'sometimes|required|email|max:255|unique:khach_hangs,email,' . $this->id,
      'so_dien_thoai' => 'sometimes|required|string|max:255|unique:khach_hangs,so_dien_thoai,' . $this->id,
      'dia_chi' => 'sometimes|required|string|max:255',
      'ghi_chu' => 'nullable|string|max:255',
    ];
  }

  /**
   * Get the error messages for the defined validation rules.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'ten_khach_hang.required' => 'Tên khách hàng là bắt buộc',
      'ten_khach_hang.max' => 'Tên khách hàng không được vượt quá 255 ký tự',
      'email.required' => 'Email là bắt buộc',
      'email.email' => 'Email không hợp lệ',
      'email.max' => 'Email không được vượt quá 255 ký tự',
      'email.unique' => 'Email đã tồn tại',
      'so_dien_thoai.required' => 'Số điện thoại là bắt buộc',
      'so_dien_thoai.max' => 'Số điện thoại không được vượt quá 255 ký tự',
      'so_dien_thoai.unique' => 'Số điện thoại đã tồn tại',
      'dia_chi.required' => 'Địa chỉ là bắt buộc',
    ];
  }
}