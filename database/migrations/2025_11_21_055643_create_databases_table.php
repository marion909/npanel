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
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('database_name')->unique(); // Actual MySQL database name: {domain}_{name}
            $table->string('display_name'); // User-friendly name shown in UI
            $table->string('mysql_user')->unique(); // MySQL username: db_{domain}_{name}
            $table->text('mysql_password_encrypted'); // Encrypted password
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->integer('size_mb')->default(0)->unsigned(); // Database size in MB
            $table->timestamps();
            
            // Indexes
            $table->index('domain_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
