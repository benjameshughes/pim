<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EbayAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ebay_user_id',
        'environment',
        'access_token',
        'refresh_token',
        'expires_in',
        'token_expires_at',
        'scopes',
        'status',
        'oauth_data',
        'last_used_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'oauth_data' => 'encrypted:array',
        'scopes' => 'encrypted:array',
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Check if the access token is expired
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return Carbon::now()->isAfter($this->token_expires_at);
    }

    /**
     * Check if the account is active and has valid tokens
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               !empty($this->access_token) && 
               !$this->isTokenExpired();
    }

    /**
     * Check if the account needs token refresh
     */
    public function needsRefresh(): bool
    {
        return $this->status === 'active' && 
               $this->isTokenExpired() && 
               !empty($this->refresh_token);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Update token information from OAuth response
     */
    public function updateTokens(array $tokenData): void
    {
        $expiresAt = isset($tokenData['expires_in']) 
            ? now()->addSeconds($tokenData['expires_in'])
            : null;

        $this->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $this->refresh_token,
            'expires_in' => $tokenData['expires_in'] ?? null,
            'token_expires_at' => $expiresAt,
            'status' => 'active',
            'oauth_data' => array_merge($this->oauth_data ?? [], $tokenData),
        ]);
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for specific environment
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
}
