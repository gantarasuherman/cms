<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation flow: a directed graph an administrator draws.
 *
 * Nodes and edges are rows rather than one JSON blob on the flow, so a single
 * node can be validated, reordered or reported on without rewriting the whole
 * diagram — and so a half-saved editor session cannot corrupt every other node.
 *
 * `bot_flow_versions` keeps a snapshot each time a flow is published. A live
 * conversation stays pinned to the version it started on, so editing a flow
 * never drops someone midway through a form they are filling in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });

        Schema::create('bot_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_flow_id')->constrained()->cascadeOnDelete();

            // Stable within a flow and authored by the editor, so an edge can
            // name its endpoints without depending on insert order.
            $table->string('key', 64);
            $table->string('type', 32);                    // start, message, menu, input, action, data_source, end
            $table->string('label')->nullable();

            // Everything type-specific: reply text, expected input, validation,
            // menu options, retry behaviour, data source binding. Kept as JSON
            // because each type needs a different shape and the set of types
            // will grow — a column per setting would not survive that.
            $table->json('config')->nullable();

            // Canvas placement. Editor state, not behaviour, but it has to
            // survive a reload or the diagram rearranges itself.
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);

            $table->timestamps();

            $table->unique(['bot_flow_id', 'key']);
            $table->index(['bot_flow_id', 'type']);
        });

        Schema::create('bot_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_flow_id')->constrained()->cascadeOnDelete();
            $table->string('from_node', 64);
            $table->string('to_node', 64);

            // Which outcome takes this edge: a menu option value, "valid",
            // "invalid", "timeout", or null for an unconditional next step.
            $table->string('condition', 64)->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['bot_flow_id', 'from_node']);
        });

        Schema::create('bot_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // The whole graph as it was published, so a running conversation
            // can keep following the shape it began on.
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bot_flow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flow_versions');
        Schema::dropIfExists('bot_edges');
        Schema::dropIfExists('bot_nodes');
        Schema::dropIfExists('bot_flows');
    }
};
