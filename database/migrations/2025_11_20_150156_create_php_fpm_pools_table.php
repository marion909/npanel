<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('php_fpm_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('pool_name', 100)->unique();
            $table->string('php_version', 10);
            $table->string('socket_path', 255);
            $table->enum('pm_mode', ['dynamic', 'static', 'ondemand'])->default('dynamic');
            $table->integer('pm_max_children')->default(5);
            $table->integer('pm_start_servers')->default(2);
            $table->integer('pm_min_spare_servers')->default(1);
            $table->integer('pm_max_spare_servers')->default(3);
            $table->string('memory_limit', 20)->default('128M');
            $table->integer('max_execution_time')->default(300);
            $table->timestamps();

            $table->index('domain_id');
            $table->index('php_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('php_fpm_pools');
    }
};
