<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryListItemPlace extends Model
{
    /** @use HasFactory<\Database\Factories\ItineraryListItemPlaceFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_list_item_id',
        'place_id',
        'start_time',
        'end_time',
        'marked_visited',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marked_visited' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItineraryListItem::class, 'itinerary_list_item_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
