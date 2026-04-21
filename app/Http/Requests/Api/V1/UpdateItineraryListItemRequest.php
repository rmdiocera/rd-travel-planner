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
        $isPlace = $this->type === 'place';
        $isChecklist = $this->type === 'checklist';
        $isNote = $this->type === 'note';

        return [
            'type' => ['required', 'string', 'in:place,checklist,note'],

            // Place fields
            'start_time' => [
                Rule::requiredIf($isPlace && ! $this->has('marked_visited')),
                'required_with:end_time',
                'nullable',
                'date_format:H:i',
                Rule::prohibitedIf(! $isPlace),
            ],
            'end_time' => [
                Rule::requiredIf($isPlace && ! $this->has('marked_visited')),
                'required_with:start_time',
                'nullable',
                'date_format:H:i',
                'after:start_time',
                Rule::prohibitedIf(! $isPlace),
            ],
            'marked_visited' => [
                Rule::requiredIf($isPlace && ! $this->has('start_time') && ! $this->has('end_time')),
                'nullable',
                'boolean',
                Rule::prohibitedIf(! $isPlace),
            ],

            // Checklist fields
            'title' => [
                'nullable',
                'string',
                'max:255',
                Rule::prohibitedIf(! $isChecklist),
            ],

            // Note fields
            'content' => [
                'required_if:type,note',
                'nullable',
                'string',
                Rule::prohibitedIf(! $isNote),
            ],

            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
