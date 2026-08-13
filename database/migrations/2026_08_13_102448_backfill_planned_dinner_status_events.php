<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('planned_dinners')->orderBy('id')->chunkById(200, function ($dinners): void {
            $events = [];

            foreach ($dinners as $dinner) {
                $events[] = $this->event($dinner->id, 'planned', null, 'planned', $dinner->created_at);

                if ($dinner->restored_at !== null) {
                    $events[] = $this->event($dinner->id, 'restored', 'cancelled', 'planned', $dinner->restored_at);
                }

                if ($dinner->status === 'cooked' && $dinner->cooked_at !== null) {
                    $events[] = $this->event($dinner->id, 'cooked', 'planned', 'cooked', $dinner->cooked_at);
                }

                // Historical cancellation evidence without a timestamp cannot be placed honestly
                // on a chronology, so leave it absent rather than inventing an occurrence time.
                if ($dinner->status === 'cancelled' && $dinner->cancelled_at !== null) {
                    $events[] = $this->event($dinner->id, 'cancelled', 'planned', 'cancelled', $dinner->cancelled_at);
                }
            }

            DB::table('planned_dinner_status_events')->insert($events);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('planned_dinner_status_events')->where('is_reconstructed', true)->delete();
    }

    /** @return array<string, mixed> */
    private function event(int $dinnerId, string $type, ?string $from, string $to, string $occurredAt): array
    {
        return [
            'planned_dinner_id' => $dinnerId,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'occurred_at' => $occurredAt,
            'actor_user_id' => null,
            'is_reconstructed' => true,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ];
    }
};
