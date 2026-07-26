<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    use HasFactory;

    public const TYPE_LOCK = 'lock';
    public const TYPE_WIPE = 'wipe';
    public const TYPE_REBOOT = 'reboot';
    public const TYPE_INSTALL_APP = 'install_app';
    public const TYPE_UNINSTALL_APP = 'uninstall_app';
    public const TYPE_SET_KIOSK = 'set_kiosk';
    public const TYPE_APPLY_POLICY = 'apply_policy';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACKED = 'acked';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'device_id',
        'created_by',
        'type',
        'payload',
        'status',
        'result',
        'sent_at',
        'acked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'acked_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
