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
        Schema::create('ingredient_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_dinner_requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pantry_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('normalized_amount', 18, 6);
            $table->timestamps();

            $table->unique(['planned_dinner_requirement_id', 'pantry_entry_id'], 'requirement_pantry_unique');
            $table->index(['pantry_entry_id', 'planned_dinner_requirement_id'], 'pantry_reservation_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_reservations');
    }
};
