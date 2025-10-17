<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmOptionSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Pipelines mặc định =====
        $pipelines = [
            ['name' => 'Mới',       'sort_order' => 10, 'active' => 1],
            ['name' => 'Quan tâm',  'sort_order' => 20, 'active' => 1],
            ['name' => 'Chốt',      'sort_order' => 30, 'active' => 1],
            ['name' => 'Bỏ qua',    'sort_order' => 40, 'active' => 1],
        ];

        foreach ($pipelines as $p) {
            DB::table('pipelines')->updateOrInsert(
                ['name' => $p['name']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // ===== Kênh liên hệ (theo sheet của bạn) =====
        $channels = [
            'Zalo Nana',
            'Facebook',
            'Zalo',
            'Hotline',
            'Website',
            'Tiktok',
            'Khách vãng lai',
            'Khác',
            'Zalo Hoatuyêt',
            'Fanpage Hoatuyêt',
            'Facebook Tuyết Võ',
            'Sự kiện Phát Hoàng Gia',
            'CTV Ái Tân',
            'Fanpage PHG',
        ];

        foreach ($channels as $name) {
            DB::table('channels')->updateOrInsert(
                ['name' => $name],
                ['name' => $name, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
