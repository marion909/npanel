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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('domain_name')->unique();
            $table->string('document_root', 512);
            $table->string('nginx_config_path', 512)->nullable();
            $table->string('php_version', 10)->default('8.3');
            $table->string('php_fpm_pool', 100)->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_cert_path', 512)->nullable();
            $table->string('ssl_key_path', 512)->nullable();
            $table->timestamp('ssl_expiry_date')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'deleted'])->default('pending');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
