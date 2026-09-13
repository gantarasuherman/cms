<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A profile picture for the person on the other end.
 *
 * Telegram offers one through `getUserProfilePhotos`; the WhatsApp Cloud API
 * does not expose profile pictures at all, so those contacts keep their
 * initials. Nullable for exactly that reason — half of them will never have one.
 *
 * Stored on the private disk like every other piece of chat media: it is a
 * photograph of a member of the public who never chose to publish it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('username');
            // The platform's file id, so an unchanged picture is not fetched
            // again on every message.
            $table->string('avatar_ref', 190)->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_ref']);
        });
    }
};
