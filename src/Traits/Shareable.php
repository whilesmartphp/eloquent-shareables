<?php

namespace Whilesmart\Shareables\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Whilesmart\Shareables\Models\Share;

trait Shareable
{
    public static function bootShareable(): void
    {
        static::deleted(function (self $resource) {
            if (method_exists($resource, 'isForceDeleting') && ! $resource->isForceDeleting()) {
                $resource->revokeAllShares();
                return;
            }
            $resource->shares()->withTrashed()->forceDelete();
        });
    }

    /** @return MorphMany<Share, $this> */
    public function shares(): MorphMany
    {
        /** @var class-string<Share> $model */
        $model = config('shareables.models.share', Share::class);

        return $this->morphMany($model, 'shareable');
    }

    public function share(Model|iterable|null $audiences, string $ability, ?Model $createdBy = null, array $constraints = []): Share
    {
        if (! $this->exists) {
            throw new InvalidArgumentException('Only persisted resources can be shared.');
        }
        if ($createdBy !== null && ! $createdBy->exists) {
            throw new InvalidArgumentException('A share creator must be a persisted model.');
        }
        if (isset($constraints['token']) && (! is_string($constraints['token']) || $constraints['token'] === '')) {
            throw new InvalidArgumentException('A share token must be a non-empty string.');
        }
        if (isset($constraints['max_uses']) && (! is_int($constraints['max_uses']) || $constraints['max_uses'] < 1)) {
            throw new InvalidArgumentException('The maximum number of uses must be a positive integer.');
        }
        $targets = $audiences instanceof Model || $audiences === null ? [$audiences] : $audiences;

        return $this->getConnection()->transaction(function () use ($targets, $ability, $createdBy, $constraints) {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $share = $this->shares()->make([
                'token' => $constraints['token'] ?? Str::random(40), 'expires_at' => $constraints['expires_at'] ?? null,
                'password' => $constraints['password'] ?? null, 'max_uses' => $constraints['max_uses'] ?? null,
            ]);
            if ($createdBy !== null) {
                $share->createdBy()->associate($createdBy);
            }
            $share->save();
            foreach ($targets as $target) {
                $share->setAudience($target, $ability);
            }

            return $share;
        });
    }

    public function isSharedWith(?Model $audience, ?string $ability = null): bool
    {
        return $this->shares()->active()->whereNull('password')->forAudience($audience, $ability)->exists();
    }

    public function isSharedViaToken(string $token, ?Authenticatable $viewer = null, ?string $ability = null, ?string $password = null): bool
    {
        $abilities = $this->shares()->where('token', $token)->first()?->abilitiesFor($viewer, $password) ?? [];

        return $ability === null ? $abilities !== [] : in_array($ability, $abilities, true);
    }

    public function revokeShare(?Model $audience): void
    {
        $this->shares()->forAudience($audience)->get()->each(fn (Share $share) => $share->removeAudience($audience));
    }

    public function revokeAllShares(): void
    {
        $this->shares()->update(['revoked_at' => now()]);
    }

    public function scopeSharedWith(Builder $query, ?Model $audience, ?string $ability = null): Builder
    {
        return $query->whereHas('shares', function (Builder $shares) use ($audience, $ability) {
            /** @var Builder<Share> $shares */
            $shares->active()->whereNull('password')->forAudience($audience, $ability);
        });
    }
}
