<?php

namespace Raikia\SeatTimerboard\Services;

use Illuminate\Support\Facades\Http;
use Raikia\SeatTimerboard\Models\Timer;
use Raikia\SeatTimerboard\Models\TimerSyncDelivery;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;

class SeatSyncPeerAdapter implements SyncPeerAdapterInterface
{
    public function __construct(
        private TimerSyncPayloadFactory $payloadFactory
    ) {
    }

    public function pushTimer(TimerSyncPeer $peer, Timer $timer, ?TimerSyncDelivery $delivery = null): ?string
    {
        $response = $this->request($peer)
            ->post($peer->base_url . '/api/v2/timerboard-sync/timers', $this->payloadFactory->build($timer));

        if (! $response->successful()) {
            throw new \RuntimeException('SeAT sync upsert failed with status ' . $response->status() . '.');
        }

        return $delivery?->remote_timer_id;
    }

    public function deleteTimer(
        TimerSyncPeer $peer,
        TimerSyncDelivery $delivery,
        int $localTimerId,
        string $sourceInstanceUuid
    ): bool {
        $response = $this->request($peer)
            ->delete($peer->base_url . '/api/v2/timerboard-sync/timers', [
                'source_instance_uuid' => $sourceInstanceUuid,
                'origin_timer_id' => $localTimerId,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('SeAT sync delete failed with status ' . $response->status() . '.');
        }

        return true;
    }

    public function supportsPull(TimerSyncPeer $peer): bool
    {
        return false;
    }

    public function pullTimers(TimerSyncPeer $peer): array
    {
        return [];
    }

    public function mapRemoteTimer(TimerSyncPeer $peer, array $record): ?array
    {
        return null;
    }

    private function request(TimerSyncPeer $peer)
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->withHeaders([
                'X-Token' => $peer->api_token,
            ]);
    }
}
