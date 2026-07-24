<?php

use App\Actions\DinnerPlans\PlanArchivedRecipe;
use App\Actions\Recipes\RestoreRecipe;
use App\Models\Recipe;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recipe archive')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Recipe::class); }
    public function restore(int $recipeId, RestoreRecipe $restoreRecipe): void { $recipe = Recipe::query()->whereBelongsTo($this->user())->archived()->findOrFail($recipeId); $restoreRecipe->handle($this->user(), $recipe); unset($this->recipes); Flux::toast(variant: 'success', text: 'Recipe restored.'); }
    public function planDinner(int $recipeId, PlanArchivedRecipe $planDinner): void { $recipe = Recipe::query()->whereBelongsTo($this->user())->archived()->findOrFail($recipeId); $planDinner->handle($this->user(), $recipe, (string) $recipe->default_servings); Flux::toast(variant: 'success', text: 'Archived recipe added to your plan.'); }
    #[Computed] public function recipes(): Collection { return Recipe::query()->whereBelongsTo($this->user())->archived()->latest('archived_at')->get(); }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4"><div><flux:heading size="xl">Recipe archive</flux:heading><flux:text class="mt-1">Archived recipes remain available for future history snapshots.</flux:text></div><flux:button :href="route('recipes.index')" wire:navigate variant="ghost">Back to recipes</flux:button></div>
    <div class="grid gap-4 md:grid-cols-2">@forelse ($this->recipes as $recipe)<flux:card wire:key="archived-recipe-{{ $recipe->id }}" class="flex items-center justify-between gap-4"><div><flux:heading>{{ $recipe->name }}</flux:heading><flux:text>{{ $recipe->default_servings }} servings</flux:text></div><div class="flex gap-2"><flux:button wire:click="planDinner({{ $recipe->id }})" variant="primary">Plan dinner</flux:button><flux:button wire:click="restore({{ $recipe->id }})">Restore</flux:button></div></flux:card>@empty<flux:callout icon="archive-box">No archived recipes.</flux:callout>@endforelse</div>
</section>
