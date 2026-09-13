<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 512);
            $table->string('route_name', 120)->nullable();

            /*
             | Pseudonymous visitor key: sha256(ip + user agent + date + app key).
             |
             | No raw IP or user agent is ever stored. The date is part of the
             | input, so the hash rotates every midnight: it can count unique
             | visitors within a day but cannot follow anyone across days, and
             | it cannot be reversed into an address.
             */
            $table->char('visitor_hash', 64);

            $table->string('referrer_host', 255)->nullable();

            // Denormalised day, so the daily aggregation is an index range scan
            // rather than DATE() applied to every row.
            $table->date('viewed_on');
            $table->timestamp('viewed_at');

            $table->index('viewed_on');
            $table->index(['viewed_on', 'visitor_hash']);
            $table->index(['viewed_on', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};

