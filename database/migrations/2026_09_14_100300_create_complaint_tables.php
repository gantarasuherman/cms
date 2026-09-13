<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints raised through the bot, and what was done about them.
 *
 * `ticket` is what a member of the public quotes back to check their report, so
 * it must be unguessable: knowing ADU-2026-000041 must not let someone read
 * ADU-2026-000042. It is generated random, not sequential — a sequential code
 * would make every other complaint readable by counting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 64)->nullable();
            $table->text('description')->nullable();

            // Per-category evidence rules: Jalan and Irigasi demand a photo and
            // a location, "Lainnya" does not. The flow reads these rather than
            // hard-coding the category names.
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_location')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket', 24)->unique();
            $table->foreignId('complaint_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bot_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bot_conversation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel', 16);                     // whatsapp, telegram, web
            $table->string('reporter_name')->nullable();
            $table->string('reporter_phone', 32)->nullable();

            $table->text('description');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();

            $table->string('status', 24)->default('baru');     // baru, diproses, selesai, ditolak
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['complaint_category_id', 'status']);
            $table->index('channel');
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();

            // Evidence from the reporter, or proof of completion from an
            // officer. Both are pictures of the same place; only the role of
            // the picture differs, so one table holds them with a kind.
            $table->string('kind', 16)->default('report');     // report, resolution
            $table->string('path');
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['complaint_id', 'kind']);
        });

        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();

            // Who moved it, and from where. A status changed by an officer
            // replying "/selesai" in a Telegram group has no Laravel user
            // session, so the channel and their platform id are recorded too.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16)->default('admin');    // admin, whatsapp, telegram, system
            $table->string('source_actor', 128)->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_updates');
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_categories');
    }
};
