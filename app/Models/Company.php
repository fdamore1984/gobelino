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
        'apns_csr_pending',
        'apns_server_cert',
        'apns_server_key',
        'apns_mdmcert_email',
        'apns_csr_submitted_at',
    ];

    protected $hidden = [
        'apns_private_key_pem',
        'apns_server_key',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'apns_expires_at' => 'datetime',
            'apns_csr_submitted_at' => 'datetime',
            'apns_certificate_pem' => 'encrypted',
            'apns_private_key_pem' => 'encrypted',
            'apns_server_key' => 'encrypted',
        ];
    }

    /**
     * True if a request has been sent to mdmcert.download (Step 1
     * of the APNs configuration) but the final certificate signed
     * by Apple hasn't been uploaded yet (Step 2).
     */
    public function isAwaitingApnsCertificate(): bool
    {
        return ! empty($this->apns_private_key_pem) && empty($this->apns_certificate_pem);
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
     * True if the company has already connected its Android Enterprise.
     * Until it does, it can't add devices.
     */
    public function hasAndroidEnterprise(): bool
    {
        return ! empty($this->android_enterprise_name);
    }

    /**
     * True if the company has uploaded a valid APNs push certificate
     * (an essential prerequisite for enrolling iPhone/iPad devices).
     * Until it's present, iOS enrollment stays disabled.
     */
    public function hasApnsConfigured(): bool
    {
        return ! empty($this->apns_certificate_pem)
            && ! empty($this->apns_private_key_pem)
            && ($this->apns_expires_at === null || $this->apns_expires_at->isFuture());
    }
}
