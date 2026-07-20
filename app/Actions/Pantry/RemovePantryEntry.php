<?php

namespace App\Actions\Pantry;

use App\Models\PantryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RemovePantryEntry
{
    public function handle(User $user, PantryEntry $pantryEntry): void
    {
        Gate::forUser($user)->authorize('delete', $pantryEntry);

        DB::transaction(function () use ($pantryEntry): void {
            $lockedEntry = PantryEntry::query()->lockForUpdate()->findOrFail($pantryEntry->id);

            if (bccomp($this->reservedAmount($lockedEntry), '0', 6) > 0) {
                throw new InvalidArgumentException('Reserved pantry stock cannot be removed.');
            }

            $lockedEntry->delete();
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function reservedAmount(PantryEntry $pantryEntry): string
    {
        return '0';
    }
}
