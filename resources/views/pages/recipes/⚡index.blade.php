<?php

use App\Actions\DinnerPlans\PlanDinner;
use App\Actions\Recipes\ArchiveRecipe;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Tag;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Recipes')] class extends Component {
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $category = '';
    #[Url] public string $tag = '';

    public function mount(): void { Gate::authorize('viewAny', Recipe::class); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategory(): void { $this->resetPage(); }
    public function updatedTag(): void { $this->resetPage(); }

    public function archive(int $recipeId, ArchiveRecipe $archiveRecipe): void
    {
        $recipe = Recipe::query()->whereBelongsTo($this->user())->findOrFail($recipeId);
        $archiveRecipe->handle($this->user(), $recipe); unset($this->recipes);
        Flux::toast(variant: 'success', text: 'Recipe archived.');
    }

    public function planDinner(int $recipeId, PlanDinner $planDinner): void
    {
        $recipe = Recipe::query()->whereBelongsTo($this->user())->active()->findOrFail($recipeId);
        $planDinner->handle($this->user(), $recipe, (string) $recipe->default_servings);
        Flux::toast(variant: 'success', text: 'Dinner added to your plan.');
    }

    #[Computed]
    public function recipes(): LengthAwarePaginator
    {
        return Recipe::query()->whereBelongsTo($this->user())->active()->search($this->search)
            ->when($this->category !== '', fn ($query) => $query->whereHas('categories', fn ($query) => $query->whereKey($this->category)))
            ->when($this->tag !== '', fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereKey($this->tag)))
            ->with(['categories:id,name', 'tags:id,name'])->withCount('ingredients')->latest()->paginate(12);
    }

    #[Computed]
    public function categories(): Collection { return RecipeCategory::query()->whereBelongsTo($this->user())->oldest('name')->get(['id', 'name']); }

    #[Computed]
    public function tags(): Collection { return Tag::query()->whereBelongsTo($this->user())->oldest('name')->get(['id', 'name']); }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><flux:heading size="xl">Recipes</flux:heading><flux:text class="mt-1">Create, organise, and scale your recipe catalogue.</flux:text></div><div class="flex gap-2"><flux:button :href="route('recipes.archive')" wire:navigate variant="ghost">Archive</flux:button><flux:button :href="route('recipes.create')" wire:navigate variant="primary" icon="plus">New recipe</flux:button></div></div>
    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    <div class="grid gap-3 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by recipe name" icon="magnifying-glass" clearable />
        <flux:select wire:model.live="category"><flux:select.option value="">All categories</flux:select.option>@foreach ($this->categories as $option)<flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model.live="tag"><flux:select.option value="">All tags</flux:select.option>@foreach ($this->tags as $option)<flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>@endforeach</flux:select>
    </div>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->recipes as $recipe)
            <flux:card wire:key="recipe-{{ $recipe->id }}" class="overflow-hidden p-0!">
                @if ($recipe->image_path)<img src="{{ Storage::disk('public')->url($recipe->image_path) }}" alt="{{ $recipe->name }}" class="h-44 w-full object-cover" />@else<div class="flex h-44 items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-800"><flux:icon.photo class="size-12" /></div>@endif
                <div class="space-y-4 p-5"><div><flux:heading size="lg">{{ $recipe->name }}</flux:heading><flux:text class="mt-1">{{ $recipe->default_servings }} servings · {{ $recipe->ingredients_count }} ingredients</flux:text></div><div class="flex flex-wrap gap-2">@foreach ($recipe->categories as $recipeCategory)<flux:badge>{{ $recipeCategory->name }}</flux:badge>@endforeach @foreach ($recipe->tags as $recipeTag)<flux:badge color="zinc">{{ $recipeTag->name }}</flux:badge>@endforeach</div><div class="flex justify-end gap-2"><flux:button wire:click="planDinner({{ $recipe->id }})" size="sm" variant="primary">Plan dinner</flux:button><flux:button :href="route('recipes.show', $recipe)" wire:navigate size="sm">View</flux:button><flux:button :href="route('recipes.edit', $recipe)" wire:navigate size="sm" variant="ghost">Edit</flux:button><flux:button wire:click="archive({{ $recipe->id }})" wire:confirm="Archive this recipe?" size="sm" variant="ghost">Archive</flux:button></div></div>
            </flux:card>
        @empty
            <flux:callout class="md:col-span-2 xl:col-span-3" icon="book-open">No recipes found.</flux:callout>
        @endforelse
    </div>
    {{ $this->recipes->links() }}
</section>
