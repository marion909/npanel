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
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('email')->unique();
            $table->text('password_encrypted');
            $table->integer('quota_mb')->default(1000);
            $table->integer('used_mb')->default(0);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
            
            $table->index('domain_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
