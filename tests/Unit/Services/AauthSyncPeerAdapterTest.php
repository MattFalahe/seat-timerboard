<?php

namespace Raikia\SeatTimerboard\Tests\Unit\Services;

use Raikia\SeatTimerboard\Models\TimerSyncPeer;
use Raikia\SeatTimerboard\Services\AauthSyncPeerAdapter;
use Raikia\SeatTimerboard\Services\TimerSyncMarker;
use Raikia\SeatTimerboard\Services\TimerboardInstanceIdentity;
use Raikia\SeatTimerboard\Tests\TestCase;

class AauthSyncPeerAdapterTest extends TestCase
{
    public function test_it_maps_a_remote_aauth_timer_into_local_timer_payload(): void
    {
        $peer = $this->createSyncPeer([
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
            'instance_uuid' => '22222222-2222-2222-2222-222222222222',
        ]);

        $mapped = app(AauthSyncPeerAdapter::class)->mapRemoteTimer($peer, [
            'timestamp' => now()->addHour()->timestamp,
            'structure' => [
                'name' => 'Friendly Fort',
                'type' => 'Fortizar',
            ],
            'owner' => 'Friendly Corp',
            'stance' => 'FR',
            'type' => 'AN',
            'system' => 'Jita',
        ]);

        $this->assertNotNull($mapped);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $mapped['origin_instance_uuid']);
        $this->assertNotEmpty($mapped['origin_timer_id']);
        $this->assertSame('Jita', $mapped['timer']['system']);
        $this->assertSame('Fortizar', $mapped['timer']['structure_type']);
        $this->assertEqualsCanonicalizing(
            ['Friendly', 'Anchoring'],
            collect($mapped['timer']['tags'])->pluck('name')->all()
        );
    }

    public function test_it_ignores_aauth_timers_that_originated_from_this_seat_instance(): void
    {
        $peer = $this->createSyncPeer([
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
        ]);
        $marker = app(TimerSyncMarker::class)->append('Bring logi', [
            'source_platform' => 'seat',
            'source_instance_uuid' => app(TimerboardInstanceIdentity::class)->getUuid(),
            'source_instance_name' => 'Local SeAT',
            'origin_timer_id' => 99,
        ]);

        $mapped = app(AauthSyncPeerAdapter::class)->mapRemoteTimer($peer, [
            'id' => 99,
            'timestamp' => now()->addHour()->timestamp,
            'details_notes' => $marker,
            'owner_name' => 'Friendly Corp',
            'eve_solar_system' => ['name' => 'Jita'],
            'structure_type' => ['name' => 'Fortizar'],
        ]);

        $this->assertNull($mapped);
    }

    public function test_it_skips_pulled_aauth_timers_that_match_a_local_timer_already_sent_to_that_peer(): void
    {
        $peer = $this->createSyncPeer([
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
            'instance_uuid' => '22222222-2222-2222-2222-222222222222',
        ]);

        $timer = $this->createTimerWithTags(['Friendly'], [
            'system' => 'Jita',
            'structure_type' => 'Fortizar',
            'structure_name' => 'Shared Fort',
            'owner_corporation' => 'Friendly Corp',
            'eve_time' => now()->addHour()->startOfMinute(),
        ]);
        $timer->syncDeliveries()->create([
            'peer_id' => $peer->id,
            'remote_timer_id' => '17',
        ]);

        $mapped = app(AauthSyncPeerAdapter::class)->mapRemoteTimer($peer, [
            'timestamp' => $timer->eve_time->timestamp,
            'structure' => [
                'name' => 'Shared Fort',
                'type' => 'Fortizar',
            ],
            'owner' => 'Friendly Corp',
            'stance' => 'FR',
            'type' => 'NO',
            'system' => 'Jita',
        ]);

        $this->assertNull($mapped);
    }
}
