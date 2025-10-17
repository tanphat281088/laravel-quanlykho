<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id'); // khi update sẽ có id

        return [
            'ten_khach_hang'    => ['required','string','max:255'],
            'so_dien_thoai'     => [
                'required','string','max:20',
                // unique theo bảng khach_hangs, bỏ qua chính nó khi update
                Rule::unique('khach_hangs','so_dien_thoai')->ignore($id),
            ],
            'email'             => ['nullable','email','max:255'],
            'dia_chi'           => ['nullable','string','max:500'],
            'kenh_lien_he'      => ['nullable','string','max:100'],
            'pipeline'          => ['nullable','string','max:100'],
            'staff_id'          => ['nullable','integer','exists:users,id'],
            'loai_khach_hang_id'=> ['nullable','integer','exists:loai_khach_hangs,id'],
            // các field chỉ server set: doanh_thu_tich_luy/diem_tich_luy/hang/...
        ];
    }
}
