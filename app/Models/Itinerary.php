<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Traits\HasReorderableItems;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itinerary extends Model
{
    /** @use HasFactory<\Database\Factories\ItineraryFactory> */
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
}
