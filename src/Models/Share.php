<?php

namespace Whilesmart\Shareables\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Whilesmart\Shareables\Contracts\ShareAudience;

/**
 * @property string $token
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $password
 * @property int|null $max_uses
 * @property int $uses
 * @property \Illuminate\Database\Eloquent\Collection<int, Audience> $audiences
 * @property Model|null $shareable
 */
class Share extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['token', 'revoked_at', 'expires_at', 'password', 'max_uses', 'uses',
        'created_by_type', 'created_by_id'];

    protected $hidden = ['password'];

    protected $casts = ['revoked_at' => 'datetime', 'expires_at' => 'datetime', 'password' => 'hashed',
        'max_uses' => 'integer', 'uses' => 'integer'];

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Audience, $this> */
    public function audiences(): HasMany
    {
        /** @var class-string<Audience> $model */
        $model = config('shareables.models.audience', Audience::class);

        return $this->hasMany($model, 'share_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('revoked_at'))
            ->where(fn (Builder $expires) => $expires->whereNull($this->qualifyColumn('expires_at'))
                ->orWhere($this->qualifyColumn('expires_at'), '>', now()))
            ->where(fn (Builder $uses) => $uses->whereNull($this->qualifyColumn('max_uses'))
                ->orWhereColumn($this->qualifyColumn('uses'), '<', $this->qualifyColumn('max_uses')));
    }

    public function scopeForAudience(Builder $query, ?Model $audience, ?string $ability = null): Builder
    {
        return $query->whereHas('audiences', function (Builder $targets) use ($audience, $ability) {
            /** @var Builder<Audience> $targets */
            $targets->forAudience($audience);
            if ($ability !== null) {
                $targets->where('ability', $ability);
            }
        });
    }

    public function scopeByCreator(Builder $query, Model $creator): Builder
    {
        return $query->where($this->qualifyColumn('created_by_type'), $creator->getMorphClass())
            ->where($this->qualifyColumn('created_by_id'), $creator->getKey());
    }

    public function scopeForResource(Builder $query, Model $resource): Builder
    {
        return $query->where($this->qualifyColumn('shareable_type'), $resource->getMorphClass())
            ->where($this->qualifyColumn('shareable_id'), $resource->getKey());
    }

    public function setAudience(?Model $audience, string $ability): Audience
    {
        if ($ability === '' || mb_strlen($ability) > 255) {
            throw new InvalidArgumentException('An audience ability must contain between 1 and 255 characters.');
        }
        if ($audience !== null && (! $audience instanceof ShareAudience || ! $audience->exists)) {
            throw new InvalidArgumentException('A share audience must be a persisted model implementing ShareAudience.');
        }

        return $this->getConnection()->transaction(function () use ($audience, $ability) {
            static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $this->unsetRelation('audiences');

            return $this->audiences()->updateOrCreate([
                'audience_type' => $audience?->getMorphClass(), 'audience_id' => $audience?->getKey(),
            ], ['ability' => $ability]);
        });
    }

    public function removeAudience(?Model $audience): void
    {
        $this->audiences()->forAudience($audience)->delete();
        $this->unsetRelation('audiences');
    }

    public function isValid(?string $password = null): bool
    {
        if ($this->trashed() || $this->revoked_at !== null || ($this->expires_at !== null && $this->expires_at->lte(now()))) {
            return false;
        }
        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return false;
        }

        return $this->password === null || ($password !== null && Hash::check($password, $this->password));
    }

    public function abilitiesFor(?Authenticatable $viewer, ?string $password = null): array
    {
        $share = static::with(['audiences.audience', 'shareable'])->find($this->getKey());
        if ($share === null || ! $share->isValid($password) || $share->shareable === null) {
            return [];
        }

        return $share->audiences->filter(fn (Audience $audience) => $audience->admits($viewer))
            ->pluck('ability')->unique()->values()->all();
    }

    public static function resolveToken(string $token, ?Authenticatable $viewer = null, ?string $password = null): array
    {
        return static::query()->getModel()->getConnection()->transaction(function () use ($token, $viewer, $password) {
            $share = static::where('token', $token)->lockForUpdate()->first();
            $abilities = $share?->abilitiesFor($viewer, $password) ?? [];
            if ($abilities !== []) {
                $share->increment('uses');
            }

            return $abilities;
        });
    }
}
