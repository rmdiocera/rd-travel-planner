<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The composite unique index is the only index backing the itinerary_id
     * foreign key, so a plain index has to exist before it can be dropped.
     */
    public function up(): void
    {
        Schema::table('itinerary_spots', function (Blueprint $table) {
            $table->index('itinerary_id');
            $table->dropUnique(['itinerary_id', 'place_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The unique index is restored first, so a rollback with repeated places
     * fails before the itinerary_id index is touched.
     */
    public function down(): void
    {
        Schema::table('itinerary_spots', function (Blueprint $table) {
            $table->unique(['itinerary_id', 'place_id']);
            $table->dropIndex(['itinerary_id']);
        });
    }
};
