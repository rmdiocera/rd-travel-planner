<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItineraryListItemType;
use App\Http\Traits\HasReorderableItems;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItineraryListItem extends Model
{
    /** @use HasFactory<\Database\Factories\ItineraryListItemFactory> */
    use HasFactory, HasReorderableItems, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_lists_id',
        'type',
        'title',
        'content',
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
            'type' => ItineraryListItemType::class,
        ];
    }

    public function itineraryList(): BelongsTo
    {
        return $this->belongsTo(ItineraryList::class, 'itinerary_lists_id');
    }

    public function placeItem(): HasOne
    {
        return $this->hasOne(ItineraryListItemPlace::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ItineraryListItemChecklistItem::class);
    }

    public function reorderChecklistItems(array $checklist_item_ids): void
    {
        $this->reorder(fn () => $this->checklistItems(), $checklist_item_ids);
    }
}
