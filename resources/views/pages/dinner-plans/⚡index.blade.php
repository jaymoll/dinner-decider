<?php

use App\Actions\DinnerPlans\CancelDinner;
use App\Actions\DinnerPlans\ChangePlannedDinnerDate;
use App\Actions\DinnerPlans\ChangePlannedDinnerServings;
use App\Actions\DinnerPlans\DuplicatePlannedDinner;
use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\DinnerPlans\MarkDinnerCooked;
use App\Actions\DinnerPlans\PlanDinnerFromHistory;
use App\Actions\DinnerPlans\RemovePlannedDinner;
use App\Actions\DinnerPlans\ReorderPlannedDinner;
use App\Actions\DinnerPlans\RestoreCancelledDinner;
use App\Models\DinnerPlan;
use App\Models\PlannedDinner;
use App\Models\User;
use App\Rules\PositiveDecimalQuantity;
use App\Enums\PlannedDinnerStatus;
use App\Models\Recipe;
use App\Queries\GetDinnerHistory;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Dinner plan')] class extends Component {
    use WithPagination;

    public DinnerPlan $plan;
    /** @var array<int, string> */
    public array $servings = [];
    /** @var array<int, string> */
    public array $dates = [];
    public ?int $pendingCookDinnerId = null;
    public ?string $cookFingerprint = null;
    /** @var list<array<string, mixed>> */
    public array $unresolved = [];
    #[Url] public string $historyRecipe = '';
    #[Url] public string $historyStatus = '';
    #[Url] public string $historyFrom = '';
    #[Url] public string $historyTo = '';
    private GetDinnerHistory $historyQuery;

    public function boot(GetDinnerHistory $historyQuery): void
    {
        $this->historyQuery = $historyQuery;
    }

    public function updatedHistoryRecipe(): void { $this->resetPage('history-page'); unset($this->history); }
    public function updatedHistoryStatus(): void { $this->resetPage('history-page'); unset($this->history); }
    public function updatedHistoryFrom(): void { $this->resetPage('history-page'); unset($this->history); }
    public function updatedHistoryTo(): void { $this->resetPage('history-page'); unset($this->history); }

    public function mount(EnsureDinnerPlan $ensureDinnerPlan): void
    {
        Gate::authorize('viewAny', DinnerPlan::class);
        $this->plan = $ensureDinnerPlan->handle($this->user());
        $this->refreshInputs();
    }

    public function updateServings(int $id, ChangePlannedDinnerServings $change): void
    {
        $this->validate(["servings.{$id}" => ['required', new PositiveDecimalQuantity]]);
        $change->handle($this->user(), $this->ownedDinner($id), $this->servings[$id]);
        $this->refreshPlan('Servings updated.');
    }

    public function updateDate(int $id, ChangePlannedDinnerDate $change): void
    {
        $this->validate(["dates.{$id}" => ['nullable', 'date_format:Y-m-d']]);
        $change->handle($this->user(), $this->ownedDinner($id), $this->dates[$id] ?: null);
        $this->refreshPlan('Dinner date updated.');
    }

    public function sortDinner(int|string $id, int $position, ReorderPlannedDinner $reorder): void
    {
        $reorder->handle($this->user(), $this->ownedDinner((int) $id), $position + 1);
        $this->refreshPlan('Dinner order updated.');
    }

    public function moveUp(int $id, ReorderPlannedDinner $reorder): void
    {
        $dinner = $this->ownedDinner($id);
        $reorder->handle($this->user(), $dinner, $dinner->position - 1);
        $this->refreshPlan('Dinner moved up.');
    }

    public function moveDown(int $id, ReorderPlannedDinner $reorder): void
    {
        $dinner = $this->ownedDinner($id);
        $reorder->handle($this->user(), $dinner, $dinner->position + 1);
        $this->refreshPlan('Dinner moved down.');
    }

    public function duplicate(int $id, DuplicatePlannedDinner $duplicate): void
    {
        $duplicate->handle($this->user(), $this->ownedDinner($id));
        $this->refreshPlan('Dinner duplicated.');
    }

    public function cancel(int $id, CancelDinner $cancel): void
    {
        $cancel->handle($this->user(), $this->ownedDinner($id));
        $this->refreshPlan('Dinner cancelled.');
    }

    public function remove(int $id, RemovePlannedDinner $remove): void
    {
        $remove->handle($this->user(), $this->ownedDinner($id));
        $this->refreshPlan('Dinner removed.');
    }

    public function cook(int $id, MarkDinnerCooked $cook): void
    {
        $result = $cook->handle($this->user(), $this->ownedDinner($id));
        if ($result->requiresConfirmation) {
            // The fingerprint binds this modal to the freshly reconciled shortage set.
            $this->pendingCookDinnerId = $id;
            $this->cookFingerprint = $result->fingerprint;
            $this->unresolved = $result->unresolved;
            Flux::modal('confirm-cooking')->show();
            return;
        }
        $this->refreshPlan('Dinner marked as cooked.');
    }

    public function confirmCooking(MarkDinnerCooked $cook): void
    {
        abort_if($this->pendingCookDinnerId === null || $this->cookFingerprint === null, 422);
        $result = $cook->handle($this->user(), $this->ownedDinner($this->pendingCookDinnerId), $this->cookFingerprint);
        if ($result->requiresConfirmation) {
            // Supply or plan state changed while the modal was open; require the user to review the
            // replacement shortage set instead of accepting a stale confirmation.
            $this->cookFingerprint = $result->fingerprint;
            $this->unresolved = $result->unresolved;
            return;
        }
        Flux::modals()->close();
        $this->pendingCookDinnerId = null;
        $this->cookFingerprint = null;
        $this->unresolved = [];
        $this->refreshPlan('Dinner cooked; reserved pantry stock was deducted.');
    }

    public function restore(int $id, RestoreCancelledDinner $restore): void
    {
        $restore->handle($this->user(), $this->ownedDinner($id));
        $this->refreshPlan('Dinner restored against current pantry stock.');
    }

    public function planAgain(int $id, PlanDinnerFromHistory $planAgain): void
    {
        $planAgain->handle($this->user(), $this->ownedDinner($id));
        $this->refreshPlan('Dinner planned again.');
    }

    #[Computed]
    public function activeDinners()
    {
        return PlannedDinner::query()->whereBelongsTo($this->plan)->active()->priorityOrder()
            ->with(['requirements' => fn ($query) => $query->withSum('reservations', 'normalized_amount')])->get();
    }

    #[Computed]
    public function history(): LengthAwarePaginator
    {
        $status = PlannedDinnerStatus::tryFrom($this->historyStatus);
        if (! in_array($status, [PlannedDinnerStatus::Cooked, PlannedDinnerStatus::Cancelled], true)) {
            $status = null;
        }

        return $this->historyQuery->get(
            $this->user(),
            ctype_digit($this->historyRecipe) ? (int) $this->historyRecipe : null,
            $status,
            $this->historyFrom,
            $this->historyTo,
            page: $this->getPage('history-page'),
        );
    }

    #[Computed]
    public function historyRecipes()
    {
        return Recipe::query()->whereBelongsTo($this->user())->oldest('name')->get(['id', 'name']);
    }

    private function ownedDinner(int $id): PlannedDinner
    {
        return PlannedDinner::query()->whereBelongsTo($this->plan)->findOrFail($id);
    }

    private function refreshPlan(string $message): void
    {
        // Computed values are request-memoized, so mutations must invalidate both active and history.
        unset($this->activeDinners, $this->history);
        $this->refreshInputs();
        Flux::toast(variant: 'success', text: $message);
    }

    private function refreshInputs(): void
    {
        PlannedDinner::query()->whereBelongsTo($this->plan)->active()->get()->each(function (PlannedDinner $dinner): void {
            $this->servings[$dinner->id] = $dinner->servings;
            $this->dates[$dinner->id] = $dinner->planned_date?->format('Y-m-d') ?? '';
        });
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
        <div><flux:heading size="xl">Dinner plan</flux:heading><flux:text class="mt-1">Your rolling plan, reserved pantry coverage, and dinner history.</flux:text></div>
        <flux:button :href="route('recommendations.index')" wire:navigate variant="primary">Find a dinner</flux:button>
    </div>

    <div class="space-y-4" wire:sort="sortDinner">
        @forelse ($this->activeDinners as $dinner)
            <flux:card wire:key="planned-dinner-{{ $dinner->id }}" wire:sort:item="{{ $dinner->id }}" class="space-y-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <button type="button" wire:sort:handle class="mt-1 cursor-grab text-zinc-400" aria-label="Reorder {{ $dinner->recipe_name }}">&#8942;&#8942;</button>
                    <div class="min-w-0 flex-1"><flux:heading size="lg">{{ $dinner->recipe_name }}</flux:heading><flux:text>{{ $dinner->planned_date?->format('d-m-Y') ?? 'No date' }}</flux:text></div>
                    <div wire:sort:ignore class="flex flex-wrap gap-2 sm:justify-end"><flux:button wire:click="moveUp({{ $dinner->id }})" wire:loading.attr="disabled" size="sm" variant="ghost" icon="arrow-up" aria-label="Move {{ $dinner->recipe_name }} up" :disabled="$loop->first">Move up</flux:button><flux:button wire:click="moveDown({{ $dinner->id }})" wire:loading.attr="disabled" size="sm" variant="ghost" icon="arrow-down" aria-label="Move {{ $dinner->recipe_name }} down" :disabled="$loop->last">Move down</flux:button><flux:button wire:click="duplicate({{ $dinner->id }})" wire:loading.attr="disabled" size="sm" variant="ghost">Duplicate</flux:button><flux:button wire:click="cancel({{ $dinner->id }})" wire:loading.attr="disabled" size="sm" variant="ghost">Cancel</flux:button><flux:button wire:click="remove({{ $dinner->id }})" wire:loading.attr="disabled" wire:confirm="Permanently remove this planned dinner?" size="sm" variant="ghost">Remove</flux:button><flux:button wire:click="cook({{ $dinner->id }})" wire:loading.attr="disabled" size="sm" variant="primary">Cook</flux:button></div>
                </div>
                <div wire:sort:ignore class="grid gap-3 sm:grid-cols-2">
                    <form wire:submit="updateServings({{ $dinner->id }})" class="flex flex-col gap-2 min-[375px]:flex-row min-[375px]:items-end"><flux:input wire:model="servings.{{ $dinner->id }}" label="Servings" inputmode="decimal" /><flux:button type="submit" wire:loading.attr="disabled">Update</flux:button></form>
                    <form wire:submit="updateDate({{ $dinner->id }})" class="space-y-2"><flux:label>Date</flux:label><div class="flex flex-col gap-2 min-[375px]:flex-row min-[375px]:items-center"><div class="min-w-0 flex-1"><x-dinner-date-picker model="dates.{{ $dinner->id }}" /></div><flux:button type="submit" wire:loading.attr="disabled">Update</flux:button></div></form>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($dinner->requirements as $requirement)
                        <div wire:key="requirement-{{ $requirement->id }}" class="flex flex-col justify-between gap-2 py-3 sm:flex-row sm:items-center">
                            <div><span class="font-medium">{{ $requirement->ingredient_name }}</span>@if ($requirement->quantity_description)<span class="text-sm text-zinc-500"> · {{ $requirement->quantity_description }}</span>@endif</div>
                            <div class="flex items-center gap-2 text-sm"><flux:badge>{{ str($requirement->coverage->value)->headline() }}</flux:badge>@if ($requirement->scaled_amount !== null)<span>needed {{ $requirement->scaled_amount }} · reserved {{ $requirement->reservations_sum_normalized_amount ?? '0' }} · missing {{ $requirement->missing_amount ?? '0' }}</span>@endif</div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @empty
            <flux:callout>No dinners are planned yet. Start from recommendations or the recipe catalogue.</flux:callout>
        @endforelse
    </div>

    <div class="space-y-4">
        <flux:heading size="lg">History</flux:heading>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="historyRecipe" label="Recipe"><flux:select.option value="">All recipes</flux:select.option>@foreach ($this->historyRecipes as $recipe)<flux:select.option value="{{ $recipe->id }}">{{ $recipe->name }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model.live="historyStatus" label="Status"><flux:select.option value="">Cooked and cancelled</flux:select.option><flux:select.option value="cooked">Cooked</flux:select.option><flux:select.option value="cancelled">Cancelled</flux:select.option></flux:select>
            <flux:input wire:model.live="historyFrom" type="date" label="From date" />
            <flux:input wire:model.live="historyTo" type="date" label="To date" />
        </div>
        @forelse ($this->history as $dinner)
            <flux:card wire:key="history-dinner-{{ $dinner->id }}" class="space-y-4"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><flux:heading>{{ $dinner->recipe_name }}</flux:heading><flux:text>{{ str($dinner->status->value)->headline() }} · {{ ($dinner->cooked_at ?? $dinner->cancelled_at)?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }} · {{ $dinner->servings }} servings</flux:text></div><div class="flex gap-2">@if ($dinner->status->value === 'cancelled')<flux:button wire:click="restore({{ $dinner->id }})" variant="ghost">Restore</flux:button>@endif<flux:button wire:click="planAgain({{ $dinner->id }})" variant="primary">Plan again</flux:button></div></div><ol class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Lifecycle for {{ $dinner->recipe_name }}">@foreach ($dinner->statusEvents as $event)<li wire:key="history-event-{{ $event->id }}" class="text-sm text-zinc-600 dark:text-zinc-300"><span class="font-medium">{{ str($event->event_type->value)->headline() }}</span> {{ $event->occurred_at->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}@if ($event->is_reconstructed) <span class="text-zinc-500">(reconstructed)</span>@endif</li>@endforeach</ol></flux:card>
        @empty
            <flux:text>No cooked or cancelled dinners yet.</flux:text>
        @endforelse
        {{ $this->history->links() }}
    </div>

    <flux:modal name="confirm-cooking" class="w-full max-w-md">
        <div class="space-y-5"><div><flux:heading size="lg">Cook with unresolved requirements?</flux:heading><flux:text class="mt-2">Only reserved stock will be deducted. These unresolved items will be recorded in dinner history.</flux:text></div><div class="space-y-2">@foreach ($unresolved as $item)<div wire:key="unresolved-{{ $item['requirement_id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"><span class="font-medium">{{ $item['ingredient'] }}</span><div class="text-sm text-zinc-500">{{ str($item['coverage'])->headline() }}@if ($item['missing_amount'] !== null) · missing {{ $item['missing_amount'] }}@endif</div></div>@endforeach</div><div class="flex flex-col-reverse gap-2 min-[375px]:flex-row min-[375px]:justify-end"><flux:modal.close><flux:button variant="ghost">Go back</flux:button></flux:modal.close><flux:button wire:click="confirmCooking" wire:loading.attr="disabled" variant="danger">Cook anyway</flux:button></div></div>
    </flux:modal>
</section>
