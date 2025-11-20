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
        Schema::create('subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_domain_id')->constrained('domains')->onDelete('cascade');
            $table->string('subdomain_name');
            $table->string('document_root', 512);
            $table->string('nginx_config_path', 512)->nullable();
            $table->string('php_version', 10)->nullable();
            $table->string('php_fpm_pool', 100)->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamps();

            $table->unique(['subdomain_name', 'parent_domain_id']);
            $table->index('parent_domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdomains');
    }
};
