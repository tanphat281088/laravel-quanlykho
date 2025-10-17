<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('khach_hangs', function (Blueprint $t) {
            // Đổi cột pipeline -> tinh_trang_khach (nếu chưa có cột mới)
            if (Schema::hasColumn('khach_hangs','pipeline') && !Schema::hasColumn('khach_hangs','tinh_trang_khach')) {
                $t->renameColumn('pipeline','tinh_trang_khach');
            }
            // Bỏ cột hạng_thành_viên nếu còn
            if (Schema::hasColumn('khach_hangs','hang_thanh_vien')) {
                $t->dropColumn('hang_thanh_vien');
            }
        });
    }

    public function down(): void {
        Schema::table('khach_hangs', function (Blueprint $t) {
            if (Schema::hasColumn('khach_hangs','tinh_trang_khach') && !Schema::hasColumn('khach_hangs','pipeline')) {
                $t->renameColumn('tinh_trang_khach','pipeline');
            }
            // Không khôi phục hang_thanh_vien
        });
    }
};
