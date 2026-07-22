<?php

namespace App\Actions\Groceries;

use App\Enums\GroceryCategory;
use App\Enums\GroceryItemSource;
use App\Models\GroceryItem;
use App\Models\GroceryList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AddManualGroceryItem
{
    /** @param array{name: string, quantity_description?: string|null, category: string} $data */
    public function handle(User $user, GroceryList $list, array $data): GroceryItem
    {
        Gate::forUser($user)->authorize('update', $list);
        $name = Str::of($data['name'])->trim()->squish()->toString();
        $description = filled($data['quantity_description'] ?? null)
            ? Str::of((string) $data['quantity_description'])->trim()->squish()->toString() : null;
        if ($name === '' || Str::length($name) > 160 || ($description !== null && Str::length($description) > 255)) {
            throw new InvalidArgumentException('The manual grocery item details are invalid.');
        }

        return GroceryItem::query()->create([
            'grocery_list_id' => $list->id,
            'source' => GroceryItemSource::Manual,
            'generation_key' => null,
            'name' => $name,
            'quantity_description' => $description,
            'category' => GroceryCategory::from($data['category']),
        ]);
    }
}
