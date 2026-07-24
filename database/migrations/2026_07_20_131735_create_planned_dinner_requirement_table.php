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
        Schema::create('planned_dinner_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planned_dinner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ingredient_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ingredient_name', 160);
            $table->string('package_label')->nullable();
            $table->string('package_type', 30)->nullable();
            $table->decimal('package_content_amount', 18, 6)->nullable();
            $table->string('package_content_unit', 20)->nullable();
            $table->decimal('package_normalized_content_amount', 18, 6)->nullable();
            $table->string('quantity_type', 20);
            $table->decimal('source_entered_amount', 18, 6)->nullable();
            $table->string('source_entered_unit', 20)->nullable();
            $table->decimal('source_normalized_amount', 18, 6)->nullable();
            $table->decimal('scaled_amount', 18, 6)->nullable();
            $table->string('compatibility_key', 120)->nullable();
            $table->string('quantity_description')->nullable();
            $table->string('non_exact_status', 20)->nullable();
            $table->string('coverage', 30)->default('missing');
            $table->decimal('missing_amount', 18, 6)->nullable();
            $table->json('unresolved_at_cooking')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['planned_dinner_id', 'position'], 'dinner_requirement_position_unique');
            $table->index(['ingredient_id', 'compatibility_key'], 'requirement_compatibility_index');
            $table->index(['planned_dinner_id', 'coverage'], 'requirement_coverage_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_dinner_requirements');
    }
};
