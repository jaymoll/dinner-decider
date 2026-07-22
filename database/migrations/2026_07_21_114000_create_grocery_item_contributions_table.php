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
        Schema::create('grocery_item_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planned_dinner_requirement_id')->constrained()->cascadeOnDelete();
            $table->decimal('normalized_amount', 18, 6)->nullable();
            $table->timestamps();

            $table->unique(['grocery_item_id', 'planned_dinner_requirement_id'], 'grocery_contribution_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_item_contributions');
    }
};
