<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'added_by',
        'name',
        'platform',
        'google_device_id',
        'udid',
        'push_magic',
        'mdm_token',
        'status',
        'model',
        'manufacturer',
        'android_version',
        'last_synced_at',
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
}
