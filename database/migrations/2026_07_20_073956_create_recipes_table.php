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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_servings');
            $table->unsignedSmallInteger('preparation_minutes')->nullable();
            $table->unsignedSmallInteger('cooking_minutes')->nullable();
            $table->string('difficulty', 80)->nullable();
            $table->string('cuisine', 80)->nullable();
            $table->string('meal_type', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
