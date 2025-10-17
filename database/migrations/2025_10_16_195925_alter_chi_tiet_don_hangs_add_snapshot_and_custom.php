<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'price_snapshot')) {
                $table->unsignedBigInteger('price_snapshot')->default(0)->after('don_gia');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'price_mode')) {
                $table->enum('price_mode', ['PREORDER','INSTANT','CUSTOM'])
                      ->default('INSTANT')->after('price_snapshot');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('price_mode');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'custom_sku')) {
                $table->string('custom_sku', 64)->nullable()->after('is_custom');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'custom_name')) {
                $table->string('custom_name', 255)->nullable()->after('custom_sku');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'custom_desc')) {
                $table->text('custom_desc')->nullable()->after('custom_name');
            }
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'custom_image_url')) {
                $table->string('custom_image_url', 2048)->nullable()->after('custom_desc');
            }
        });

        // Cho product_id cho phép NULL mà KHÔNG cần doctrine/dbal:
        // 1) Drop FK nếu tồn tại (tên FK phổ biến dưới đây, nếu khác thì cũng không sao – sẽ try/catch)
        try { DB::statement('ALTER TABLE chi_tiet_don_hangs DROP FOREIGN KEY chi_tiet_don_hangs_product_id_foreign'); } catch (\Throwable $e) {}

        // 2) Đổi cột sang NULL bằng SQL thuần
        try { DB::statement('ALTER TABLE chi_tiet_don_hangs MODIFY product_id BIGINT UNSIGNED NULL'); } catch (\Throwable $e) {}

        // 3) Thêm lại FK (NULL ON DELETE)
        try { DB::statement('ALTER TABLE chi_tiet_don_hangs ADD CONSTRAINT chi_tiet_don_hangs_product_id_foreign FOREIGN KEY (product_id) REFERENCES san_phams(id) ON DELETE SET NULL'); } catch (\Throwable $e) {}
    }

    public function down(): void {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            foreach (['custom_image_url','custom_desc','custom_name','custom_sku','is_custom','price_mode','price_snapshot'] as $col) {
                if (Schema::hasColumn('chi_tiet_don_hangs', $col)) $table->dropColumn($col);
            }
        });
        // Không buộc đổi product_id về NOT NULL để tránh xung đột dữ liệu custom đã phát sinh.
    }
};
