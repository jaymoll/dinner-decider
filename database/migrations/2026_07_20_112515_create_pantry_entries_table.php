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
        Schema::create('pantry_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_package_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('display_unit', 20)->nullable();
            $table->decimal('total_normalized_amount', 18, 6)->unsigned();
            $table->string('compatibility_key', 120);
            $table->string('merge_key', 140);
            $table->timestamps();

            $table->unique(['user_id', 'ingredient_id', 'merge_key']);
            $table->index(['user_id', 'compatibility_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pantry_entries');
    }
};
