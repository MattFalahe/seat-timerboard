<?php

namespace Raikia\SeatTimerboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TimerSyncPeer extends Model
{
    public const TYPE_SEAT = 'seat';
    public const TYPE_AAUTH = 'aauth';

    protected $table = 'seat_timerboard_sync_peers';

    protected $fillable = [
        'name',
        'peer_type',
        'instance_uuid',
        'base_url',
        'api_token',
        'sync_tag_ids',
        'excluded_tag_ids',
        'incoming_role_id',
        'allow_remote_delete',
        'pull_remote_timers',
        'is_enabled',
    ];

    protected $casts = [
        'allow_remote_delete' => 'boolean',
        'pull_remote_timers' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function getSyncTagIdsAttribute($value): array
    {
        return array_values(array_filter(json_decode($value ?: '[]', true) ?: [], function ($tagId) {
            return filled($tagId);
        }));
    }

    public function setSyncTagIdsAttribute($value): void
    {
        $this->attributes['sync_tag_ids'] = json_encode(array_values(array_unique(array_map('intval', $value ?: []))));
    }

    public function getExcludedTagIdsAttribute($value): array
    {
        return array_values(array_filter(json_decode($value ?: '[]', true) ?: [], function ($tagId) {
            return filled($tagId);
        }));
    }

    public function setExcludedTagIdsAttribute($value): void
    {
        $this->attributes['excluded_tag_ids'] = json_encode(array_values(array_unique(array_map('intval', $value ?: []))));
    }

    public function getApiTokenAttribute($value): string
    {
        return Crypt::decryptString($value);
    }

    public function setApiTokenAttribute($value): void
    {
        if (blank($value)) {
            return;
        }

        $this->attributes['api_token'] = Crypt::encryptString(trim($value));
    }

    public function setBaseUrlAttribute($value): void
    {
        $this->attributes['base_url'] = rtrim(trim((string) $value), '/');
    }

    public function includedTagIds(): array
    {
        return array_map('intval', $this->sync_tag_ids ?: []);
    }

    public function excludedTagIds(): array
    {
        return array_map('intval', $this->excluded_tag_ids ?: []);
    }

    public function isSeatPeer(): bool
    {
        return ($this->peer_type ?: self::TYPE_SEAT) === self::TYPE_SEAT;
    }

    public function isAauthPeer(): bool
    {
        return $this->peer_type === self::TYPE_AAUTH;
    }

    public function incomingRole()
    {
        return $this->belongsTo(\Seat\Web\Models\Acl\Role::class, 'incoming_role_id');
    }

    public function deliveries()
    {
        return $this->hasMany(TimerSyncDelivery::class, 'peer_id');
    }
}
