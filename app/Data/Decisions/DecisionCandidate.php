<?php

namespace App\Data\Decisions;

use App\Data\Recommendations\RecommendationResult;
use Carbon\CarbonImmutable;

final readonly class DecisionCandidate
{
    /**
     * @param  list<string>  $explanations
     */
    public function __construct(
        public RecommendationResult $recommendation,
        public ?CarbonImmutable $lastCookedAt,
        public string $seededOrderKey,
        public array $explanations,
    ) {}
}
