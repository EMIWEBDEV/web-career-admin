<?php

namespace Tests\Unit;

use Tests\TestCase;

class CareerDivisiGuardConfigTest extends TestCase
{
    public function test_default_config_value_is_enabled_true_when_env_unset(): void
    {
        $this->assertTrue(config('career_divisi_guard.enabled'));
    }

    public function test_config_reads_env_override(): void
    {
        config(['career_divisi_guard.enabled' => false]);

        $this->assertFalse(config('career_divisi_guard.enabled'));
    }
}
