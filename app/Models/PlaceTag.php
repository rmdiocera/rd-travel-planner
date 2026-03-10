<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PlaceTag extends Pivot
{
    use HasUlids;

    public $timestamps = false;
}
