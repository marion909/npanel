<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('status')->default('pending'); // pending, active, error, deleted
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('hetzner_zone_id')->nullable()->index();
            $table->boolean('wildcard_ssl_enabled')->default(false);
            $table->string('wildcard_ssl_status')->nullable(); // pending, issued, failed, renewing
            $table->timestamp('wildcard_ssl_last_issued_at')->nullable();
            $table->string('php_version')->default('8.2'); // default PHP version
            $table->string('document_root');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
