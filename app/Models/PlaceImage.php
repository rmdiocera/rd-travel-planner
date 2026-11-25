<?php

namespace App\Models;

use Database\Factories\PlaceImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaceImage extends Model
{
    /** @use HasFactory<PlaceImageFactory> */
    use HasFactory;

    protected $fillable = ['path'];
}
