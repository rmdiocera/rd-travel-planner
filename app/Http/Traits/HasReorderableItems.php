<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Closure;

trait HasReorderableItems
{
    public function reorder(Closure $relationship, array $ids): void
    {
        collect($ids)->each(function ($id, $index) use ($relationship) {
            $relationship()->where('id', $id)->update(['sort_order' => $index + 1]);
        });
    }
}
