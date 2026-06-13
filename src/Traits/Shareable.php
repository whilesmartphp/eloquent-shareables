<?php

namespace Whilesmart\Shareables\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Whilesmart\Shareables\Models\Share;

trait Shareable
{
    /**
     * Get all shares for the model.
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    /**
     * Share the model with an owner and audience.
     */
    public function share(string $access, Model $owner, ?string $audience = null, ?string $token = null): Share
    {
        return $this->shares()->create([
            'access' => $access,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'audience' => $audience,
            'token' => $token ?? Str::random(40),
        ]);
    }

    /**
     * Check if the model has a valid share with the given audience.
     */
    public function isSharedWith(?string $audience): bool
    {
        return $this->shares()
            ->where('audience', $audience)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Check if the model has a valid share via the given token.
     */
    public function isSharedViaToken(string $token): bool
    {
        return $this->shares()
            ->where('token', $token)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Revoke shares for a specific audience.
     */
    public function revokeShare(?string $audience): void
    {
        $this->shares()
            ->where('audience', $audience)
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revoke all shares for the model.
     */
    public function revokeAllShares(): void
    {
        $this->shares()->update(['revoked_at' => now()]);
    }

    /**
     * Scope a query to only include models shared with a specific audience.
     */
    public function scopeSharedWith(Builder $query, ?string $audience): Builder
    {
        return $query->whereHas('shares', function ($q) use ($audience) {
            $q->where('audience', $audience)
                ->whereNull('revoked_at');
        });
    }
}
