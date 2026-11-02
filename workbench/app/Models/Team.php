<?php

namespace Workbench\App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Whilesmart\Shareables\Contracts\ShareAudience;
use Whilesmart\Shareables\Traits\ReceivesShares;

class Team extends Model implements ShareAudience
{
    use ReceivesShares;

    protected $fillable = ['name'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function admits(?Authenticatable $viewer): bool
    {
        return $viewer instanceof User && $this->members()->whereKey($viewer->getAuthIdentifier())->exists();
    }
}
