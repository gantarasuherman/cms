<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an administrator supply the platform keys without touching a server.
 *
 * Separate from `settings` and encrypted at rest, because these are the keys
 * to a public service's identity: anybody holding the WhatsApp token can send
 * messages as the institution. A plain column would put them in every database
 * backup and every `SELECT *` an operator runs.
 *
 * The environment stays a valid source — a deployment that already sets them
 * keeps working, and the database simply takes precedence when filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_channels', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('bot_channels', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });
    }
};
