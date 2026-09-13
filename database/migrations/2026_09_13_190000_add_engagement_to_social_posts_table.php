<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('account_handle', 64)->nullable()->after('platform');
            // Nullable on purpose: an unknown count shows no number at all,
            // which is honest. Zero would claim the post has no likes.
            $table->unsignedInteger('likes')->nullable()->after('caption');
            $table->unsignedInteger('comments')->nullable()->after('likes');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['account_handle', 'likes', 'comments']);
        });
    }
};
