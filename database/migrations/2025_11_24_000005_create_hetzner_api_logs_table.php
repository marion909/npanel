<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hetzner_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subdomain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('endpoint');
            $table->longText('request_payload')->nullable();
            $table->integer('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'subdomain_id']);
            $table->index(['endpoint', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hetzner_api_logs');
    }
};
