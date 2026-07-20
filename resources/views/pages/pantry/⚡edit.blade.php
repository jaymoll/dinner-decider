<?php

use App\Actions\Pantry\UpdatePantryEntry;
use App\Livewire\Forms\PantryEntryForm;
use App\Models\PantryEntry;
use App\Models\User;
use App\Rules\PositiveDecimalQuantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit pantry entry')] class extends Component {
    public PantryEntry $pantryEntry;
    public PantryEntryForm $form;

    public function mount(PantryEntry $pantryEntry): void
    {
        Gate::authorize('update', $pantryEntry);
        $this->pantryEntry = $pantryEntry->load(['ingredient', 'ingredientPackage']);
        $this->form->setEntry($this->pantryEntry);
    }

    public function save(UpdatePantryEntry $updatePantryEntry): void
    {
        $this->validate(['form.amount' => ['required', new PositiveDecimalQuantity]]);
        $updatePantryEntry->handle($this->user(), $this->pantryEntry, $this->form->amount);
        session()->flash('status', 'Pantry total updated.');
        $this->redirectRoute('pantry.index', navigate: true);
    }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div><flux:heading size="xl">Edit {{ $pantryEntry->ingredient->name }}</flux:heading><flux:text class="mt-1">Set the exact total for this pantry row.</flux:text></div>
    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4"><flux:heading>{{ $pantryEntry->ingredientPackage?->label ?? $pantryEntry->display_unit?->label() }}</flux:heading><flux:input wire:model="form.amount" label="Total amount" inputmode="decimal" /><flux:error name="form.amount" /></flux:card>
        <div class="flex justify-end gap-2"><flux:button :href="route('pantry.index')" wire:navigate variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary">Save total</flux:button></div>
    </form>
</section>
