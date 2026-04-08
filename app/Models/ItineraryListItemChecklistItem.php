<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryListItemChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\ItineraryListItemChecklistItemFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_list_item_id',
        'label',
        'is_checked',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItineraryListItem::class, 'itinerary_list_item_id');
    }
}
