<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItineraryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date', 'required_with:end_date', 'present_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'present_with:start_date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
