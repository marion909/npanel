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
        Schema::table('subdomains', function (Blueprint $table) {
            $table->string('ssl_cert_path')->nullable()->after('wordpress_installed');
            $table->string('ssl_key_path')->nullable()->after('ssl_cert_path');
            $table->timestamp('ssl_expiry_date')->nullable()->after('ssl_key_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subdomains', function (Blueprint $table) {
            $table->dropColumn(['ssl_cert_path', 'ssl_key_path', 'ssl_expiry_date']);
        });
    }
};
