<?php

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\Groceries\AddManualGroceryItem;
use App\Actions\Groceries\ClearCompletedGroceries;
use App\Actions\Groceries\EditGeneratedGroceryQuantity;
use App\Actions\Groceries\EnsureGroceryList;
use App\Actions\Groceries\RemoveManualGroceryItem;
use App\Actions\Groceries\RegenerateGroceryList;
use App\Actions\Groceries\ToggleGroceryItemChecked;
use App\Actions\Groceries\UpdateManualGroceryItem;
use App\Enums\GroceryCategory;
use App\Enums\GroceryItemSource;
use App\Livewire\Forms\GroceryItemForm;
use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\User;
use App\Services\Measurements\QuantityFormatter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Groceries')] class extends Component {
    public GroceryList $list;
    public GroceryItemForm $form;
    public ?int $editingItemId = null;
    public ?int $overrideItemId = null;
    public string $overrideAmount = '';

    public function mount(EnsureDinnerPlan $ensureDinnerPlan, EnsureGroceryList $ensureGroceryList, RegenerateGroceryList $regenerate): void
    {
        Gate::authorize('viewAny', GroceryList::class);
        $plan = $ensureDinnerPlan->handle($this->user());
        $this->list = $ensureGroceryList->handle($plan);

        // Reconciliation normally maintains this projection; first access repairs a legacy or empty
        // list without regenerating on every component mount.
        if ($this->list->regenerated_at === null) {
            $regenerate->handle($plan);
            $this->list->refresh();
        }
    }

    public function openAdd(): void
    {
        $this->editingItemId = null;
        $this->form->reset();
        Flux::modal('manual-grocery')->show();
    }

    public function openEdit(int $id): void
    {
        $item = $this->ownedItem($id);
        abort_unless($item->source === GroceryItemSource::Manual, 422);
        $this->editingItemId = $id;
        $this->form->setItem($item);
        Flux::modal('manual-grocery')->show();
    }

    public function save(AddManualGroceryItem $add, UpdateManualGroceryItem $update): void
    {
        $data = $this->form->payload();
        if ($this->editingItemId === null) {
            $add->handle($this->user(), $this->list, $data);
        } else {
            $update->handle($this->user(), $this->ownedItem($this->editingItemId), $data);
        }
        Flux::modals()->close();
        $this->refreshItems('Grocery item saved.');
    }

    public function remove(int $id, RemoveManualGroceryItem $remove): void
    {
        $remove->handle($this->user(), $this->ownedItem($id));
        $this->refreshItems('Manual item removed.');
    }

    public function toggle(int $id, ToggleGroceryItemChecked $toggle): void
    {
        $toggle->handle($this->user(), $this->ownedItem($id));
        unset($this->items);
    }

    public function clearCompleted(ClearCompletedGroceries $clear): void
    {
        $count = $clear->handle($this->user(), $this->list);
        $this->refreshItems("{$count} completed item(s) cleared.");
    }

    public function openOverride(int $id): void
    {
        $item = $this->ownedItem($id);
        abort_unless($item->source === GroceryItemSource::Generated && $item->calculated_amount !== null, 422);
        $this->overrideItemId = $id;
        $this->overrideAmount = $item->override_amount ?? $item->calculated_amount;
        Flux::modal('generated-override')->show();
    }

    public function saveOverride(EditGeneratedGroceryQuantity $edit): void
    {
        $this->validate(['overrideAmount' => ['required', 'decimal:0,6', 'gt:0']]);
        abort_if($this->overrideItemId === null, 422);
        $item = $this->ownedItem($this->overrideItemId);
        $edit->handle($this->user(), $item, $this->overrideAmount, $item->calculated_unit);
        Flux::modals()->close();
        $this->refreshItems('Temporary quantity override saved.');
    }

    #[Computed]
    public function items(): Collection
    {
        return GroceryItem::query()->whereBelongsTo($this->list)
            ->with(['contributions.requirement.plannedDinner:id,recipe_name,planned_date'])
            ->orderBy('category')->orderByRaw('checked_at IS NOT NULL')->orderBy('name')->oldest('id')->get();
    }

    public function displayQuantity(GroceryItem $item): string
    {
        // A temporary override affects presentation only; calculated truth remains available for the
        // next regeneration and for contribution explanations.
        $amount = $item->is_manually_adjusted ? $item->override_amount : $item->calculated_amount;
        $unit = $item->is_manually_adjusted ? $item->override_unit : $item->calculated_unit;
        if ($amount !== null) {
            $formatted = app(QuantityFormatter::class)->formatAmount($amount, $unit?->measurementGroup()->value === 'count');
            return trim($formatted.' '.($unit?->value ?? $item->package_label ?? 'packages'));
        }

        return $item->quantity_description ?? 'No quantity';
    }

    /** @return list<GroceryCategory> */
    public function visibleCategories(): array
    {
        $values = $this->items->pluck('category')->unique();
        return array_values(array_filter(GroceryCategory::cases(), fn (GroceryCategory $category): bool => $values->contains($category)));
    }

    private function ownedItem(int $id): GroceryItem
    {
        return GroceryItem::query()->whereBelongsTo($this->list)->findOrFail($id);
    }

    private function refreshItems(string $message): void
    {
        unset($this->items);
        $this->form->reset();
        $this->editingItemId = null;
        $this->overrideItemId = null;
        Flux::toast(variant: 'success', text: $message);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        return $user;
    }
}; ?>

<section class="w-full space-y-8">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><flux:heading size="xl">Groceries</flux:heading><flux:text class="mt-1">A live checklist of dinner shortfalls plus your manual items.</flux:text></div>
        <div class="flex gap-2"><flux:button wire:click="clearCompleted" wire:loading.attr="disabled" variant="ghost">Clear completed</flux:button><flux:button wire:click="openAdd" variant="primary">Add item</flux:button></div>
    </div>

    <div wire:loading.delay class="w-full"><flux:skeleton class="h-16 w-full" /></div>

    <div wire:loading.remove class="space-y-7">
        @forelse ($this->visibleCategories() as $category)
            <section wire:key="category-{{ $category->value }}" class="space-y-3">
                <flux:heading size="lg">{{ $category->label() }}</flux:heading>
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                    @foreach ($this->items->where('category', $category) as $item)
                        <article wire:key="grocery-item-{{ $item->id }}" class="flex items-start gap-3 p-4 {{ $item->checked_at ? 'opacity-60' : '' }}">
                            <flux:checkbox wire:click="toggle({{ $item->id }})" :checked="$item->checked_at !== null" :aria-label="'Check '.$item->name" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2"><span class="font-medium {{ $item->checked_at ? 'line-through' : '' }}">{{ $item->name }}</span><flux:badge size="sm">{{ $item->source->value }}</flux:badge>@if ($item->is_manually_adjusted)<flux:badge size="sm" color="amber">Temporary quantity</flux:badge>@endif</div>
                                <flux:text class="mt-1">{{ $this->displayQuantity($item) }}@if ($item->package_label && $item->calculated_unit) · {{ $item->package_label }} context @endif</flux:text>
                                @if ($item->quantity_increased_at && $item->previous_calculated_amount !== null)<flux:callout class="mt-2" variant="warning">Quantity changed from {{ $item->previous_calculated_amount }} to {{ $item->calculated_amount }} and was unchecked.</flux:callout>@endif
                                @if ($item->contributions->isNotEmpty())<details class="mt-2 text-sm text-zinc-500"><summary class="cursor-pointer">Needed for {{ $item->contributions->count() }} dinner requirement(s)</summary><ul class="mt-1 list-disc ps-5">@foreach ($item->contributions as $contribution)<li wire:key="contribution-{{ $contribution->id }}">{{ $contribution->requirement->plannedDinner->recipe_name }}@if ($contribution->normalized_amount !== null) · {{ $contribution->normalized_amount }}@endif</li>@endforeach</ul></details>@endif
                            </div>
                            <div class="flex gap-1">@if ($item->source === GroceryItemSource::Manual)<flux:button wire:click="openEdit({{ $item->id }})" size="sm" variant="ghost">Edit</flux:button><flux:button wire:click="remove({{ $item->id }})" wire:confirm="Remove this manual item?" size="sm" variant="ghost">Remove</flux:button>@elseif ($item->calculated_amount !== null)<flux:button wire:click="openOverride({{ $item->id }})" size="sm" variant="ghost">Quantity</flux:button>@endif</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <flux:callout>Your grocery list is empty. Plan a dinner with pantry shortfalls or add a manual item.</flux:callout>
        @endforelse
    </div>

    <flux:modal name="manual-grocery" class="md:w-96">
        <form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $editingItemId ? 'Edit manual item' : 'Add manual item' }}</flux:heading><flux:input wire:model="form.name" label="Item" /><flux:input wire:model="form.quantityDescription" label="Quantity or note" /><flux:select wire:model="form.category" label="Category">@foreach (GroceryCategory::cases() as $category)<flux:select.option value="{{ $category->value }}">{{ $category->label() }}</flux:select.option>@endforeach</flux:select><div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div></form>
    </flux:modal>

    <flux:modal name="generated-override" class="md:w-96">
        <form wire:submit="saveOverride" class="space-y-5"><div><flux:heading size="lg">Temporary quantity</flux:heading><flux:text class="mt-1">This display override clears when dinner or pantry data changes.</flux:text></div><flux:input wire:model="overrideAmount" label="Amount" inputmode="decimal" /><div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div></form>
    </flux:modal>
</section>
