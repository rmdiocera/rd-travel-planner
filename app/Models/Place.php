<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    /** @use HasFactory<\Database\Factories\PlaceFactory> */
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
}
