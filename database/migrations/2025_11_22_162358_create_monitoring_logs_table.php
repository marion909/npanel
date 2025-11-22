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
        Schema::create('monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type'); // 'system', 'domain', 'php-fpm', 'nginx'
            $table->json('metric_value');
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('created_at');
            
            $table->index(['metric_type', 'created_at']);
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_logs');
    }
};
