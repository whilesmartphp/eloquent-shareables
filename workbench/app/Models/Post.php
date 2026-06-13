<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Shareables\Traits\Shareable;

class Post extends Model
{
    use Shareable;

    protected $fillable = ['title'];
}
