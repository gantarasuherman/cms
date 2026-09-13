<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a post exist before its picture does.
 *
 * The picture now normally arrives from the platform rather than from an
 * upload, and the fetch happens after the row is written — so `image` cannot
 * stay NOT NULL. Expand only: no existing row loses anything, and every
 * existing path keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            // The platform's URL for the picture, minus its expiring
            // signature, so an unchanged image is not re-downloaded hourly.
            $table->string('remote_image_url', 500)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('remote_image_url');
            // Rows with no picture would break a NOT NULL constraint, so they
            // are given an empty path rather than blocking the rollback.
            \Illuminate\Support\Facades\DB::table('social_posts')->whereNull('image')->update(['image' => '']);
            $table->string('image')->nullable(false)->change();
        });
    }
};
