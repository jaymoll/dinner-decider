<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_application_uses_utc_storage_and_an_english_interface(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('en', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_dinner_decider_presentation_conventions_are_configured(): void
    {
        $this->assertSame('Europe/Amsterdam', config('dinner-decider.display_timezone'));
        $this->assertSame('d-m-Y', config('dinner-decider.date_format'));
        $this->assertSame('H:i', config('dinner-decider.time_format'));
        $this->assertSame(1, config('dinner-decider.week_starts_on'));
        $this->assertSame('metric', config('dinner-decider.measurement_system'));
        $this->assertSame([',', '.'], config('dinner-decider.decimal_input_separators'));
    }

    public function test_queue_jobs_execute_synchronously(): void
    {
        $this->assertSame('sync', config('queue.default'));
    }
}
