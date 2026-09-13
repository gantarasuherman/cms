<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32)->default('instagram');
            $table->string('image');
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('permalink');
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'social_posts_live_index');
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
