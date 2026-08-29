<?php

namespace Raikia\SeatTimerboard\Tests\Unit\Http;

use Illuminate\Http\Request;
use Raikia\SeatTimerboard\Http\Controllers\TimerboardController;
use Raikia\SeatTimerboard\Tests\TestCase;

class TimerboardControllerTest extends TestCase
{
    public function test_search_systems_uses_prefix_matching_for_supported_location_groups(): void
    {
        $this->insertMapDenormalize([
            'itemID' => 30000142,
            'typeID' => 5,
            'groupID' => 5,
            'solarSystemID' => 30000142,
            'constellationID' => 20000020,
            'regionID' => 10000002,
            'itemName' => 'Jita',
        ]);
        $this->insertMapDenormalize([
            'itemID' => 30000143,
            'typeID' => 5,
            'groupID' => 5,
            'solarSystemID' => 30000143,
            'constellationID' => 20000020,
            'regionID' => 10000002,
            'itemName' => 'Jitami',
        ]);
        $this->insertMapDenormalize([
            'itemID' => 30000144,
            'typeID' => 5,
            'groupID' => 5,
            'solarSystemID' => 30000144,
            'constellationID' => 20000020,
            'regionID' => 10000002,
            'itemName' => 'New Jita',
        ]);
        $this->insertMapDenormalize([
            'itemID' => 30000145,
            'typeID' => 5,
            'groupID' => 6,
            'solarSystemID' => 30000145,
            'constellationID' => 20000020,
            'regionID' => 10000002,
            'itemName' => 'Jitara',
        ]);

        $response = app(TimerboardController::class)->searchSystems(new Request([
            'q' => 'Jit',
        ]));

        $results = $response->getData(true)['results'];

        $this->assertSame([
            ['id' => 'Jita', 'text' => 'Jita'],
            ['id' => 'Jitami', 'text' => 'Jitami'],
        ], $results);
    }
}
