<?php

use App\Data\Measurements\QuantityInput;
use App\Enums\QuantityType;
use App\Models\Recipe;
use App\Services\Measurements\QuantityFormatter;
use App\Services\Measurements\UnitConverter;
use App\Services\Recipes\RecipeScaler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recipe')] class extends Component {
    public Recipe $recipe;
    public int|string $selectedServings = 1;
    protected UnitConverter $converter;
    protected RecipeScaler $scaler;
    protected QuantityFormatter $formatter;

    public function boot(UnitConverter $converter, RecipeScaler $scaler, QuantityFormatter $formatter): void { $this->converter = $converter; $this->scaler = $scaler; $this->formatter = $formatter; }
    public function mount(Recipe $recipe): void { Gate::authorize('view', $recipe); $this->recipe = $recipe->load(['ingredients.ingredient', 'ingredients.ingredientPackage', 'steps', 'categories', 'tags']); $this->selectedServings = $recipe->default_servings; }
    public function updatedSelectedServings(): void { $this->validateOnly('selectedServings', ['selectedServings' => ['required', 'integer', 'min:1', 'max:1000']]); unset($this->scaledIngredients); }
    public function resetServings(): void { $this->selectedServings = $this->recipe->default_servings; unset($this->scaledIngredients); }

    #[Computed]
    public function scaledIngredients(): array
    {
        $selectedServings = filter_var($this->selectedServings, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: $this->recipe->default_servings;

        return $this->recipe->ingredients->map(function ($line) use ($selectedServings): array {
            if ($line->quantity_type === QuantityType::NonExact) { return ['name' => $line->ingredient->name, 'display' => $line->quantity_description, 'status' => $line->non_exact_status?->value]; }

            $package = $line->ingredientPackage;
            $quantity = $package
                ? $this->converter->normalize(new QuantityInput($line->entered_amount, ingredientId: $line->ingredient_id, ingredientPackageId: $package->id, packageContentAmount: $package->content_amount, packageContentUnit: $package->content_unit))
                : $this->converter->normalize(new QuantityInput($line->entered_amount, $line->entered_unit, $line->ingredient_id));
            $scaled = $this->scaler->scaleQuantity($quantity, (string) $selectedServings, (string) $this->recipe->default_servings);
            $display = $package && $package->hasKnownContents()
                ? $this->formatter->formatNormalized($scaled).' ('.$this->formatter->format($scaled, $package->package_type->value).')'
                : $this->formatter->format($scaled, $package?->package_type->value);

            return ['name' => $line->ingredient->name, 'display' => $display, 'status' => null];
        })->all();
    }
}; ?>

<section class="w-full space-y-8">
    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    <div class="grid gap-8 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><div class="flex flex-wrap items-center gap-2"><flux:heading size="xl">{{ $recipe->name }}</flux:heading>@if ($recipe->archived_at)<flux:badge>Archived</flux:badge>@endif</div><flux:text class="mt-2">{{ $recipe->description ?: 'No description provided.' }}</flux:text></div><div class="flex gap-2">@if (! $recipe->archived_at)<flux:button :href="route('recipes.edit', $recipe)" wire:navigate variant="ghost">Edit</flux:button>@endif<flux:button :href="route('recipes.index')" wire:navigate variant="ghost">Back</flux:button></div></div>
            <div class="flex flex-wrap gap-2">@foreach ($recipe->categories as $category)<flux:badge>{{ $category->name }}</flux:badge>@endforeach @foreach ($recipe->tags as $tag)<flux:badge color="zinc">{{ $tag->name }}</flux:badge>@endforeach</div>
            <flux:card class="space-y-5">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><flux:heading size="lg">Ingredients</flux:heading><flux:text class="mt-1">Scaled from the immutable {{ $recipe->default_servings }}-serving source amounts.</flux:text></div><div class="flex items-end gap-2"><div class="w-32"><flux:input wire:model.live="selectedServings" label="Servings" type="number" min="1" /></div><flux:button wire:click="resetServings" variant="ghost">Reset</flux:button></div></div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">@foreach ($this->scaledIngredients as $line)<div class="flex items-center justify-between gap-4 py-3"><span class="font-medium">{{ $line['name'] }}</span><span class="text-right text-zinc-600 dark:text-zinc-300">{{ $line['display'] }} @if ($line['status'])<flux:badge size="sm">{{ str($line['status'])->headline() }}</flux:badge>@endif</span></div>@endforeach</div>
            </flux:card>
            <flux:card class="space-y-4"><flux:heading size="lg">Instructions</flux:heading><ol class="space-y-4">@foreach ($recipe->steps as $step)<li class="flex gap-4"><flux:badge>{{ $step->position }}</flux:badge><p>{{ $step->instruction }}</p></li>@endforeach</ol></flux:card>
        </div>
        <div class="space-y-5">
            @if ($recipe->image_path)<img src="{{ Storage::disk('public')->url($recipe->image_path) }}" alt="{{ $recipe->name }}" class="w-full rounded-xl object-cover" />@else<div class="flex aspect-square items-center justify-center rounded-xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800"><div class="text-center"><flux:icon.photo class="mx-auto size-14" /><flux:text class="mt-2">No recipe image</flux:text></div></div>@endif
            <flux:card class="space-y-3"><flux:heading>Recipe information</flux:heading><flux:text>Default servings: {{ $recipe->default_servings }}</flux:text><flux:text>Preparation: {{ $recipe->preparation_minutes !== null ? $recipe->preparation_minutes.' min' : 'Unknown' }}</flux:text><flux:text>Cooking: {{ $recipe->cooking_minutes !== null ? $recipe->cooking_minutes.' min' : 'Unknown' }}</flux:text><flux:text>Difficulty: {{ $recipe->difficulty ?: 'Unknown' }}</flux:text><flux:text>Cuisine: {{ $recipe->cuisine ?: 'Unknown' }}</flux:text></flux:card>
        </div>
    </div>
</section>
