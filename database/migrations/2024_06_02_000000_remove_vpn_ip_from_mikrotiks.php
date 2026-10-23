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
        Schema::table('mikrotiks', function (Blueprint $table) {
            // Drop the column; unique index on vpn_ip in vpn_users remains.
            $table->dropColumn('vpn_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->ipAddress('vpn_ip')->nullable()->after('vpn_username');
        });
    }
};
