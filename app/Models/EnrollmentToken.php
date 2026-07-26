<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'platform',
        'google_name',
        'token',
        'apk_checksum',
        'qr_code_json',
        'expires_at',
        'used',
    ];

    /**
     * Consumes the token: marks it used, so it can't be redeemed by a
     * second device.
     */
    public function markUsed(): void
    {
        $this->update(['used' => true]);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
