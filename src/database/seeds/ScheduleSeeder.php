<?php

namespace Raikia\SeatTimerboard\Database\Seeds;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            [
                'command' => 'timerboard:sync-pull',
                'expression' => '*/5 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
