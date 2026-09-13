<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each person is in the conversation, and everything that was said.
 *
 * The session and the transcript are separate tables on purpose: the session is
 * one hot row per person that is updated on every turn, while the transcript is
 * append-only and grows without bound. Keeping them apart means the log can be
 * pruned on its own retention schedule without touching live sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_flow_id')->nullable()->constrained()->nullOnDelete();

            // Pinned at the moment the conversation started, so republishing a
            // flow cannot move someone to a different question mid-answer.
            $table->unsignedInteger('flow_version')->nullable();
            $table->string('current_node', 64)->nullable();

            // Answers gathered so far, keyed by the node that asked. This is
            // what lets a later node build a complaint out of earlier replies.
            $table->json('state')->nullable();

            $table->string('status', 16)->default('active');   // active, completed, expired, blocked
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One live session per person per channel; finished ones are kept.
            $table->index(['bot_contact_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('bot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_conversation_id')->constrained()->cascadeOnDelete();

            $table->string('direction', 8);                    // in, out
            $table->string('type', 16)->default('text');       // text, image, location, document, menu, system
            $table->text('body')->nullable();

            // Media lives on disk; this is the path, never the bytes.
            $table->string('media_path')->nullable();
            $table->string('media_mime', 128)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('node_key', 64)->nullable();
            $table->string('external_id', 128)->nullable();    // the platform's message id, for delivery receipts
            $table->string('delivery_status', 16)->nullable();

            // The untouched webhook payload, for working out why a turn went
            // wrong months later. Pruned with the rest of the transcript.
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['bot_conversation_id', 'created_at']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_messages');
        Schema::dropIfExists('bot_conversations');
    }
};
