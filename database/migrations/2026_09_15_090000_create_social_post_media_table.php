<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A post can carry more than one picture.
 *
 * Instagram calls it a carousel album; until now only the slide named by
 * `?img_index=` was kept, so a ten-picture post showed as one. The cover still
 * lives on `social_posts.image` — every existing row, every query that filters
 * on it and the admin upload all keep working — and these rows are the slides
 * beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // A video is kept as its poster frame plus a link out: mirroring
            // the file would mean unbounded storage for something the platform
            // already serves, and its signed URL expires within days anyway.
            $table->string('kind', 16)->default('image');

            $table->string('path');
            $table->string('remote_url', 512)->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['social_post_id', 'sort_order']);
        });

        Schema::table('social_posts', function (Blueprint $table) {
            // IMAGE / VIDEO / CAROUSEL_ALBUM, as the platform reports it.
            $table->string('media_type', 24)->nullable()->after('platform');

            // Not readable from any API for a third-party account, so it is an
            // editor's statement of fact rather than a synced figure.
            $table->boolean('is_verified')->default(false)->after('account_handle');

            // A handful of comments to preview, [{username, text}, …]. Only
            // arrives when the token carries instagram_manage_comments.
            $table->json('top_comments')->nullable()->after('comments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_media');

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'is_verified', 'top_comments']);
        });
    }
};
