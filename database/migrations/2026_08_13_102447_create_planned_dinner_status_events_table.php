<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('planned_dinner_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_dinner_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamp('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_reconstructed')->default(false);
            $table->timestamps();

            $table->index(['planned_dinner_id', 'occurred_at'], 'planned_dinner_events_timeline_index');
            $table->index('actor_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_dinner_status_events');
    }
};
