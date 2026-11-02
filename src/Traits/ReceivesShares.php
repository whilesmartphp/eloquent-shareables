<?php

namespace Whilesmart\Shareables\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Whilesmart\Shareables\Models\Audience;

trait ReceivesShares
{
    public static function bootReceivesShares(): void
    {
        static::deleted(function (self $audience) {
            if (! method_exists($audience, 'isForceDeleting') || $audience->isForceDeleting()) {
                $audience->receivedShareAudiences()->delete();
            }
        });
    }

    /** @return MorphMany<Audience, $this> */
    public function receivedShareAudiences(): MorphMany
    {
        /** @var class-string<Audience> $model */
        $model = config('shareables.models.audience', Audience::class);

        return $this->morphMany($model, 'audience');
    }
}
