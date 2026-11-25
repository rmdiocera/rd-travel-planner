<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Traits\HasReorderableItems;
use Database\Factories\ItineraryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itinerary extends Model
{
    /** @use HasFactory<ItineraryFactory> */
    use HasFactory, HasReorderableItems, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'start_date',
        'end_date',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spots(): HasMany
    {
        return $this->hasMany(ItinerarySpot::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ItineraryList::class);
    }

    public function reorderLists(array $list_ids): void
    {
        $this->reorder(fn () => $this->lists(), $list_ids);
    }

    /**
     * The spots on one day of the itinerary, given as Y-m-d.
     */
    public function spotsOn(string $visit_date): HasMany
    {
        return $this->spots()->where('visit_date', $visit_date);
    }

    public function nextSpotSortOrder(string $visit_date): int
    {
        return (int) $this->spotsOn($visit_date)->max('sort_order') + 1;
    }

    /**
     * @param  list<string>  $spot_ids
     */
    public function reorderSpots(string $visit_date, array $spot_ids): void
    {
        $this->reorder(fn () => $this->spotsOn($visit_date), $spot_ids);
    }

    public function renumberSpotsOn(string $visit_date): void
    {
        $this->reorderSpots($visit_date, $this->spotsOn($visit_date)->orderBy('sort_order')->pluck('id')->all());
    }
}
