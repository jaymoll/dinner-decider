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
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
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
        return PlannedDinner::query()->whereBelongsTo($this->plan)->history()->latest('updated_at')->paginate(10);
    }

    private function ownedDinner(int $id): PlannedDinner
    {
        return PlannedDinner::query()->whereBelongsTo($this->plan)->findOrFail($id);
    }

    private function refreshPlan(string $message): void
    {
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
                <div class="flex items-start gap-3">
                    <button type="button" wire:sort:handle class="mt-1 cursor-grab text-zinc-400" aria-label="Reorder {{ $dinner->recipe_name }}">&#8942;&#8942;</button>
                    <div class="min-w-0 flex-1"><flux:heading size="lg">{{ $dinner->recipe_name }}</flux:heading><flux:text>{{ $dinner->planned_date?->format('d-m-Y') ?? 'No date' }}</flux:text></div>
                    <div wire:sort:ignore class="flex flex-wrap justify-end gap-2"><flux:button wire:click="duplicate({{ $dinner->id }})" size="sm" variant="ghost">Duplicate</flux:button><flux:button wire:click="cancel({{ $dinner->id }})" size="sm" variant="ghost">Cancel</flux:button><flux:button wire:click="remove({{ $dinner->id }})" wire:confirm="Permanently remove this planned dinner?" size="sm" variant="ghost">Remove</flux:button><flux:button wire:click="cook({{ $dinner->id }})" size="sm" variant="primary">Cook</flux:button></div>
                </div>
                <div wire:sort:ignore class="grid gap-3 sm:grid-cols-2">
                    <form wire:submit="updateServings({{ $dinner->id }})" class="flex items-end gap-2"><flux:input wire:model="servings.{{ $dinner->id }}" label="Servings" inputmode="decimal" /><flux:button type="submit">Update</flux:button></form>
                    <form wire:submit="updateDate({{ $dinner->id }})" class="space-y-2"><flux:label>Date</flux:label><div class="flex items-center gap-2"><div class="flex-1"><x-dinner-date-picker model="dates.{{ $dinner->id }}" /></div><flux:button type="submit">Update</flux:button></div></form>
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
        @forelse ($this->history as $dinner)
            <flux:card wire:key="history-dinner-{{ $dinner->id }}" class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><flux:heading>{{ $dinner->recipe_name }}</flux:heading><flux:text>{{ str($dinner->status->value)->headline() }} · {{ ($dinner->cooked_at ?? $dinner->cancelled_at)?->format('d-m-Y H:i') }}</flux:text></div><div class="flex gap-2">@if ($dinner->status->value === 'cancelled')<flux:button wire:click="restore({{ $dinner->id }})" variant="ghost">Restore</flux:button>@endif<flux:button wire:click="planAgain({{ $dinner->id }})" variant="primary">Plan again</flux:button></div></flux:card>
        @empty
            <flux:text>No cooked or cancelled dinners yet.</flux:text>
        @endforelse
        {{ $this->history->links() }}
    </div>

    <flux:modal name="confirm-cooking" class="min-w-[24rem]">
        <div class="space-y-5"><div><flux:heading size="lg">Cook with unresolved requirements?</flux:heading><flux:text class="mt-2">Only reserved stock will be deducted. These unresolved items will be recorded in dinner history.</flux:text></div><div class="space-y-2">@foreach ($unresolved as $item)<div wire:key="unresolved-{{ $item['requirement_id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"><span class="font-medium">{{ $item['ingredient'] }}</span><div class="text-sm text-zinc-500">{{ str($item['coverage'])->headline() }}@if ($item['missing_amount'] !== null) · missing {{ $item['missing_amount'] }}@endif</div></div>@endforeach</div><div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Go back</flux:button></flux:modal.close><flux:button wire:click="confirmCooking" variant="danger">Cook anyway</flux:button></div></div>
    </flux:modal>
</section>
