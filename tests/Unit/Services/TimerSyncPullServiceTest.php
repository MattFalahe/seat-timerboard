<?php

namespace Raikia\SeatTimerboard\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use Raikia\SeatTimerboard\Jobs\ProcessTimerSyncPullPeer;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;
use Raikia\SeatTimerboard\Services\TimerSyncPullService;
use Raikia\SeatTimerboard\Tests\TestCase;

class TimerSyncPullServiceTest extends TestCase
{
    public function test_it_queues_one_pull_job_per_enabled_aauth_peer_with_pull_enabled(): void
    {
        Queue::fake();

        $eligiblePeer = $this->createSyncPeer([
            'id' => 1,
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
            'pull_remote_timers' => true,
        ]);
        $disabledPeer = $this->createSyncPeer([
            'instance_uuid' => '22222222-2222-2222-2222-222222222222',
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
            'pull_remote_timers' => true,
            'is_enabled' => false,
        ]);
        $seatPeer = $this->createSyncPeer([
            'instance_uuid' => '33333333-3333-3333-3333-333333333333',
            'peer_type' => TimerSyncPeer::TYPE_SEAT,
            'pull_remote_timers' => true,
        ]);
        $nonPullPeer = $this->createSyncPeer([
            'instance_uuid' => '44444444-4444-4444-4444-444444444444',
            'peer_type' => TimerSyncPeer::TYPE_AAUTH,
            'pull_remote_timers' => false,
        ]);

        $count = app(TimerSyncPullService::class)->queueEnabledPeers();

        $this->assertSame(1, $count);

        Queue::assertPushed(ProcessTimerSyncPullPeer::class, function ($job) use ($eligiblePeer) {
            return $this->readPrivateProperty($job, 'peerId') === $eligiblePeer->id;
        });
        Queue::assertNotPushed(ProcessTimerSyncPullPeer::class, function ($job) use ($disabledPeer, $seatPeer, $nonPullPeer) {
            $peerId = $this->readPrivateProperty($job, 'peerId');

            return in_array($peerId, [$disabledPeer->id, $seatPeer->id, $nonPullPeer->id], true);
        });
    }
}
