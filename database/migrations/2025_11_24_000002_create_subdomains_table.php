<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. app
            $table->string('full_name')->unique(); // app.example.com
            $table->string('php_version')->nullable(); // override
            $table->string('document_root')->nullable(); // override
            $table->boolean('nginx_enabled')->default(true);
            $table->timestamps();

            $table->unique(['domain_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomains');
    }
};
