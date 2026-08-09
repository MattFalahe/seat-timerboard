<?php

namespace Raikia\SeatTimerboard\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Raikia\SeatTimerboard\Jobs\ProcessTimerSyncDelete;
use Raikia\SeatTimerboard\Jobs\ProcessTimerSyncUpsert;
use Raikia\SeatTimerboard\Models\Timer;
use Raikia\SeatTimerboard\Models\TimerSyncDelivery;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;

class TimerSyncOutboundService
{
    public function __construct(
        private TimerSyncPeerAdapterFactory $adapterFactory,
        private TimerboardInstanceIdentity $identity
    ) {
    }

    public function syncTimer(Timer $timer): void
    {
        if ($timer->isRemoteSynced()) {
            return;
        }

        TimerSyncPeer::query()
            ->where('is_enabled', true)
            ->get()
            ->each(function (TimerSyncPeer $peer) use ($timer) {
                ProcessTimerSyncUpsert::dispatch($timer->id, $peer->id);
            });
    }

    public function deleteLocalTimer(array $snapshot): void
    {
        $localTimerId = (int) ($snapshot['id'] ?? 0);

        if ($localTimerId === 0) {
            return;
        }

        if (! empty($snapshot['is_remote_synced'])) {
            TimerSyncDelivery::where('local_timer_id', $localTimerId)->delete();

            return;
        }

        $deliveries = TimerSyncDelivery::with('peer')
            ->where('local_timer_id', $localTimerId)
            ->get();

        foreach ($deliveries as $delivery) {
            $peer = $delivery->peer;

            if (! $peer || ! $peer->is_enabled || ! $peer->allow_remote_delete) {
                $delivery->delete();
                continue;
            }

            ProcessTimerSyncDelete::dispatch($delivery->id, $localTimerId);
        }
    }

    public function syncTimerToPeer(int $timerId, int $peerId): void
    {
        $timer = Timer::with('tags')->find($timerId);

        if (! $timer || $timer->isRemoteSynced()) {
            return;
        }

        $peer = TimerSyncPeer::query()
            ->whereKey($peerId)
            ->where('is_enabled', true)
            ->first();

        if (! $peer) {
            return;
        }

        $tagIds = $timer->tags->pluck('id')->map(fn ($id) => (int) $id)->all();
        $delivery = TimerSyncDelivery::query()
            ->where('local_timer_id', $timer->id)
            ->where('peer_id', $peer->id)
            ->first();

        if (! $this->shouldSyncToPeer($peer, $tagIds)) {
            if ($delivery) {
                $this->removeDeliveryForPeer($peer, $delivery, $timer->id);
            }

            return;
        }

        $adapter = $this->adapterFactory->forPeer($peer);

        try {
            $remoteTimerId = $adapter->pushTimer($peer, $timer, $delivery);
        } catch (\Throwable $exception) {
            Log::warning('Timerboard sync upsert failed.', [
                'timer_id' => $timer->id,
                'peer_id' => $peer->id,
                'peer_type' => $peer->peer_type,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        TimerSyncDelivery::updateOrCreate(
            [
                'local_timer_id' => $timer->id,
                'peer_id' => $peer->id,
            ],
            [
                'remote_timer_id' => $remoteTimerId,
                'last_synced_at' => Carbon::now(),
            ]
        );
    }

    public function deleteDeliveryFromPeer(int $deliveryId, int $localTimerId): void
    {
        $delivery = TimerSyncDelivery::with('peer')->find($deliveryId);

        if (! $delivery) {
            return;
        }

        $peer = $delivery->peer;

        $this->removeDeliveryForPeer($peer, $delivery, $localTimerId);
    }

    private function shouldSyncToPeer(TimerSyncPeer $peer, array $tagIds): bool
    {
        $excludedTagIds = $peer->excludedTagIds();
        $includedTagIds = $peer->includedTagIds();

        if (! empty(array_intersect($tagIds, $excludedTagIds))) {
            return false;
        }

        if (empty($includedTagIds)) {
            return true;
        }

        return ! empty(array_intersect($tagIds, $includedTagIds));
    }

    private function removeDeliveryForPeer(?TimerSyncPeer $peer, TimerSyncDelivery $delivery, int $localTimerId): void
    {
        if (! $peer || ! $peer->is_enabled || ! $peer->allow_remote_delete) {
            $delivery->delete();

            return;
        }

        try {
            $deleted = $this->adapterFactory
                ->forPeer($peer)
                ->deleteTimer($peer, $delivery, $localTimerId, $this->identity->getUuid());
        } catch (\Throwable $exception) {
            Log::warning('Timerboard sync delete failed.', [
                'local_timer_id' => $localTimerId,
                'peer_id' => $peer->id,
                'peer_type' => $peer->peer_type,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($deleted) {
            $delivery->delete();
        }
    }
}
