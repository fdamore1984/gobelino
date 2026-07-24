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
        'apns_certificate_pem',
        'apns_private_key_pem',
        'apns_topic',
        'apns_expires_at',
    ];

    protected $hidden = [
        'apns_private_key_pem',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'apns_expires_at' => 'datetime',
            'apns_certificate_pem' => 'encrypted',
            'apns_private_key_pem' => 'encrypted',
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

    /**
     * True se l'azienda ha caricato un certificato push APNs valido
     * (prerequisito indispensabile per iscrivere iPhone/iPad).
     * Finché manca, l'enrollment iOS resta disabilitato.
     */
    public function hasApnsConfigured(): bool
    {
        return ! empty($this->apns_certificate_pem)
            && ! empty($this->apns_private_key_pem)
            && ($this->apns_expires_at === null || $this->apns_expires_at->isFuture());
    }
}
