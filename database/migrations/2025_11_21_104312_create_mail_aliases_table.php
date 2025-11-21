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
        Schema::create('mail_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('source'); // email@domain.com or @domain.com for catch-all
            $table->string('destination'); // target email address
            $table->enum('type', ['alias', 'catchall'])->default('alias');
            $table->timestamps();
            
            $table->index('domain_id');
            $table->index(['source', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
    }
};
