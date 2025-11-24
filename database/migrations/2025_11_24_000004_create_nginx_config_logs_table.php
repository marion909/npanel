<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nginx_config_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subdomain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action'); // create, update, delete, rollback
            $table->longText('previous_config')->nullable();
            $table->longText('new_config')->nullable();
            $table->boolean('success')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'subdomain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nginx_config_logs');
    }
};
