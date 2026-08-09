<?php

namespace Raikia\SeatTimerboard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;
use Raikia\SeatTimerboard\Models\Tag;
use Raikia\SeatTimerboard\Models\TimerSyncPeer;
use Raikia\SeatTimerboard\Services\TimerboardInstanceIdentity;
use Seat\Web\Http\Controllers\Controller;
use Seat\Web\Models\Acl\Role;

class SyncController extends Controller
{
    public function index(TimerboardInstanceIdentity $identity)
    {
        $peers = TimerSyncPeer::with('incomingRole')
            ->orderBy('name')
            ->get();
        $tags = Tag::orderBy('name')->get();
        $roles = Role::orderBy('title')->get();

        return view('seat-timerboard::sync', [
            'peers' => $peers,
            'tags' => $tags,
            'roles' => $roles,
            'localInstanceUuid' => $identity->getUuid(),
            'localInstanceName' => $identity->getName(),
            'localBaseUrl' => $identity->getBaseUrl(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePeerRequest($request);

        TimerSyncPeer::create($this->payloadFromRequest($data, true));

        return redirect()->route('timerboard.sync.index')->with('success', 'Sync peer created successfully.');
    }

    public function update(Request $request, TimerSyncPeer $peer)
    {
        $data = $this->validatePeerRequest($request, $peer);

        $payload = $this->payloadFromRequest($data, false);

        if (blank($data['api_token'] ?? null)) {
            unset($payload['api_token']);
        }

        $peer->update($payload);

        return redirect()->route('timerboard.sync.index')->with('success', 'Sync peer updated successfully.');
    }

    public function destroy(TimerSyncPeer $peer)
    {
        $peer->deliveries()->delete();
        $peer->delete();

        return redirect()->route('timerboard.sync.index')->with('success', 'Sync peer deleted successfully.');
    }

    private function validatePeerRequest(Request $request, ?TimerSyncPeer $peer = null): array
    {
        $identity = app(TimerboardInstanceIdentity::class);
        $validator = validator($request->all(), $this->rules($peer?->id, $peer === null));

        $validator->after(function (Validator $validator) use ($request, $identity) {
            $peerType = $request->input('peer_type', TimerSyncPeer::TYPE_SEAT);
            $peerInstanceUuid = trim((string) $request->input('instance_uuid'));
            $peerBaseUrl = $this->normalizeUrl($request->input('base_url'));
            $localBaseUrl = $this->normalizeUrl($identity->getBaseUrl());

            if ($peerType === TimerSyncPeer::TYPE_SEAT && $peerInstanceUuid === '') {
                $validator->errors()->add('instance_uuid', 'A SeAT peer requires the remote instance UUID.');
            }

            if ($peerType !== TimerSyncPeer::TYPE_SEAT && $peerInstanceUuid !== '' && ! Str::isUuid($peerInstanceUuid)) {
                $validator->errors()->add('instance_uuid', 'The peer identifier must be a valid UUID.');
            }

            if ($peerType === TimerSyncPeer::TYPE_SEAT && $peerInstanceUuid !== '' && $peerInstanceUuid === $identity->getUuid()) {
                $validator->errors()->add('instance_uuid', 'You cannot configure this SeAT instance as its own sync peer.');
            }

            if ($peerType === TimerSyncPeer::TYPE_SEAT && $peerBaseUrl !== '' && $localBaseUrl !== '' && $peerBaseUrl === $localBaseUrl) {
                $validator->errors()->add('base_url', 'You cannot configure this SeAT instance as its own sync peer.');
            }
        });

        return $validator->validate();
    }

    private function rules(?int $peerId = null, bool $requireToken = true): array
    {
        return [
            'name' => 'required|string|max:255',
            'peer_type' => ['required', Rule::in([TimerSyncPeer::TYPE_SEAT, TimerSyncPeer::TYPE_AAUTH])],
            'instance_uuid' => [
                'nullable',
                'uuid',
                Rule::unique('seat_timerboard_sync_peers', 'instance_uuid')->ignore($peerId),
            ],
            'base_url' => 'required|url|max:255',
            'api_token' => ($requireToken ? 'required' : 'nullable') . '|string|max:255',
            'sync_tag_ids' => 'nullable|array',
            'sync_tag_ids.*' => 'integer|exists:seat_timerboard_tags,id',
            'excluded_tag_ids' => 'nullable|array',
            'excluded_tag_ids.*' => 'integer|exists:seat_timerboard_tags,id',
            'incoming_role_id' => 'nullable|integer|exists:roles,id',
            'allow_remote_delete' => 'nullable|boolean',
            'pull_remote_timers' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
        ];
    }

    private function payloadFromRequest(array $data, bool $isCreate): array
    {
        $peerType = $data['peer_type'] ?? TimerSyncPeer::TYPE_SEAT;

        $payload = [
            'name' => $data['name'],
            'peer_type' => $peerType,
            'instance_uuid' => $data['instance_uuid'] ?? ($isCreate ? (string) Str::uuid() : null),
            'base_url' => $data['base_url'],
            'sync_tag_ids' => $data['sync_tag_ids'] ?? [],
            'excluded_tag_ids' => $data['excluded_tag_ids'] ?? [],
            'incoming_role_id' => $data['incoming_role_id'] ?? null,
            'allow_remote_delete' => $peerType === TimerSyncPeer::TYPE_SEAT && ! empty($data['allow_remote_delete']),
            'pull_remote_timers' => $peerType === TimerSyncPeer::TYPE_AAUTH && ! empty($data['pull_remote_timers']),
            'is_enabled' => ! empty($data['is_enabled']),
        ];

        if (! $isCreate && blank($payload['instance_uuid'])) {
            unset($payload['instance_uuid']);
        }

        if ($isCreate || filled($data['api_token'] ?? null)) {
            $payload['api_token'] = $data['api_token'];
        }

        return $payload;
    }

    private function normalizeUrl(?string $url): string
    {
        return rtrim(trim((string) $url), '/');
    }
}
