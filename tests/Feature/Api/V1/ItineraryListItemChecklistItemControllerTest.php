<?php

declare(strict_types=1);

use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use App\Models\User;

test('unauthenticated users cannot access checklist item endpoints', function () {
    $itinerary = Itinerary::factory()->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist']);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create();

    $this->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/reorder")->assertUnauthorized();
    $this->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}")->assertUnauthorized();
});

test('user can add an item to their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->assertTrue($user->can('create', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist", [
            'label' => 'Fushimi Inari',
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Fushimi Inari')
        ->assertJsonPath('data.sort_order', 1);

    expect($item->checklistItems()->count())->toBe(1);
});

test('user can add multiple items to their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);

    $this->assertTrue($user->can('create', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist", [
            'label' => 'Fushimi Inari',
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Fushimi Inari')
        ->assertJsonPath('data.sort_order', 1);

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist", [
            'label' => 'Himeji Castle',
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Himeji Castle')
        ->assertJsonPath('data.sort_order', 2);

    expect($item->checklistItems()->count())->toBe(2);
});

test('user cannot add a checklist item to another user\'s list item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist']);

    $this->assertFalse($user->can('create', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->postJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist", [
            'label' => 'Fushimi Inari',
        ])
        ->assertForbidden();
});

test('user can change the label of an item on their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->assertTrue($user->can('update', [$checklist_item, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'label' => 'Himeji Castle',
        ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Himeji Castle');

    expect($checklist_item->refresh()->label)->toBe('Himeji Castle');
});

test('user can toggle an item on their checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->assertTrue($user->can('update', [$checklist_item, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'is_checked' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_checked', true);

    expect($checklist_item->refresh()->is_checked)->toBeTrue();

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'is_checked' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_checked', false);

    expect($checklist_item->refresh()->is_checked)->toBeFalse();
});

test('is_checked is prohibited when updating a checklist item label', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist', 'sort_order' => 1]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'label' => 'Himeji Castle',
            'is_checked' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_checked']);
});

test('label is prohibited when toggling a checklist item', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist', 'sort_order' => 1]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create(['sort_order' => 1]);

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'is_checked' => true,
            'label' => 'Fushimi Inari',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['label']);
});

test('user cannot update a checklist item on another user\'s list item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist']);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Original Label',
    ]);

    $this->assertFalse($user->can('update', [$checklist_item, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}", [
            'label' => 'Hijacked Label',
        ])
        ->assertForbidden();
});

test('user can reorder checklist items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    [$first, $second, $third] = ItineraryListItemChecklistItem::factory()->for($item, 'item')->createMany([
        ['label' => 'Fushimi Inari', 'sort_order' => 1],
        ['label' => 'Himeji Castle', 'sort_order' => 2],
        ['label' => 'Kiyomizu-dera', 'sort_order' => 3],
    ]);

    $this->assertTrue($user->can('reorder', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/reorder", [
            'checklist_item_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertNoContent();

    expect($third->refresh()->sort_order)->toBe(1);
    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(3);
});

test('user cannot reorder checklist items on another user\'s list item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist']);
    [$first, $second] = ItineraryListItemChecklistItem::factory()->for($item, 'item')->createMany([
        ['label' => 'First Item', 'sort_order' => 1],
        ['label' => 'Second Item', 'sort_order' => 2],
    ]);

    $this->assertFalse($user->can('reorder', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->patchJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/reorder", [
            'checklist_item_ids' => [$second->id, $first->id],
        ])
        ->assertForbidden();
});

test('user can delete an item from a checklist', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create([
        'label' => 'Fushimi Inari',
        'sort_order' => 1,
    ]);

    $this->assertTrue($user->can('delete', [$checklist_item, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}")
        ->assertNoContent();

    expect($checklist_item->fresh())->toBeNull();
});

test('deleting a checklist item reorders remaining checklist items', function () {
    $user = User::factory()->create();
    $itinerary = Itinerary::factory()->for($user)->create();
    $list = ItineraryList::factory()->for($itinerary)->create(['sort_order' => 1]);
    $item = ItineraryListItem::factory()->for($list)->create([
        'type' => 'checklist',
        'sort_order' => 1,
    ]);
    [$first, $second, $third] = ItineraryListItemChecklistItem::factory()->for($item, 'item')->createMany([
        ['label' => 'Fushimi Inari', 'sort_order' => 1],
        ['label' => 'Himeji Castle', 'sort_order' => 2],
        ['label' => 'Kiyomizu-dera', 'sort_order' => 3],
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$first->id}")
        ->assertNoContent();

    expect($first->fresh())->toBeNull();
    expect($second->refresh()->sort_order)->toBe(1);
    expect($third->refresh()->sort_order)->toBe(2);
});

test('user cannot delete a checklist item from another user\'s list item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $itinerary = Itinerary::factory()->for($other)->create();
    $list = ItineraryList::factory()->for($itinerary)->create();
    $item = ItineraryListItem::factory()->for($list)->create(['type' => 'checklist']);
    $checklist_item = ItineraryListItemChecklistItem::factory()->for($item, 'item')->create();

    $this->assertFalse($user->can('delete', [$checklist_item, $itinerary, $list, $item]));

    $this->actingAs($user)
        ->deleteJson("/api/v1/itineraries/{$itinerary->id}/lists/{$list->id}/items/{$item->id}/checklist/{$checklist_item->id}")
        ->assertForbidden();
});
