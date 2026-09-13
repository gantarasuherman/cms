<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two tables that keep the bridge between Laravel and the Python bot honest.
 *
 * `bot_outbox` makes sending durable: the web request that creates a complaint
 * records what must be sent and returns, and delivery is retried out of band.
 * A notification lost because Meta was briefly unreachable is a complaint
 * nobody was told about.
 *
 * `bot_webhook_events` records the platform's message id before the message is
 * acted on. Both Meta and Telegram retry a webhook they believe failed, and
 * without this a retried delivery would file the same complaint twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('destination', 64);
            $table->string('type', 16)->default('text');
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->json('payload')->nullable();

            $table->string('status', 16)->default('pending');  // pending, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
        });

        Schema::create('bot_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            // The platform's own id for the event. Unique, so a replay is
            // rejected by the database rather than by a race-prone check.
            $table->string('external_id', 190);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['channel', 'external_id']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_webhook_events');
        Schema::dropIfExists('bot_outbox');
    }
};
