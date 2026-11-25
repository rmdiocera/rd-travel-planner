<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItinerarySpot;

test('backfill numbers each day in the order the grouped view showed it', function () {
    $itinerary = Itinerary::factory()->create();
    $other_itinerary = Itinerary::factory()->create();
    $create_spot = fn (Itinerary $itinerary, string $visit_date, ?string $start_time) => ItinerarySpot::factory()->for($itinerary)->create([
        'visit_date' => $visit_date,
        'start_time' => $start_time,
        'sort_order' => 0,
    ]);
    $afternoon = $create_spot($itinerary, '2026-06-09', '14:00');
    $first_untimed = $create_spot($itinerary, '2026-06-09', null);
    $morning = $create_spot($itinerary, '2026-06-09', '09:00');
    $second_untimed = $create_spot($itinerary, '2026-06-09', null);
    $next_day = $create_spot($itinerary, '2026-06-10', '10:00');
    $other_itinerary_spot = $create_spot($other_itinerary, '2026-06-09', null);

    $migration = require database_path('migrations/2026_11_25_033304_add_sort_order_to_itinerary_spots_table.php');
    $migration->backfillSortOrder();

    $spot_ids_by_sort_order = fn (Itinerary $itinerary, string $visit_date) => $itinerary->spots()
        ->where('visit_date', $visit_date)
        ->orderBy('sort_order')
        ->pluck('id', 'sort_order')
        ->all();

    expect($spot_ids_by_sort_order($itinerary, '2026-06-09'))
        ->toBe([1 => $first_untimed->id, 2 => $second_untimed->id, 3 => $morning->id, 4 => $afternoon->id]);
    expect($spot_ids_by_sort_order($itinerary, '2026-06-10'))->toBe([1 => $next_day->id]);
    expect($spot_ids_by_sort_order($other_itinerary, '2026-06-09'))->toBe([1 => $other_itinerary_spot->id]);
});
