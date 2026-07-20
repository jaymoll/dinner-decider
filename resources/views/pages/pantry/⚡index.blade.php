<?php

use App\Actions\Pantry\RemovePantryEntry;
use App\Actions\Pantry\UpdateIngredientPantryStatus;
use App\Data\Pantry\PantryBalance;
use App\Exceptions\PantryEntryRemovalRequiresConfirmation;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\User;
use App\Queries\AvailablePantry;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pantry')] class extends Component {
    use WithPagination;

    private AvailablePantry $availablePantry;
    public ?int $pendingRemovalId = null;
    /** @var list<array{dinner: string, date: string|null, amount: string}> */
    public array $pendingRemovalReservations = [];

    public function boot(AvailablePantry $availablePantry): void { $this->availablePantry = $availablePantry; }

    public function mount(): void { Gate::authorize('viewAny', PantryEntry::class); }

    public function remove(int $entryId, RemovePantryEntry $removePantryEntry): void
    {
        $entry = PantryEntry::query()->whereBelongsTo($this->user())->findOrFail($entryId);
        try {
            $removePantryEntry->handle($this->user(), $entry);
        } catch (PantryEntryRemovalRequiresConfirmation $exception) {
            $this->pendingRemovalId = $entryId;
            $this->pendingRemovalReservations = $exception->reservations;
            Flux::modal('confirm-pantry-removal')->show();
            return;
        }
        unset($this->balances);
        Flux::toast(variant: 'success', text: 'Pantry entry removed.');
    }

    public function confirmRemoval(RemovePantryEntry $removePantryEntry): void
    {
        abort_if($this->pendingRemovalId === null, 422);
        $entry = PantryEntry::query()->whereBelongsTo($this->user())->findOrFail($this->pendingRemovalId);
        $removePantryEntry->handle($this->user(), $entry, true);
        $this->pendingRemovalId = null;
        $this->pendingRemovalReservations = [];
        unset($this->balances);
        Flux::modals()->close();
        Flux::toast(variant: 'success', text: 'Pantry entry removed and dinners reallocated.');
    }

    public function toggleStaple(int $ingredientId, UpdateIngredientPantryStatus $updateStatus): void
    {
        $ingredient = Ingredient::query()->whereBelongsTo($this->user())->active()->findOrFail($ingredientId);
        $updateStatus->handle($this->user(), $ingredient, ! $ingredient->is_staple, $ingredient->is_currently_available);
        unset($this->balances);
    }

    public function toggleAvailability(int $ingredientId, UpdateIngredientPantryStatus $updateStatus): void
    {
        $ingredient = Ingredient::query()->whereBelongsTo($this->user())->active()->findOrFail($ingredientId);
        $updateStatus->handle($this->user(), $ingredient, $ingredient->is_staple, ! $ingredient->is_currently_available);
        unset($this->balances);
    }

    /** @return LengthAwarePaginator<int, PantryBalance> */
    #[Computed]
    public function balances(): LengthAwarePaginator
    {
        $balances = $this->availablePantry->get($this->user(), includeReservationDetails: true)->balances;
        $page = $this->getPage();

        return new LengthAwarePaginator($balances->forPage($page, 15)->values(), $balances->count(), 15, $page, ['path' => request()->url()]);
    }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><flux:heading size="xl">Pantry</flux:heading><flux:text class="mt-1">Current stock, reservation-ready balances, and availability controls.</flux:text></div>
        <flux:button :href="route('pantry.create')" wire:navigate variant="primary" icon="plus">Add stock</flux:button>
    </div>
    @if (session('status')) <flux:callout variant="success">{{ session('status') }}</flux:callout> @endif
    <flux:card class="overflow-hidden p-0!">
        <flux:table :paginate="$this->balances">
            <flux:table.columns><flux:table.column>Ingredient</flux:table.column><flux:table.column>Total</flux:table.column><flux:table.column>Reserved</flux:table.column><flux:table.column>Available</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column></flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($this->balances as $balance)
                    <flux:table.row :key="$balance->entry->id">
                        <flux:table.cell variant="strong"><div>{{ $balance->entry->ingredient->name }}</div><div class="text-sm font-normal text-zinc-500">{{ $balance->entry->ingredientPackage?->package_type->label() ?? $balance->entry->display_unit?->label() }}</div></flux:table.cell>
                        <flux:table.cell>{{ $balance->totalDisplay }}</flux:table.cell>
                        <flux:table.cell><div>{{ $balance->reservedDisplay }}</div>@if ($balance->entry->reservations->isNotEmpty())<details class="mt-2 text-xs"><summary class="cursor-pointer text-zinc-500">Reservation details</summary><div class="mt-2 space-y-1">@foreach ($balance->entry->reservations as $reservation)<div wire:key="pantry-reservation-{{ $reservation->id }}">{{ $reservation->requirement->plannedDinner->recipe_name }} · {{ $reservation->requirement->plannedDinner->planned_date?->format('d-m-Y') ?? 'No date' }} · {{ $reservation->normalized_amount }}</div>@endforeach</div></details>@endif</flux:table.cell>
                        <flux:table.cell>{{ $balance->availableDisplay }}</flux:table.cell>
                        <flux:table.cell><div class="flex flex-wrap gap-2">@if ($balance->entry->ingredient->is_staple)<flux:badge color="lime">Staple</flux:badge>@endif @if (! $balance->entry->ingredient->is_currently_available)<flux:badge color="amber">Temporarily unavailable</flux:badge>@endif</div></flux:table.cell>
                        <flux:table.cell><div class="flex flex-wrap justify-end gap-2"><flux:button wire:click="toggleStaple({{ $balance->entry->ingredient_id }})" size="sm" variant="ghost">Toggle staple</flux:button><flux:button wire:click="toggleAvailability({{ $balance->entry->ingredient_id }})" size="sm" variant="ghost">Toggle availability</flux:button><flux:button :href="route('pantry.edit', $balance->entry)" wire:navigate size="sm" variant="ghost">Edit</flux:button><flux:button wire:click="remove({{ $balance->entry->id }})" size="sm" variant="ghost">Remove</flux:button></div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6"><flux:text class="py-8 text-center">Your pantry is empty.</flux:text></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
    <flux:modal name="confirm-pantry-removal" class="min-w-[24rem]"><div class="space-y-5"><div><flux:heading size="lg">Remove reserved pantry stock?</flux:heading><flux:text class="mt-2">These dinner reservations will be released and reallocated to remaining compatible stock.</flux:text></div><div class="space-y-2">@foreach ($pendingRemovalReservations as $reservation)<div wire:key="pending-removal-{{ $loop->index }}" class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">{{ $reservation['dinner'] }} · {{ $reservation['date'] ? \Carbon\CarbonImmutable::parse($reservation['date'])->format('d-m-Y') : 'No date' }} · {{ $reservation['amount'] }}</div>@endforeach</div><div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Keep stock</flux:button></flux:modal.close><flux:button wire:click="confirmRemoval" variant="danger">Remove and reallocate</flux:button></div></div></flux:modal>
</section>
