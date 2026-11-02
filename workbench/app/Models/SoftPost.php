<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftPost extends Post
{
    use SoftDeletes;

    protected $table = 'posts';
}
