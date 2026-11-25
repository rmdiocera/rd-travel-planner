<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('itinerary_spots', function (Blueprint $table) {
            $table->unsignedTinyInteger('sort_order')->after('marked_visited');
        });

        $this->backfillSortOrder();
    }

    /**
     * Number each day's spots 1..n in the order the grouped view showed them
     * before sort_order existed: untimed spots first, then by start time,
     * with ties in creation order.
     *
     * Public so a test can run the backfill without the schema change, which
     * would commit the test's transaction.
     */
    public function backfillSortOrder(): void
    {
        DB::table('itinerary_spots')
            ->orderBy('itinerary_id')
            ->orderBy('visit_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get(['id', 'itinerary_id', 'visit_date'])
            ->groupBy(fn (object $spot) => "{$spot->itinerary_id}|{$spot->visit_date}")
            ->each(fn (Collection $day) => $day->each(
                fn (object $spot, int $index) => DB::table('itinerary_spots')
                    ->where('id', $spot->id)
                    ->update(['sort_order' => $index + 1])
            ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary_spots', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
