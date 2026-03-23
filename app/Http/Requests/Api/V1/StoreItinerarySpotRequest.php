<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItinerarySpotRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'place_id' => [
                'required',
                'string',
                'exists:places,id',
                Rule::unique('itinerary_spots')->where('itinerary_id', $this->route('itinerary')->id),
            ],
            'visit_date' => ['required', 'date'],
        ];
    }
}
