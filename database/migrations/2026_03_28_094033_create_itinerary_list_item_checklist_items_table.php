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
        Schema::create('itinerary_list_item_checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('itinerary_list_item_id')->constrained('itinerary_list_items', 'id', 'ili_checklist_items_fk')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_checked')->default(false);
            $table->tinyInteger('sort_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_list_item_checklist_items');
    }
};
