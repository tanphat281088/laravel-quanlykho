<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CodeGen
{
    public static function next(string $prefix = 'KH'): string
    {
        $row = DB::table('khach_hangs')
            ->select('ma_khach_hang')
            ->whereNotNull('ma_khach_hang')
            ->orderByDesc('id')
            ->first();

        $num = 0;
        if ($row && preg_match('/^'.preg_quote($prefix, '/').'(\d{1,})$/', $row->ma_khach_hang, $m)) {
            $num = (int) $m[1];
        }
        return sprintf('%s%05d', $prefix, $num + 1);
    }
}
