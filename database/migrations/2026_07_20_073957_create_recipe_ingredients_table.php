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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_package_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('quantity_type', 20);
            $table->decimal('entered_amount', 18, 6)->nullable();
            $table->string('entered_unit', 20)->nullable();
            $table->decimal('normalized_amount', 18, 6)->nullable();
            $table->string('compatibility_key', 100)->nullable();
            $table->string('quantity_description', 255)->nullable();
            $table->string('non_exact_status', 20)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['recipe_id', 'position']);
            $table->index('ingredient_id');
            $table->index('ingredient_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
