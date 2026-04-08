<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItineraryListItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:place,checklist,note'],
            'start_time' => ['required_if:type,place', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:type,place', 'nullable', 'date_format:H:i', 'after:start_time'],
            'marked_visited' => ['required_if:type,place', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_checked' => ['boolean'],
            'checklist_item_id' => ['nullable', 'string', Rule::exists('itinerary_list_item_checklist_items', 'id')->where('itinerary_list_item_id', $this->item->id)],
            'content' => ['required_if:type,note', 'nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
