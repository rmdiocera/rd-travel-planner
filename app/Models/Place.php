<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'details',
        'address',
        'country',
        'city',
        'website',
        'phone',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'place_tags')
            ->using(PlaceTag::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PlaceImage::class, 'place_id');
    }

    public function spots(): HasMany
    {
        return $this->hasMany(ItinerarySpot::class);
    }

    /**
     * Keep places whose name or city contains every word of the term, in any order.
     *
     * Results are ranked: names starting with the whole term first, then names
     * containing it, then places that only match on city, each group by name.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = Str::squish($term);

        foreach (explode(' ', $term) as $word) {
            $query->whereAny(['name', 'city'], 'like', '%'.$this->escapeLike($word).'%');
        }

        $escapedTerm = $this->escapeLike($term);

        $query->orderByRaw(
            'CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
            ["{$escapedTerm}%", "%{$escapedTerm}%"],
        )->orderBy('name');
    }

    /**
     * Escape LIKE wildcards so they match literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
