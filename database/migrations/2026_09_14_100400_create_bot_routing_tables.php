<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who gets told about a new complaint, and where the bot reads its answers from.
 *
 * Both are routing tables an administrator fills in, and both exist so the
 * behaviour they describe is data rather than code: adding a new officer or a
 * new "list the latest news" answer must not require a deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 16);                     // whatsapp, telegram
            // A phone number in international form, or a Telegram chat id
            // (negative for a group).
            $table->string('destination', 64);
            $table->boolean('is_active')->default(true);

            // Whether messages arriving from this destination may change a
            // complaint's status. A notification target is not automatically
            // an authority: the officers' group can command, a broadcast
            // channel for the head of office only listens.
            $table->boolean('can_command')->default(false);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['channel', 'destination']);
        });

        // Which categories each recipient hears about. A row per pair, so the
        // admin screen is a set of checkboxes and a query is a plain join —
        // a comma-separated column here would make "who covers Jalan?" a scan.
        Schema::create('bot_recipient_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bot_recipient_id', 'complaint_category_id'], 'bot_recipient_category_unique');
        });

        Schema::create('bot_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Which published content this answers from. Restricted to a fixed
            // vocabulary in code: a free-text model name here would be a
            // ready-made way to read the users table over WhatsApp.
            $table->string('source', 32);                      // news, services, documents, faqs, pages

            $table->unsignedInteger('limit')->default(5);

            // How each row is written into a chat line, and what the detail
            // reply looks like when someone answers with its number.
            $table->string('list_template', 255)->nullable();
            $table->text('detail_template')->nullable();

            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_data_sources');
        Schema::dropIfExists('bot_recipient_categories');
        Schema::dropIfExists('bot_recipients');
    }
};
