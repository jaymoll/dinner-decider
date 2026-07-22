<?php

namespace App\Services\Groceries;

use App\Data\Groceries\GroceryCalculationItem;
use App\Data\Groceries\GroceryContributionData;
use App\Data\Groceries\GroceryRequirementData;
use App\Enums\GroceryCategory;
use App\Enums\NonExactStatus;
use App\Enums\QuantityType;
use App\Enums\RequirementCoverage;
use App\Enums\UnitCode;
use Illuminate\Support\Str;

final class GroceryCalculator
{
    public function __construct(private readonly int $calculationScale = 6) {}

    /**
     * @param  iterable<GroceryRequirementData>  $requirements
     * @return list<GroceryCalculationItem>
     */
    public function calculate(iterable $requirements): array
    {
        /** @var array<string, array{ingredient_id: int, name: string, category: GroceryCategory, amount: numeric-string|null, unit: UnitCode|null, description: string|null, package_id: int|null, package_label: string|null, contributions: list<GroceryContributionData>}> $groups */
        $groups = [];

        foreach ($requirements as $requirement) {
            $canonical = $this->canonicalGroup($requirement);
            if ($canonical === null) {
                continue;
            }

            $key = hash('sha256', 'grocery:v1|'.$canonical);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'ingredient_id' => $requirement->ingredientId,
                    'name' => $requirement->ingredientName,
                    'category' => GroceryCategory::fromIngredientCategory($requirement->ingredientCategory),
                    'amount' => $requirement->quantityType === QuantityType::Exact ? '0' : null,
                    'unit' => $this->unit($requirement->compatibilityKey),
                    'description' => $requirement->quantityType === QuantityType::NonExact ? $this->description($requirement->quantityDescription) : null,
                    'package_id' => $requirement->ingredientPackageId,
                    'package_label' => $requirement->packageLabel,
                    'contributions' => [],
                ];
            }

            if ($groups[$key]['package_id'] !== $requirement->ingredientPackageId) {
                $groups[$key]['package_id'] = null;
                $groups[$key]['package_label'] = null;
            }

            if ($requirement->quantityType === QuantityType::Exact) {
                $amount = $requirement->missingAmount ?? '0';
                $currentAmount = $groups[$key]['amount'];
                if ($currentAmount === null) {
                    throw new \LogicException('An exact grocery group must contain an amount.');
                }
                $groups[$key]['amount'] = bcadd($currentAmount, $amount, $this->scale());
                $groups[$key]['contributions'][] = new GroceryContributionData($requirement->requirementId, $amount);
            } else {
                $groups[$key]['contributions'][] = new GroceryContributionData($requirement->requirementId, null);
            }
        }

        ksort($groups, SORT_STRING);

        return array_map(
            fn (array $group, string $key): GroceryCalculationItem => new GroceryCalculationItem(
                generationKey: $key,
                ingredientId: $group['ingredient_id'],
                name: $group['name'],
                category: $group['category'],
                calculatedAmount: $group['amount'],
                calculatedUnit: $group['unit'],
                quantityDescription: $group['description'],
                ingredientPackageId: $group['package_id'],
                packageLabel: $group['package_label'],
                contributions: $group['contributions'],
            ),
            $groups,
            array_keys($groups),
        );
    }

    private function canonicalGroup(GroceryRequirementData $requirement): ?string
    {
        if ($requirement->quantityType === QuantityType::Exact) {
            if ($requirement->compatibilityKey === null || $requirement->missingAmount === null
                || bccomp($requirement->missingAmount, '0', $this->scale()) <= 0) {
                return null;
            }

            return "exact|{$requirement->ingredientId}|{$requirement->compatibilityKey}";
        }

        if ($requirement->nonExactStatus !== NonExactStatus::Required
            || $requirement->coverage !== RequirementCoverage::Unavailable) {
            return null;
        }

        return "required|{$requirement->ingredientId}|".Str::lower($this->description($requirement->quantityDescription));
    }

    private function description(?string $description): string
    {
        return Str::of($description ?? 'Required')->trim()->squish()->toString();
    }

    private function unit(?string $compatibilityKey): ?UnitCode
    {
        return match (true) {
            $compatibilityKey === 'mass' => UnitCode::Gram,
            $compatibilityKey === 'volume' => UnitCode::Millilitre,
            str_starts_with((string) $compatibilityKey, 'count:') => UnitCode::tryFrom((string) Str::afterLast((string) $compatibilityKey, ':')),
            default => null,
        };
    }

    private function scale(): int
    {
        return $this->calculationScale;
    }
}
