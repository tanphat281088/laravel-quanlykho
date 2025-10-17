<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('pipelines')) {
            Schema::create('pipelines', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('channels')) {
            Schema::create('channels', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('channels');
    }
};
