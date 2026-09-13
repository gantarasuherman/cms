<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channels the bot speaks through, and the people it has spoken to.
 *
 * Credentials are deliberately NOT here. Access tokens live in the environment,
 * because AuditLogger records the before and after of every settings change and
 * would write a token into the audit table in clear text. What belongs in the
 * database is everything an administrator legitimately changes: which channel
 * is on, how it greets, which flow it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_channels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();          // whatsapp, telegram
            $table->string('name');
            $table->boolean('is_active')->default(false);

            // Which flow this channel runs. Null means the default flow, so a
            // new channel is usable before anyone has drawn it one.
            $table->foreignId('bot_flow_id')->nullable();

            // Non-secret operational settings: phone number id, bot username,
            // greeting, session timeout. The token itself stays in .env.
            $table->json('settings')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('bot_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_channel_id')->constrained()->cascadeOnDelete();

            // The platform's own id for the person: a WhatsApp wa_id or a
            // Telegram chat id. Unique per channel, never across channels.
            $table->string('external_id', 128);
            $table->string('name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('username', 64)->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['bot_channel_id', 'external_id']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_contacts');
        Schema::dropIfExists('bot_channels');
    }
};
