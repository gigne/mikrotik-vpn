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
            $table->dropColumn('vpn_password_encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->string('vpn_password_encrypted')->nullable()->after('vpn_username');
        });
    }
};
