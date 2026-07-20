<?php

use App\Actions\Pantry\AddPantryStock;
use App\Livewire\Forms\PantryEntryForm;
use App\Models\Ingredient;
use App\Models\PantryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add pantry stock')] class extends Component {
    public PantryEntryForm $form;

    public function mount(): void { Gate::authorize('create', PantryEntry::class); }
    public function updatedFormIngredientId(): void { $this->form->unit = ''; $this->form->ingredient_package_id = null; }
    public function updatedFormUnit(): void { if (filled($this->form->unit)) { $this->form->ingredient_package_id = null; } }
    public function updatedFormIngredientPackageId(): void { if ($this->form->ingredient_package_id !== null) { $this->form->unit = ''; } }

    public function save(AddPantryStock $addPantryStock): void
    {
        $addPantryStock->handle($this->user(), $this->form->validated($this->user()));
        session()->flash('status', 'Pantry stock added.');
        $this->redirectRoute('pantry.index', navigate: true);
    }

    /** @return Collection<int, Ingredient> */
    #[Computed]
    public function ingredients(): Collection { return Ingredient::query()->whereBelongsTo($this->user())->active()->with('packages')->orderBy('name')->get(); }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div><flux:heading size="xl">Add pantry stock</flux:heading><flux:text class="mt-1">Add a direct quantity or count packages from your ingredient catalogue.</flux:text></div>
    <x-pantry.form :form="$form" :ingredients="$this->ingredients" submit-label="Add stock" />
</section>
