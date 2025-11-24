<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subdomain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('hetzner_record_id')->nullable()->index();
            $table->string('type'); // A, AAAA, CNAME, TXT, etc.
            $table->string('name');
            $table->text('value');
            $table->integer('ttl')->default(3600);
            $table->string('status')->default('pending'); // pending, synced, error
            $table->timestamps();

            $table->index(['domain_id', 'subdomain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
