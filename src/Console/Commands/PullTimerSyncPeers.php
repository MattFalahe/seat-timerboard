<?php

namespace Raikia\SeatTimerboard\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatTimerboard\Services\TimerSyncPullService;

class PullTimerSyncPeers extends Command
{
    protected $signature = 'timerboard:sync-pull {--peer-id=}';

    protected $description = 'Pull remote timers from enabled AAuth sync peers.';

    public function handle(TimerSyncPullService $pullService): int
    {
        $peerId = $this->option('peer-id');

        $queued = $pullService->queueEnabledPeers($peerId !== null ? (int) $peerId : null);

        $this->info(sprintf(
            'Timerboard sync pull queued for %d peer%s.',
            $queued,
            $queued === 1 ? '' : 's'
        ));

        return self::SUCCESS;
    }
}
