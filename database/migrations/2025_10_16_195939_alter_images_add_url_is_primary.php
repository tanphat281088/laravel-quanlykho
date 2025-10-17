<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('images', function (Blueprint $table) {
            if (!Schema::hasColumn('images', 'url')) {
                $table->string('url', 2048)->nullable()->after('path');
            }
            if (!Schema::hasColumn('images', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('url');
            }
            $table->index(['imageable_id','imageable_type','is_primary'], 'images_imageable_primary_idx');
        });
    }

    public function down(): void {
        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasColumn('images', 'is_primary')) {
                $table->dropIndex('images_imageable_primary_idx');
                $table->dropColumn('is_primary');
            }
            if (Schema::hasColumn('images', 'url')) {
                $table->dropColumn('url');
            }
        });
    }
};
