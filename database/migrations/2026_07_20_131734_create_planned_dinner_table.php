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
        Schema::create('planned_dinners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dinner_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipe_name', 160);
            $table->text('recipe_description')->nullable();
            $table->decimal('source_servings', 18, 6);
            $table->decimal('servings', 18, 6);
            $table->unsignedSmallInteger('preparation_minutes')->nullable();
            $table->unsignedSmallInteger('cooking_minutes')->nullable();
            $table->string('difficulty', 80)->nullable();
            $table->string('cuisine', 80)->nullable();
            $table->string('meal_type', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->json('recipe_steps');
            $table->json('recipe_categories');
            $table->json('recipe_tags');
            $table->date('planned_date')->nullable();
            $table->string('status', 20)->default('planned');
            $table->unsignedInteger('position');
            $table->timestamp('cooked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['dinner_plan_id', 'status', 'planned_date', 'position'], 'planned_dinners_active_priority_index');
            $table->index(['dinner_plan_id', 'status', 'cooked_at', 'cancelled_at'], 'planned_dinners_history_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_dinners');
    }
};
