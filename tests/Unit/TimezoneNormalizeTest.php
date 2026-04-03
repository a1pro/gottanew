<?php

namespace Tests\Unit;

use App\Support\Timezone;
use PHPUnit\Framework\TestCase;

class TimezoneNormalizeTest extends TestCase
{
    public function test_normalizes_known_aliases(): void
    {
        $this->assertSame('Asia/Kolkata', Timezone::normalize('Asia/Calcutta',
'UTC'));
           $this->assertSame('America/New_York', Timezone::normalize('US/Eastern',
'UTC'));
    }

    public function test_falls_back_on_invalid_timezones(): void
    {
        $this->assertSame('UTC', Timezone::normalize('Not/A_Timezone', 'UTC'));
        $this->assertSame('America/Los_Angeles', Timezone::normalize('',
'America/Los_Angeles'));
    }
}
