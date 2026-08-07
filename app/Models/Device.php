<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'added_by',
        'enrollment_token_id',
        'name',
        'platform',
        'device_token',
        'udid',
        'push_magic',
        'mdm_token',
        'fcm_token',
        'status',
        'model',
        'manufacturer',
        'android_version',
        'serial_number',
        'imei',
        'battery_level',
        'poll_interval_seconds',
        'last_poll_at',
        'kiosk_enabled',
        'kiosk_allowed_packages',
        'is_device_owner',
        'agent_app_version',
        'last_synced_at',
    ];

    protected $hidden = [
        'device_token',
    ];

    public function isIos(): bool
    {
        return $this->platform === 'ios';
    }

    public function isAndroid(): bool
    {
        return $this->platform === 'android';
    }

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_poll_at' => 'datetime',
            'kiosk_enabled' => 'boolean',
            'kiosk_allowed_packages' => 'array',
            'is_device_owner' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * True if the agent hasn't polled recently (twice its expected
     * interval), used in the UI to flag devices as offline/stale.
     */
    public function isOnline(): bool
    {
        if (! $this->last_poll_at) {
            return false;
        }

        return $this->last_poll_at->greaterThan(
            now()->subSeconds($this->poll_interval_seconds * 2 + 60)
        );
    }

    public static function generateDeviceToken(): string
    {
        return Str::random(48);
    }
}
