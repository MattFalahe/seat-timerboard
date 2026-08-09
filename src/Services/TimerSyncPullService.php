<?php

namespace Raikia\SeatTimerboard\Services;

use Illuminate\Support\Facades\Log;
use Raikia\SeatTimerboard\Jobs\ProcessTimerSyncPullPeer;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;

class TimerSyncPullService
{
    public function __construct(
        private TimerSyncPeerAdapterFactory $adapterFactory,
        private TimerSyncInboundService $inboundService,
        private TimerMutationDispatcher $mutationDispatcher
    ) {
    }

    public function queueEnabledPeers(?int $peerId = null): int
    {
        $query = TimerSyncPeer::query()
            ->where('is_enabled', true)
            ->where('peer_type', TimerSyncPeer::TYPE_AAUTH)
            ->where('pull_remote_timers', true)
            ->orderBy('name');

        if ($peerId !== null) {
            $query->whereKey($peerId);
        }

        $peers = $query->get();

        $peers->each(function (TimerSyncPeer $peer) {
            ProcessTimerSyncPullPeer::dispatch($peer->id);
        });

        return $peers->count();
    }

    public function pullPeerById(int $peerId): void
    {
        $peer = TimerSyncPeer::query()
            ->whereKey($peerId)
            ->where('is_enabled', true)
            ->where('peer_type', TimerSyncPeer::TYPE_AAUTH)
            ->where('pull_remote_timers', true)
            ->first();

        if (! $peer) {
            return;
        }

        $this->pullPeer($peer);
    }

    public function pullPeer(TimerSyncPeer $peer): void
    {
        $adapter = $this->adapterFactory->forPeer($peer);

        if (! $adapter->supportsPull($peer)) {
            return;
        }

        foreach ($adapter->pullTimers($peer) as $record) {
            try {
                $mapped = $adapter->mapRemoteTimer($peer, $record);

                if (! $mapped) {
                    continue;
                }

                [$timer, $isNew] = $this->inboundService->upsertRemoteTimer(
                    $peer,
                    (string) $mapped['origin_instance_uuid'],
                    (string) $mapped['origin_timer_id'],
                    $mapped['timer'],
                    'AAuth',
                    [
                        'source_instance_uuid' => $mapped['origin_instance_uuid'],
                        'source_instance_name' => $peer->name,
                        'origin_timer_id' => $mapped['origin_timer_id'],
                    ]
                );

                $this->mutationDispatcher->dispatchSaved($timer, $isNew);
            } catch (\Throwable $exception) {
                Log::warning('Timerboard AAuth pull skipped a timer.', [
                    'peer_id' => $peer->id,
                    'peer_name' => $peer->name,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
