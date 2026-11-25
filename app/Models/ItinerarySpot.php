<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ItinerarySpotFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItinerarySpot extends Model
{
    /** @use HasFactory<ItinerarySpotFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'itinerary_id',
        'place_id',
        'visit_date',
        'start_time',
        'end_time',
        'marked_visited',
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
            'visit_date' => 'date',
            'marked_visited' => 'boolean',
        ];
    }

    protected function startTime(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::withSeconds($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::withSeconds($value));
    }

    /**
     * Store an "HH:MM" time as "HH:MM:00", the form the database returns, so
     * resending an unchanged time doesn't count as a change.
     */
    private static function withSeconds(?string $time): ?string
    {
        return $time !== null && strlen($time) === 5 ? "{$time}:00" : $time;
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Move this spot the shortest distance that puts it in time order with the
     * other timed spots of its day. Untimed spots don't count as neighbours and
     * keep their places, equal times count as in order, and once the spot
     * moves one way it never turns back.
     */
    public function moveIntoTimeOrder(): void
    {
        $visit_date = $this->visit_date->toDateString();
        $day = $this->itinerary->spotsOn($visit_date)->orderBy('sort_order')->get(['id', 'start_time']);
        $position = $day->search(fn (self $spot) => $spot->id === $this->id);
        $start_time = $day[$position]->start_time;
        $others = $day->reject(fn (self $spot) => $spot->id === $this->id)->values();

        $last_timed_before = fn (int $position): ?int => $others->take($position)->whereNotNull('start_time')->keys()->last();
        $first_timed_from = fn (int $position): ?int => $others->slice($position)->whereNotNull('start_time')->keys()->first();

        $moved_earlier = false;

        while (($previous = $last_timed_before($position)) !== null && $others[$previous]->start_time > $start_time) {
            $position = $previous;
            $moved_earlier = true;
        }

        if (! $moved_earlier) {
            while (($next = $first_timed_from($position)) !== null && $others[$next]->start_time < $start_time) {
                $position = $next + 1;
            }
        }

        $spot_ids = $others->pluck('id')->all();
        array_splice($spot_ids, $position, 0, [$this->id]);

        $this->itinerary->reorderSpots($visit_date, $spot_ids);
    }
}
