<?php

use App\Actions\Ingredients\RestoreIngredient;
use App\Models\Ingredient;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ingredient archive')] class extends Component {
    public function mount(): void { Gate::authorize('viewAny', Ingredient::class); }

    public function restore(int $ingredientId, RestoreIngredient $restoreIngredient): void
    {
        $ingredient = Ingredient::query()->whereBelongsTo($this->user())->archived()->findOrFail($ingredientId);
        $restoreIngredient->handle($this->user(), $ingredient); unset($this->ingredients);
        Flux::toast(variant: 'success', text: 'Ingredient restored.');
    }

    #[Computed]
    public function ingredients(): Collection { return Ingredient::query()->whereBelongsTo($this->user())->archived()->latest('archived_at')->get(); }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4"><div><flux:heading size="xl">Ingredient archive</flux:heading><flux:text class="mt-1">Archived ingredients remain attached to existing recipes.</flux:text></div><flux:button :href="route('ingredients.index')" wire:navigate variant="ghost">Back to ingredients</flux:button></div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($this->ingredients as $ingredient)
            <flux:card wire:key="archived-ingredient-{{ $ingredient->id }}" class="flex items-center justify-between gap-4"><div><flux:heading>{{ $ingredient->name }}</flux:heading><flux:text>{{ $ingredient->preferred_unit->label() }}</flux:text></div><flux:button wire:click="restore({{ $ingredient->id }})" variant="primary">Restore</flux:button></flux:card>
        @empty
            <flux:callout icon="archive-box">No archived ingredients.</flux:callout>
        @endforelse
    </div>
</section>
