<?php

namespace Raikia\SeatTimerboard\Services;

use Raikia\SeatTimerboard\Models\Timer;
use Raikia\SeatTimerboard\Models\TimerSyncDelivery;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;

interface SyncPeerAdapterInterface
{
    public function pushTimer(TimerSyncPeer $peer, Timer $timer, ?TimerSyncDelivery $delivery = null): ?string;

    public function deleteTimer(
        TimerSyncPeer $peer,
        TimerSyncDelivery $delivery,
        int $localTimerId,
        string $sourceInstanceUuid
    ): bool;

    public function supportsPull(TimerSyncPeer $peer): bool;

    public function pullTimers(TimerSyncPeer $peer): array;

    public function mapRemoteTimer(TimerSyncPeer $peer, array $record): ?array;
}
