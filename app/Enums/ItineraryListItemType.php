<?php

declare(strict_types=1);

namespace App\Enums;

enum ItineraryListItemType: string
{
    case Place = 'place';
    case Checklist = 'checklist';
    case Note = 'note';
}
