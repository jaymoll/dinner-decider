@props(['form', 'submitLabel' => 'Save ingredient'])

<form wire:submit="save" class="space-y-8">
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">Ingredient details</flux:heading>
            <flux:text class="mt-1">Choose the normal measurement used when adding this ingredient to a recipe.</flux:text>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <flux:input wire:model="form.name" label="Name" required autofocus />
            <flux:input wire:model="form.category" label="Category" placeholder="e.g. Dry goods" />

            <flux:select wire:model.live="form.preferred_measurement_group" label="Measurement group" required>
                @foreach ([\App\Enums\MeasurementGroup::Mass, \App\Enums\MeasurementGroup::Volume, \App\Enums\MeasurementGroup::Count] as $group)
                    <flux:select.option value="{{ $group->value }}">{{ str($group->value)->headline() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.preferred_unit" label="Preferred unit" required>
                @foreach (\App\Enums\UnitCode::cases() as $unit)
                    @if ($unit->measurementGroup()->value === $form->preferred_measurement_group)
                        <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }} ({{ $unit->value }})</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
        </div>

        <flux:switch wire:model="form.is_staple" label="This is a staple I normally keep available" />
    </flux:card>

    <flux:card class="space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">Aliases</flux:heading>
                <flux:text class="mt-1">Optional alternative names for finding this ingredient.</flux:text>
            </div>
            <flux:button type="button" wire:click="addAlias" variant="ghost" icon="plus">Add alias</flux:button>
        </div>

        @forelse ($form->aliases as $index => $alias)
            <div wire:key="alias-{{ $index }}" class="flex items-start gap-3">
                <div class="flex-1">
                    <flux:input wire:model="form.aliases.{{ $index }}" label="Alias {{ $index + 1 }}" />
                </div>
                <flux:button type="button" wire:click="removeAlias({{ $index }})" variant="ghost" icon="trash" aria-label="Remove alias" class="mt-6" />
            </div>
        @empty
            <flux:text>No aliases added.</flux:text>
        @endforelse
        <flux:error name="aliases" />
    </flux:card>

    <flux:card class="space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">Packages</flux:heading>
                <flux:text class="mt-1">Metric contents make cans, jars, packs, bags, and bottles calculation-safe.</flux:text>
            </div>
            <flux:button type="button" wire:click="addPackage" variant="ghost" icon="plus">Add package</flux:button>
        </div>

        @forelse ($form->packages as $index => $package)
            <div wire:key="package-{{ $package['key'] }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-5">
                <flux:select wire:model="form.packages.{{ $index }}.package_type" label="Type">
                    @foreach (\App\Enums\PackageType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="md:col-span-2">
                    <flux:input wire:model="form.packages.{{ $index }}.label" label="Label" placeholder="400 g can" />
                </div>
                <flux:input wire:model="form.packages.{{ $index }}.content_amount" label="Metric contents" placeholder="400" />
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:select wire:model="form.packages.{{ $index }}.content_unit" label="Unit">
                            <flux:select.option value="">Unknown</flux:select.option>
                            @foreach (\App\Enums\UnitCode::cases() as $unit)
                                @if ($unit->isMetricContentUnit())
                                    <flux:select.option value="{{ $unit->value }}">{{ $unit->value }}</flux:select.option>
                                @endif
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button type="button" wire:click="removePackage({{ $index }})" variant="ghost" icon="trash" aria-label="Remove package" />
                </div>
            </div>
        @empty
            <flux:text>No package definitions added.</flux:text>
        @endforelse
    </flux:card>

    <div class="flex items-center justify-end gap-3">
        <flux:button :href="route('ingredients.index')" wire:navigate variant="ghost">Cancel</flux:button>
        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ $submitLabel }}</flux:button>
    </div>
</form>
