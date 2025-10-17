<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('san_phams', function (Blueprint $table) {
            if (!Schema::hasColumn('san_phams', 'gia_chinh')) {
                $table->unsignedBigInteger('gia_chinh')->default(0);
            }
            if (!Schema::hasColumn('san_phams', 'gia_dat_ngay')) {
                $table->unsignedBigInteger('gia_dat_ngay')->default(0);
            }
        });
    }

    public function down(): void {
        Schema::table('san_phams', function (Blueprint $table) {
            if (Schema::hasColumn('san_phams', 'gia_dat_ngay')) {
                $table->dropColumn('gia_dat_ngay');
            }
            if (Schema::hasColumn('san_phams', 'gia_chinh')) {
                $table->dropColumn('gia_chinh');
            }
        });
    }
};
