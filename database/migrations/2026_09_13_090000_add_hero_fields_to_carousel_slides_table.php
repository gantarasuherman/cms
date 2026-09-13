<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the existing carousel for the hero slider.
 *
 * Purely additive. The columns the hero design also needs — a link, a schedule
 * window, ordering, an active flag — already exist here as `link`,
 * `start_date` and `end_date`; renaming them to `button_url`/`start_at`/`end_at`
 * would rewrite nine readers and writers for no behavioural gain, so the
 * established names stay and only the genuinely missing fields are added.
 *
 * Existing rows keep working untouched: every new column is nullable, and
 * down() removes exactly what up() added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('subtitle');
            $table->text('description')->nullable()->after('category');
            // Kept apart from `title`: a decorative slide may need no
            // description while its photograph still needs describing.
            $table->string('alt_text')->nullable()->after('image');
        });

        Schema::table('carousel_slides', function (Blueprint $table) {
            // The public query filters on the active flag and both ends of the
            // schedule window, then orders — one composite index covers it.
            $table->index(['is_active', 'start_date', 'end_date'], 'carousel_slides_live_index');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->dropIndex('carousel_slides_live_index');
            $table->dropIndex(['sort_order']);
        });

        Schema::table('carousel_slides', function (Blueprint $table) {
            $table->dropColumn(['category', 'description', 'alt_text']);
        });
    }
};

