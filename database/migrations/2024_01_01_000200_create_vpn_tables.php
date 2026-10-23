<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- VPN users (chap-secrets entries) ---
        Schema::create('vpn_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->ipAddress('assigned_ip')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->dateTime('expired_at')->nullable()->index();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // --- MikroTik branches ---
        Schema::create('mikrotiks', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 100)->unique();
            $table->text('description')->nullable();
            $table->string('vpn_username', 100)->unique();
            $table->unsignedInteger('winbox_internal_port')->default(8291);
            $table->unsignedInteger('webfig_internal_port')->default(80);
            $table->unsignedInteger('winbox_public_port')->nullable();
            $table->unsignedInteger('webfig_public_port')->nullable();
            $table->string('routeros_version', 40)->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('ssh_public_key', 1024)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_online');
            $table->index('last_seen_at');
        });

        // --- Nginx port-forward mappings ---
        Schema::create('nginx_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')
                  ->constrained()
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->enum('conn_type', ['winbox', 'webfig']);
            $table->unsignedInteger('public_port');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['conn_type', 'public_port']);
        });

        // --- Router config backups ---
        Schema::create('router_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mikrotik_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('backup_path', 500);
            $table->char('checksum', 32)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamps();

            $table->index('mikrotik_id');
        });

        // --- Audit trail ---
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // --- In-app notifications ---
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('title', 200);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_read']);
        });

        // --- Key/value settings ---
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('cast', 30)->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('router_backups');
        Schema::dropIfExists('nginx_mappings');
        Schema::dropIfExists('mikrotiks');
        Schema::dropIfExists('vpn_users');
    }
};