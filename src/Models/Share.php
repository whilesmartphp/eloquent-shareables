<?php

namespace Whilesmart\Shareables\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Share extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'audience',
        'access',
        'token',
        'revoked_at',
        'owner_type',
        'owner_id',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }
}
