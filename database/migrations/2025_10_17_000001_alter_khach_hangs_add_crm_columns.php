<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('khach_hangs', function (Blueprint $t) {
            if (!Schema::hasColumn('khach_hangs','ma_khach_hang')) {
                $t->string('ma_khach_hang')->nullable()->unique()->after('id');
            }
            if (Schema::hasColumn('khach_hangs','so_dien_thoai')) {
                $t->string('so_dien_thoai', 20)->change();
            }
            if (!Schema::hasColumn('khach_hangs','kenh_lien_he')) {
                $t->string('kenh_lien_he')->nullable()->after('dia_chi');
            }
            if (!Schema::hasColumn('khach_hangs','pipeline')) {
                $t->string('pipeline')->nullable()->after('kenh_lien_he');
            }
            if (!Schema::hasColumn('khach_hangs','staff_id')) {
                $t->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete()->after('pipeline');
            }
            if (!Schema::hasColumn('khach_hangs','staff_name')) {
                $t->string('staff_name')->nullable()->after('staff_id');
            }
            if (!Schema::hasColumn('khach_hangs','doanh_thu_tich_luy')) {
                $t->unsignedBigInteger('doanh_thu_tich_luy')->default(0)->after('ghi_chu');
            }
            if (!Schema::hasColumn('khach_hangs','diem_tich_luy')) {
                $t->unsignedBigInteger('diem_tich_luy')->default(0)->after('doanh_thu_tich_luy');
            }
            if (!Schema::hasColumn('khach_hangs','hang_thanh_vien')) {
                $t->string('hang_thanh_vien')->nullable()->after('diem_tich_luy');
            }
            if (!Schema::hasColumn('khach_hangs','ngay_cap_nhat_hang')) {
                $t->date('ngay_cap_nhat_hang')->nullable()->after('hang_thanh_vien');
            }
            if (!Schema::hasColumn('khach_hangs','ngay_lien_he')) {
                $t->date('ngay_lien_he')->nullable()->after('ngay_cap_nhat_hang');
            }
        });

        // Unique index cho SĐT (an toàn nếu đã có sẽ bỏ qua)
        try {
            Schema::table('khach_hangs', function (Blueprint $t) {
                $t->unique('so_dien_thoai', 'kh_phone_unique');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void {
        Schema::table('khach_hangs', function (Blueprint $t) {
            if (Schema::hasColumn('khach_hangs','so_dien_thoai')) {
                $t->dropUnique('kh_phone_unique');
            }
            $cols = [
                'ma_khach_hang','kenh_lien_he','pipeline','staff_id','staff_name',
                'doanh_thu_tich_luy','diem_tich_luy','hang_thanh_vien','ngay_cap_nhat_hang','ngay_lien_he'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('khach_hangs',$c)) $t->dropColumn($c);
            }
        });
    }
};
