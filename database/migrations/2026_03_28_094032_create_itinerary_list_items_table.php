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
        Schema::create('itinerary_list_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('itinerary_lists_id')->constrained('itinerary_lists')->cascadeOnDelete();
            $table->enum('type', ['place', 'checklist', 'note']);
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_list_items');
    }
};
