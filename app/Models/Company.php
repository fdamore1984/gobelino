<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subscription_status',
        'trial_ends_at',
        'payment_provider_customer_id',
        'android_enterprise_name',
        'android_signup_url_name',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function enrollmentTokens(): HasMany
    {
        return $this->hasMany(EnrollmentToken::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function subscribed(): bool
    {
        return $this->subscription_status === 'active';
    }

    public function hasAccess(): bool
    {
        return $this->onTrial() || $this->subscribed();
    }

    /**
     * True se l'azienda ha già collegato il proprio Android Enterprise.
     * Finché non lo fa, non può aggiungere dispositivi.
     */
    public function hasAndroidEnterprise(): bool
    {
        return ! empty($this->android_enterprise_name);
    }
}
