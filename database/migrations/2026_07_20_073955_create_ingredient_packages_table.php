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
        Schema::create('ingredient_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('package_type', 20);
            $table->string('label', 120);
            $table->decimal('content_amount', 18, 6)->nullable();
            $table->string('content_unit', 20)->nullable();
            $table->decimal('normalized_content_amount', 18, 6)->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'package_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_packages');
    }
};
