<?php

declare(strict_types=1);

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
        Schema::create('ikev2_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('assigned_ip')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('certificate_serial')->nullable()->unique();
            $table->text('certificate_pem')->nullable();
            $table->text('private_key_pem')->nullable();
            $table->text('ca_certificate_pem')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_enabled');
            $table->index('expired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ikev2_users');
    }
};
