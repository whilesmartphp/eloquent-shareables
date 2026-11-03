<?php

namespace Whilesmart\Shareables\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Whilesmart\Shareables\Contracts\ShareAudience;

/**
 * @property string|null $audience_type
 * @property int|null $audience_id
 * @property Model|null $audience
 */
class Audience extends Model
{
    protected $table = 'share_audiences';

    protected $fillable = ['audience_type', 'audience_id', 'ability'];

    public function share(): BelongsTo
    {
        return $this->belongsTo(config('shareables.models.share', Share::class));
    }

    public function audience(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForAudience(Builder $query, ?Model $audience): Builder
    {
        if ($audience === null) {
            return $query->whereNull($this->qualifyColumn('audience_type'))->whereNull($this->qualifyColumn('audience_id'));
        }

        return $query->where($this->qualifyColumn('audience_type'), $audience->getMorphClass())
            ->where($this->qualifyColumn('audience_id'), $audience->getKey());
    }

    public function admits(?Authenticatable $viewer): bool
    {
        if ($this->audience_type === null && $this->audience_id === null) {
            return true;
        }
        $audience = $this->audience;

        return $audience instanceof ShareAudience && $audience->admits($viewer);
    }
}
