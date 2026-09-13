<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // The platform's own id for the post. Resolved once by matching the
            // permalink, then reused so later syncs cost one request each.
            $table->string('remote_id', 128)->nullable()->after('permalink');
            $table->boolean('sync_enabled')->default(true)->after('remote_id');
            $table->timestamp('synced_at')->nullable()->after('sync_enabled');
            $table->string('sync_status', 16)->nullable()->after('synced_at');
            $table->string('sync_message', 500)->nullable()->after('sync_status');

            $table->index(['sync_enabled', 'synced_at'], 'social_posts_sync_index');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropIndex('social_posts_sync_index');
            $table->dropColumn(['remote_id', 'sync_enabled', 'synced_at', 'sync_status', 'sync_message']);
        });
    }
};
