<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('badge', 48)->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('code', 64)->nullable();
            $table->string('button_text', 64)->nullable();
            $table->string('link')->nullable();
            $table->string('ticker_text', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The public query is always "live, in order"; the same shape the
            // carousel uses, for the same reason.
            $table->index(['is_active', 'start_date', 'end_date'], 'announcements_live_index');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
