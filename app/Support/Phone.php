<?php

namespace App\Support;

class Phone
{
    /**
     * Chuẩn hoá số điện thoại về dạng nội địa VN: 0xxxxxxxxx
     * - Loại bỏ ký tự không phải số
     * - +84 / 84 / 084 -> 0
     * - Giữ lại 9–11 chữ số (tuỳ nhà mạng)
     */
    public static function normalize(?string $raw): ?string
    {
        if (!$raw) return null;

        // Bỏ mọi ký tự không phải số
        $s = preg_replace('/\D+/', '', $raw);

        // Quy về đầu 0 nếu là mã quốc gia VN
        if (str_starts_with($s, '84') && strlen($s) >= 10) {
            $s = '0' . substr($s, 2);
        } elseif (str_starts_with($s, '084')) {
            $s = '0' . substr($s, 3);
        }

        // Trả về như đã chuẩn hoá (không ép độ dài cứng)
        return $s ?: null;
    }
}
