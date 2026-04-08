<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Traits\HasReorderableItems;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryList extends Model
{
    /** @use HasFactory<\Database\Factories\ItineraryListFactory> */
    use HasFactory, HasReorderableItems, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_id',
        'name',
        'sort_order',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryListItem::class, 'itinerary_lists_id');
    }

    public function reorderListItems(array $list_item_ids): void
    {
        $this->reorder(fn () => $this->items(), $list_item_ids);
    }
}
