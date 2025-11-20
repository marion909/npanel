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
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('certificate_path', 512)->nullable();
            $table->string('private_key_path', 512)->nullable();
            $table->string('chain_path', 512)->nullable();
            $table->enum('provider', ['letsencrypt', 'manual', 'self-signed'])->default('letsencrypt');
            $table->timestamp('issue_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('last_renewal_attempt')->nullable();
            $table->timestamps();

            $table->index('domain_id');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
