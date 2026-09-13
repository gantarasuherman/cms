<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an editor decided about a question people keep asking the bot.
 *
 * Only the decision lives here. The questions themselves, and how often each
 * was asked, are counted from `bot_messages` every time the screen is opened —
 * a copy would drift the moment a new message arrived, and there is no
 * reconciliation job worth writing for a table that can simply be recomputed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_question_topics', function (Blueprint $table) {
            $table->id();

            // The normalised form of the question: lowercase, stripped of
            // punctuation. Two people asking the same thing land on one row.
            $table->string('fingerprint', 191)->unique();

            // The wording the decision was made against, kept so the screen
            // can show what was dismissed without re-deriving it.
            $table->string('sample', 500);

            $table->string('status', 16)->default('new');
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_question_topics');
    }
};
