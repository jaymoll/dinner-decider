<?php

namespace Tests\Feature\Performance;

use App\Actions\DinnerPlans\EnsureDinnerPlan;
use App\Actions\Groceries\EnsureGroceryList;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReadPathPerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, int> */
    private const QUERY_CEILINGS = [
        'pages::pantry.index' => 12,
        'pages::dinner-plans.index' => 14,
        'pages::groceries.index' => 16,
    ];

    public function test_product_read_paths_remain_bounded_for_small_and_demo_fixtures(): void
    {
        $smallUser = User::factory()->create();
        $smallPlan = app(EnsureDinnerPlan::class)->handle($smallUser);
        app(EnsureGroceryList::class)->handle($smallPlan);

        $smallCounts = $this->readPathCounts($smallUser);

        $this->seed();
        $demoUser = User::query()->where('email', 'test@example.com')->sole();
        $demoCounts = $this->readPathCounts($demoUser);

        foreach (self::QUERY_CEILINGS as $component => $ceiling) {
            $this->assertLessThanOrEqual($ceiling, $smallCounts[$component], "Small {$component} query ceiling exceeded.");
            $this->assertLessThanOrEqual($ceiling, $demoCounts[$component], "Demo {$component} query ceiling exceeded.");
        }
    }

    /** @return array<string, int> */
    private function readPathCounts(User $user): array
    {
        $counts = [];

        foreach (array_keys(self::QUERY_CEILINGS) as $component) {
            DB::enableQueryLog();
            DB::flushQueryLog();

            Livewire::actingAs($user)->test($component)->assertOk();

            $counts[$component] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $counts;
    }
}
