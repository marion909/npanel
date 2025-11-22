<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mark existing WordPress subdomains
        DB::table('subdomains')
            ->whereIn('id', [55, 57, 58])
            ->update(['wordpress_installed' => 1]);
    }

    public function down(): void
    {
        //
    }
};
