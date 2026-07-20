<?php

use App\Actions\Ingredients\ArchiveIngredient;
use App\Models\Ingredient;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ingredients')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void { Gate::authorize('viewAny', Ingredient::class); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function archive(int $ingredientId, ArchiveIngredient $archiveIngredient): void
    {
        $ingredient = Ingredient::query()->whereBelongsTo($this->user())->findOrFail($ingredientId);
        $archiveIngredient->handle($this->user(), $ingredient);
        unset($this->ingredients);
        Flux::toast(variant: 'success', text: 'Ingredient archived.');
    }

    #[Computed]
    public function ingredients(): LengthAwarePaginator
    {
        return Ingredient::query()->whereBelongsTo($this->user())->active()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhereHas('aliases', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))))
            ->withCount(['aliases', 'packages'])->latest()->paginate(15);
    }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><flux:heading size="xl">Ingredients</flux:heading><flux:text class="mt-1">Your reusable ingredient and measurement catalogue.</flux:text></div>
        <div class="flex gap-2"><flux:button :href="route('ingredients.archive')" wire:navigate variant="ghost">Archive</flux:button><flux:button :href="route('ingredients.create')" wire:navigate variant="primary" icon="plus">New ingredient</flux:button></div>
    </div>
    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search ingredients or aliases" icon="magnifying-glass" clearable />
    <flux:card class="overflow-hidden p-0!">
        <flux:table>
            <flux:table.columns><flux:table.column>Name</flux:table.column><flux:table.column>Preferred measurement</flux:table.column><flux:table.column>Catalogue details</flux:table.column><flux:table.column></flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($this->ingredients as $ingredient)
                    <flux:table.row :key="$ingredient->id">
                        <flux:table.cell><div class="font-medium">{{ $ingredient->name }}</div><div class="text-sm text-zinc-500">{{ $ingredient->category ?: 'Uncategorised' }}</div></flux:table.cell>
                        <flux:table.cell>{{ $ingredient->preferred_unit->label() }} ({{ $ingredient->preferred_unit->value }})</flux:table.cell>
                        <flux:table.cell>{{ $ingredient->aliases_count }} aliases · {{ $ingredient->packages_count }} packages @if ($ingredient->is_staple)<flux:badge size="sm" class="ml-2">Staple</flux:badge>@endif</flux:table.cell>
                        <flux:table.cell><div class="flex justify-end gap-2"><flux:button :href="route('ingredients.edit', $ingredient)" wire:navigate size="sm" variant="ghost">Edit</flux:button><flux:button wire:click="archive({{ $ingredient->id }})" wire:confirm="Archive this ingredient? Existing recipe lines will keep their reference." size="sm" variant="ghost">Archive</flux:button></div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4"><flux:text class="py-8 text-center">No ingredients found.</flux:text></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
    {{ $this->ingredients->links() }}
</section>
