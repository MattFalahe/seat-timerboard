<?php

namespace Raikia\SeatTimerboard\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Raikia\SeatTimerboard\Models\Tag;
use Raikia\SeatTimerboard\Models\Timer;
use Raikia\SeatTimerboard\Models\TimerSyncDelivery;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;

class AauthSyncPeerAdapter implements SyncPeerAdapterInterface
{
    private const HOURS_PASSED_HEADER = 'hours-passed';
    private const HOURS_UNTIL_HEADER = 'hours-until';
    private const PULL_HOURS_PASSED = '0';
    private const PULL_HOURS_UNTIL = '8760';

    public function __construct(
        private TimerboardInstanceIdentity $identity,
        private TimerSyncMarker $marker
    ) {
    }

    public function pushTimer(TimerSyncPeer $peer, Timer $timer, ?TimerSyncDelivery $delivery = null): ?string
    {
        if ($delivery) {
            return $delivery->remote_timer_id;
        }

        $payload = $this->buildPayload($timer);

        if ($payload === null) {
            throw new \RuntimeException('Unable to build an AAuth timer payload from the local timer.');
        }

        $response = $this->request($peer)->post($this->collectionUrl($peer), $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('AAuth sync create failed with status ' . $response->status() . '.');
        }

        $responseData = $response->json();

        return $this->extractRemoteTimerId(is_array($responseData) ? $responseData : []);
    }

    public function deleteTimer(
        TimerSyncPeer $peer,
        TimerSyncDelivery $delivery,
        int $localTimerId,
        string $sourceInstanceUuid
    ): bool {
        return false;
    }

    public function supportsPull(TimerSyncPeer $peer): bool
    {
        return $peer->pull_remote_timers;
    }

    public function pullTimers(TimerSyncPeer $peer): array
    {
        $response = $this->request($peer)
            ->withHeaders([
                self::HOURS_PASSED_HEADER => self::PULL_HOURS_PASSED,
                self::HOURS_UNTIL_HEADER => self::PULL_HOURS_UNTIL,
            ])
            ->get($this->collectionUrl($peer));

        if (! $response->successful()) {
            throw new \RuntimeException('AAuth sync pull failed with status ' . $response->status() . '.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [];
        }

        return $this->extractTimerCollection($body);
    }

    public function mapRemoteTimer(TimerSyncPeer $peer, array $record): ?array
    {
        $notes = (string) (Arr::get($record, 'details_notes') ?? Arr::get($record, 'notes') ?? '');
        $marker = $this->marker->extract($notes);

        if (($marker['source_instance_uuid'] ?? null) === $this->identity->getUuid()) {
            return null;
        }

        $systemName = $this->extractSolarSystemName($record);
        $structureType = Timer::normalizeStructureType(
            Arr::get($record, 'structure.type')
            ?? Arr::get($record, 'structure.type_name')
            ?? Arr::get($record, 'structure.typeName')
            ?? Arr::get($record, 'structure_type.name')
            ?? Arr::get($record, 'structure_type_name')
            ?? Arr::get($record, 'structure_type')
        );
        $eveTime = $this->extractDate($record);

        if (blank($systemName) || blank($structureType) || blank($eveTime)) {
            return null;
        }

        $eveTimeCarbon = Carbon::parse($eveTime)->setTimezone('UTC');

        if ($eveTimeCarbon->lessThanOrEqualTo(Carbon::now('UTC'))) {
            return null;
        }

        $remoteId = $this->extractRemoteTimerId($record);
        $syntheticOriginId = $this->syntheticOriginTimerId($record, $systemName, $structureType, $eveTimeCarbon);

        if ($this->matchesExistingOutboundTimer(
            $peer,
            $systemName,
            $structureType,
            Arr::get($record, 'structure.name')
                ?? Arr::get($record, 'structure_name'),
            Arr::get($record, 'owner')
                ?? Arr::get($record, 'owner_name')
                ?? Arr::get($record, 'owner_corporation'),
            $eveTimeCarbon
        )) {
            return null;
        }

        return [
            'origin_instance_uuid' => (string) ($marker['source_instance_uuid'] ?? $peer->instance_uuid),
            'origin_timer_id' => (string) ($marker['origin_timer_id'] ?? $remoteId ?? $syntheticOriginId),
            'timer' => [
                'system' => $systemName,
                'structure_type' => $structureType,
                'structure_name' => Arr::get($record, 'structure.name')
                    ?? Arr::get($record, 'structure_name')
                    ?: null,
                'notes' => $this->marker->strip($notes),
                'owner_corporation' => (string) (
                    Arr::get($record, 'owner')
                    ?? Arr::get($record, 'owner_name')
                    ?? Arr::get($record, 'owner_corporation')
                    ?? 'Unknown'
                ),
                'attacker_corporation' => null,
                'eve_time' => $eveTimeCarbon->toIso8601String(),
                'tags' => $this->buildTagsFromRemoteRecord($record),
            ],
        ];
    }

    private function buildPayload(Timer $timer): ?array
    {
        $structureTypeId = Timer::structureTypeTypeId($timer->structure_type);

        if (! filled($timer->system) || ! $structureTypeId) {
            return null;
        }

        return [
            'date' => $timer->eve_time->copy()->setTimezone('UTC')->toIso8601String(),
            'system' => $timer->system,
            'structure' => $structureTypeId,
            'structure_name' => $timer->structure_name ?: '',
            'owner' => $timer->owner_corporation ?: null,
            'details_notes' => $this->marker->append($timer->notes, [
                'source_platform' => 'seat',
                'source_instance_uuid' => $this->identity->getUuid(),
                'source_instance_name' => $this->identity->getName(),
                'origin_timer_id' => $timer->id,
            ]),
            'timer_type' => $this->resolveRemoteTimerType($timer),
            'objective' => $this->resolveRemoteObjective($timer),
            'opsec' => false,
        ];
    }

    private function resolveRemoteTimerType(Timer $timer): string
    {
        $tagNames = $timer->tags->pluck('name')->map(fn ($name) => strtolower((string) $name))->all();

        if (in_array('anchoring', $tagNames, true)) {
            return 'AN';
        }

        return 'NO';
    }

    private function resolveRemoteObjective(Timer $timer): string
    {
        $tagNames = $timer->tags->pluck('name')->map(fn ($name) => strtolower((string) $name))->all();

        if (in_array('friendly', $tagNames, true)) {
            return 'FR';
        }

        if (in_array('hostile', $tagNames, true)) {
            return 'HO';
        }

        return 'UN';
    }

    private function buildTagsFromRemoteRecord(array $record): array
    {
        $tags = [];
        $objective = strtoupper((string) (Arr::get($record, 'stance') ?? Arr::get($record, 'objective', '')));
        $timerType = strtoupper((string) (Arr::get($record, 'type') ?? Arr::get($record, 'timer_type', '')));

        if ($objective === 'FR') {
            $tags[] = ['name' => 'Friendly', 'color' => Tag::defaultColorFor('Friendly')];
        }

        if ($objective === 'HO') {
            $tags[] = ['name' => 'Hostile', 'color' => Tag::defaultColorFor('Hostile')];
        }

        if ($timerType === 'AN') {
            $tags[] = ['name' => 'Anchoring', 'color' => Tag::defaultColorFor('Anchoring')];
        }

        return $tags;
    }

    private function extractTimerCollection(array $body): array
    {
        $timers = Arr::get($body, 'timers');

        if (is_array($timers)) {
            return $timers;
        }

        if (array_is_list($body)) {
            return $body;
        }

        foreach (['results', 'items', 'data'] as $key) {
            $value = Arr::get($body, $key);

            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    private function extractRemoteTimerId(array $record): ?string
    {
        foreach (['id', 'pk', 'timer_id'] as $key) {
            $value = Arr::get($record, $key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        $url = Arr::get($record, 'url');

        if (is_string($url) && preg_match('#/edit/(\d+)$#', rtrim($url, '/'), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractSolarSystemName(array $record): ?string
    {
        $value = Arr::get($record, 'eve_solar_system.name')
            ?? Arr::get($record, 'eve_solar_system')
            ?? Arr::get($record, 'solar_system.name')
            ?? Arr::get($record, 'solar_system_name')
            ?? Arr::get($record, 'system');

        return filled($value) ? (string) $value : null;
    }

    private function extractDate(array $record): ?string
    {
        $timestamp = Arr::get($record, 'timestamp');

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestampUTC((int) $timestamp)->toIso8601String();
        }

        $value = Arr::get($record, 'date')
            ?? Arr::get($record, 'eve_time')
            ?? Arr::get($record, 'timer_time');

        return filled($value) ? (string) $value : null;
    }

    private function collectionUrl(TimerSyncPeer $peer): string
    {
        return $peer->base_url;
    }

    private function syntheticOriginTimerId(
        array $record,
        string $systemName,
        string $structureType,
        Carbon $eveTimeCarbon
    ): string {
        $structureName = trim((string) (Arr::get($record, 'structure.name') ?? Arr::get($record, 'structure_name') ?? ''));
        $owner = trim((string) (Arr::get($record, 'owner') ?? Arr::get($record, 'owner_name') ?? Arr::get($record, 'owner_corporation') ?? ''));
        $objective = strtoupper(trim((string) (Arr::get($record, 'stance') ?? Arr::get($record, 'objective') ?? '')));
        $timerType = strtoupper(trim((string) (Arr::get($record, 'type') ?? Arr::get($record, 'timer_type') ?? '')));

        $canonical = implode('|', [
            mb_strtolower($systemName),
            mb_strtolower($structureType),
            mb_strtolower($structureName),
            mb_strtolower($owner),
            $eveTimeCarbon->timestamp,
            $objective,
            $timerType,
        ]);

        return sprintf('%u', crc32($canonical));
    }

    private function matchesExistingOutboundTimer(
        TimerSyncPeer $peer,
        string $systemName,
        string $structureType,
        ?string $structureName,
        ?string $ownerCorporation,
        Carbon $eveTimeCarbon
    ): bool {
        return Timer::query()
            ->whereHas('syncDeliveries', function ($query) use ($peer) {
                $query->where('peer_id', $peer->id);
            })
            ->whereNull('sync_origin_instance_uuid')
            ->where('system', $systemName)
            ->where('structure_type', $structureType)
            ->where('eve_time', $eveTimeCarbon->copy()->setTimezone('UTC'))
            ->when(filled($structureName), function ($query) use ($structureName) {
                $query->where('structure_name', (string) $structureName);
            }, function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNull('structure_name')->orWhere('structure_name', '');
                });
            })
            ->when(filled($ownerCorporation), function ($query) use ($ownerCorporation) {
                $query->where('owner_corporation', (string) $ownerCorporation);
            }, function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNull('owner_corporation')->orWhere('owner_corporation', '');
                });
            })
            ->exists();
    }

    private function request(TimerSyncPeer $peer)
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->withHeaders([
                'X-restapi-Key' => $peer->api_token,
            ]);
    }
}
