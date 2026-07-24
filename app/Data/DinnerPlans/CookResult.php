<?php

namespace App\Data\DinnerPlans;

final readonly class CookResult
{
    /**
     * @param  array<int, array{requirement_id: int, ingredient: string, coverage: string, missing_amount: string|null, description: string|null}>  $unresolved
     */
    public function __construct(
        public bool $cooked,
        public bool $alreadyCooked = false,
        public bool $requiresConfirmation = false,
        public ?string $fingerprint = null,
        public array $unresolved = [],
    ) {}
}
