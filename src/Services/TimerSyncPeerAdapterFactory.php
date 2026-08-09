<?php

namespace Raikia\SeatTimerboard\Services;

use Raikia\SeatTimerboard\Models\TimerSyncPeer;

class TimerSyncPeerAdapterFactory
{
    public function __construct(
        private SeatSyncPeerAdapter $seatAdapter,
        private AauthSyncPeerAdapter $aauthAdapter
    ) {
    }

    public function forPeer(TimerSyncPeer $peer): SyncPeerAdapterInterface
    {
        return $peer->isAauthPeer() ? $this->aauthAdapter : $this->seatAdapter;
    }
}
