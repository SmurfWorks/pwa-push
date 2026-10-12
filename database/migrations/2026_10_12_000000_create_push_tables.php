<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('token_hash', 64)->unique();
            $table->json('origins');
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('push_app_id')->constrained('push_apps')->cascadeOnDelete();
            $table->text('endpoint')->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('push_app_id')->constrained('push_apps')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->timestamp('deliver_at');
            $table->unsignedSmallInteger('grace_minutes')->default(30);
            $table->string('title');
            $table->text('body');
            $table->string('url', 2000)->nullable();
            $table->string('icon', 2000)->nullable();
            $table->string('tag')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'key']);
            $table->index(['sent_at', 'skipped_at', 'deliver_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('push_apps');
    }
};
