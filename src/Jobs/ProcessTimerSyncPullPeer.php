<?php

namespace Raikia\SeatTimerboard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Raikia\SeatTimerboard\Services\TimerSyncPullService;

class ProcessTimerSyncPullPeer implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;
    public int $uniqueFor = 300;

    public function __construct(
        private int $peerId
    ) {
    }

    public function handle(TimerSyncPullService $pullService): void
    {
        $pullService->pullPeerById($this->peerId);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('Timerboard sync pull peer job failed.', [
            'peer_id' => $this->peerId,
            'attempts' => $this->job && method_exists($this->job, 'attempts') ? $this->job->attempts() : null,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
    }

    public function uniqueId(): string
    {
        return 'seat-timerboard:sync-pull-peer:' . $this->peerId;
    }

    public function tags(): array
    {
        return ['seat-timerboard', 'sync', 'pull', 'peer:' . $this->peerId];
    }
}
