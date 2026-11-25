<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\ItinerarySpot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderItinerarySpotsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * This runs before validation, so a non-owner gets a 403 before the
     * day check below can reveal anything about the itinerary's spots.
     */
    public function authorize(): bool
    {
        return $this->user()->can('reorder', [ItinerarySpot::class, $this->route('itinerary')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'spot_ids' => ['required', 'array', 'list'],
            'spot_ids.*' => ['required', 'string', 'distinct'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $spotIds = collect($this->input('spot_ids'))->sort()->values()->all();
                $daySpotIds = $this->route('itinerary')
                    ->spotsOn($this->input('visit_date'))
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

                if ($spotIds !== $daySpotIds) {
                    $validator->errors()->add('spot_ids', 'The spot ids must list every spot on this day exactly once.');
                }
            },
        ];
    }
}
