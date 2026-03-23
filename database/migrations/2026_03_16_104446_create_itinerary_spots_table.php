<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('itinerary_spots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('itinerary_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('place_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('marked_visited')->default(false);
            $table->timestamps();

            $table->unique(['itinerary_id', 'place_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_spots');
    }
};
