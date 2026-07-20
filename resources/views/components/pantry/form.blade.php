@props(['form', 'ingredients', 'submitLabel'])

<form wire:submit="save" class="space-y-6">
    <flux:card class="space-y-6">
        <flux:select wire:model.live="form.ingredient_id" label="Ingredient" placeholder="Choose an ingredient">
            @foreach ($ingredients as $ingredient)
                <flux:select.option :value="$ingredient->id" wire:key="pantry-ingredient-{{ $ingredient->id }}">{{ $ingredient->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:error name="form.ingredient_id" />
        @if ($form->ingredient_id)
            @php($selectedIngredient = $ingredients->firstWhere('id', $form->ingredient_id))
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.unit" label="Direct unit" :disabled="$form->ingredient_package_id !== null">
                    <flux:select.option value="">Use a package</flux:select.option>
                    @foreach (\App\Enums\UnitCode::cases() as $unit)
                        @if ($selectedIngredient && $unit->measurementGroup() === $selectedIngredient->preferred_measurement_group && ($unit->measurementGroup() !== \App\Enums\MeasurementGroup::Count || $unit === $selectedIngredient->preferred_unit))
                            <flux:select.option :value="$unit->value">{{ $unit->label() }} ({{ $unit->value }})</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.ingredient_package_id" label="Package definition" :disabled="filled($form->unit)">
                    <flux:select.option value="">Use a direct unit</flux:select.option>
                    @foreach ($selectedIngredient?->packages ?? [] as $package)
                        <flux:select.option :value="$package->id" wire:key="pantry-package-{{ $package->id }}">{{ $package->label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:error name="form.unit" />
            <flux:error name="form.ingredient_package_id" />
        @endif
        <flux:input wire:model="form.amount" label="Amount" inputmode="decimal" placeholder="For example 1.5 or 1/2" />
        <flux:error name="form.amount" />
    </flux:card>
    <div class="flex justify-end gap-2"><flux:button :href="route('pantry.index')" wire:navigate variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button></div>
</form>
