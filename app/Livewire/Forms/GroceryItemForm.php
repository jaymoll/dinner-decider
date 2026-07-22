<?php

namespace App\Livewire\Forms;

use App\Enums\GroceryCategory;
use App\Models\GroceryItem;
use Illuminate\Validation\Rule;
use Livewire\Form;

class GroceryItemForm extends Form
{
    public string $name = '';

    public string $quantityDescription = '';

    public string $category = 'other';

    public function setItem(GroceryItem $item): void
    {
        $this->name = $item->name;
        $this->quantityDescription = $item->quantity_description ?? '';
        $this->category = $item->category->value;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'quantityDescription' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(GroceryCategory::class)],
        ];
    }

    /** @return array{name: string, quantity_description: string|null, category: string} */
    public function payload(): array
    {
        $this->validate();

        return [
            'name' => $this->name,
            'quantity_description' => filled($this->quantityDescription) ? $this->quantityDescription : null,
            'category' => $this->category,
        ];
    }
}
