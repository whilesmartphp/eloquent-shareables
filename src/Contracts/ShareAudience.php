<?php

namespace Whilesmart\Shareables\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface ShareAudience
{
    public function admits(?Authenticatable $viewer): bool;
}
