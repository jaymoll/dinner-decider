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
        Schema::create('grocery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ingredient_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('generation_key', 64)->nullable();
            $table->string('name', 160);
            $table->decimal('calculated_amount', 18, 6)->nullable();
            $table->string('calculated_unit', 20)->nullable();
            $table->string('quantity_description')->nullable();
            $table->string('package_label')->nullable();
            $table->decimal('override_amount', 18, 6)->nullable();
            $table->string('override_unit', 20)->nullable();
            $table->boolean('is_manually_adjusted')->default(false);
            $table->string('category', 40)->default('other');
            $table->timestamp('checked_at')->nullable();
            $table->decimal('previous_calculated_amount', 18, 6)->nullable();
            $table->timestamp('quantity_increased_at')->nullable();
            $table->timestamps();

            $table->unique(['grocery_list_id', 'source', 'generation_key'], 'grocery_generated_key_unique');
            $table->index(['grocery_list_id', 'checked_at'], 'grocery_checked_index');
            $table->index(['grocery_list_id', 'category'], 'grocery_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_items');
    }
};
