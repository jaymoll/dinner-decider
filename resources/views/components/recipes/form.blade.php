@props(['form', 'ingredientOptions', 'currentImagePath' => null, 'submitLabel' => 'Save recipe'])

<form wire:submit="save" class="space-y-8">
    <flux:card class="space-y-6">
        <div><flux:heading size="lg">Recipe details</flux:heading><flux:text class="mt-1">Only the name, servings, one ingredient, and one instruction are required.</flux:text></div>
        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2"><flux:input wire:model="form.name" label="Name" required autofocus /></div>
            <div class="md:col-span-2"><flux:textarea wire:model="form.description" label="Description" rows="3" /></div>
            <flux:input wire:model="form.default_servings" label="Default servings" type="number" min="1" required />
            <flux:input wire:model="form.meal_type" label="Meal type" placeholder="Dinner" />
            <flux:input wire:model="form.preparation_minutes" label="Preparation time (minutes)" type="number" min="0" />
            <flux:input wire:model="form.cooking_minutes" label="Cooking time (minutes)" type="number" min="0" />
            <flux:input wire:model="form.difficulty" label="Difficulty" />
            <flux:input wire:model="form.cuisine" label="Cuisine" />
            <flux:input wire:model="form.categoryNames" label="Categories" description="Separate names with commas" />
            <flux:input wire:model="form.tagNames" label="Tags" description="Separate names with commas" />
            <div class="md:col-span-2"><flux:input wire:model="form.source_url" label="Source URL" type="url" /></div>
            <div class="md:col-span-2"><flux:textarea wire:model="form.notes" label="Notes" rows="3" /></div>
        </div>
    </flux:card>

    <flux:card class="space-y-5">
        <div class="flex items-center justify-between gap-4"><div><flux:heading size="lg">Ingredients</flux:heading><flux:text class="mt-1">Use exact metric/count quantities, a defined package, or a written non-exact amount.</flux:text></div><flux:button type="button" wire:click="addIngredient" variant="ghost" icon="plus">Add ingredient</flux:button></div>
        @if ($ingredientOptions->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle" heading="Create an ingredient first"><flux:button :href="route('ingredients.create')" wire:navigate size="sm" class="mt-3">New ingredient</flux:button></flux:callout>
        @endif

        <div wire:sort="moveIngredient" class="space-y-4">
            @foreach ($form->ingredients as $index => $row)
                @php $selectedIngredient = $ingredientOptions->firstWhere('id', (int) $row['ingredient_id']); @endphp
                <div wire:key="recipe-ingredient-{{ $row['key'] }}" wire:sort:item="{{ $row['key'] }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="mb-4 flex items-center justify-between"><flux:badge>Ingredient {{ $index + 1 }}</flux:badge><flux:button type="button" wire:click="removeIngredient({{ $index }})" wire:sort:ignore variant="ghost" size="sm" icon="trash">Remove</flux:button></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="form.ingredients.{{ $index }}.ingredient_id" wire:change="ingredientChanged({{ $index }})" label="Ingredient" required>
                            <flux:select.option value="">Choose an ingredient</flux:select.option>
                            @foreach ($ingredientOptions as $ingredient)
                                <flux:select.option value="{{ $ingredient->id }}">{{ $ingredient->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="form.ingredients.{{ $index }}.quantity_type" wire:change="quantityTypeChanged({{ $index }})" label="Quantity type">
                            <flux:select.option value="exact">Exact</flux:select.option>
                            <flux:select.option value="non_exact">Non-exact</flux:select.option>
                        </flux:select>

                        @if ($row['quantity_type'] === 'non_exact')
                            <div class="md:col-span-2"><flux:input wire:model="form.ingredients.{{ $index }}.description" label="Written quantity" placeholder="Salt to taste" /></div>
                            <flux:select wire:model="form.ingredients.{{ $index }}.non_exact_status" label="Status"><flux:select.option value="required">Required</flux:select.option><flux:select.option value="optional">Optional</flux:select.option></flux:select>
                        @else
                            <flux:input wire:model="form.ingredients.{{ $index }}.amount" label="Amount" placeholder="1.5 or 1 1/2" />
                            @if ($selectedIngredient?->packages->isNotEmpty())
                                <flux:select wire:model.live="form.ingredients.{{ $index }}.ingredient_package_id" label="Package (optional)">
                                    <flux:select.option value="">Use a unit</flux:select.option>
                                    @foreach ($selectedIngredient->packages as $package)
                                        <flux:select.option value="{{ $package->id }}">{{ $package->label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                            @if (blank($row['ingredient_package_id']))
                                <flux:select wire:model="form.ingredients.{{ $index }}.unit" label="Unit">
                                    @foreach (\App\Enums\UnitCode::cases() as $unit)
                                        @if (! $selectedIngredient || ($unit->measurementGroup() === $selectedIngredient->preferred_measurement_group && ($unit->measurementGroup() !== \App\Enums\MeasurementGroup::Count || $unit === $selectedIngredient->preferred_unit)))
                                            <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }} ({{ $unit->value }})</flux:select.option>
                                        @endif
                                    @endforeach
                                </flux:select>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <flux:error name="ingredients" />
    </flux:card>

    <flux:card class="space-y-5">
        <div class="flex items-center justify-between gap-4"><div><flux:heading size="lg">Instructions</flux:heading><flux:text class="mt-1">Drag steps into the order they should be followed.</flux:text></div><flux:button type="button" wire:click="addStep" variant="ghost" icon="plus">Add step</flux:button></div>
        <div wire:sort="moveStep" class="space-y-3">
            @foreach ($form->steps as $index => $step)
                <div wire:key="recipe-step-{{ $step['key'] }}" wire:sort:item="{{ $step['key'] }}" class="flex items-start gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:badge>{{ $index + 1 }}</flux:badge>
                    <div class="flex-1"><flux:textarea wire:model="form.steps.{{ $index }}.instruction" label="Step {{ $index + 1 }}" rows="2" /></div>
                    <flux:button type="button" wire:click="removeStep({{ $index }})" wire:sort:ignore variant="ghost" icon="trash" aria-label="Remove step" />
                </div>
            @endforeach
        </div>
        <flux:error name="steps" />
    </flux:card>

    <flux:card class="space-y-4">
        <div><flux:heading size="lg">Image</flux:heading><flux:text class="mt-1">Optional JPG, PNG, or WebP up to 4 MB.</flux:text></div>
        @if ($currentImagePath)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentImagePath) }}" alt="Current recipe image" class="h-40 w-56 rounded-lg object-cover" />
            <flux:checkbox wire:model="form.remove_image" label="Remove current image" />
        @endif
        <flux:input wire:model="form.image" type="file" label="{{ $currentImagePath ? 'Replace image' : 'Recipe image' }}" accept="image/jpeg,image/png,image/webp" />
    </flux:card>

    <div class="flex justify-end gap-3"><flux:button :href="route('recipes.index')" wire:navigate variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $submitLabel }}</flux:button></div>
</form>
