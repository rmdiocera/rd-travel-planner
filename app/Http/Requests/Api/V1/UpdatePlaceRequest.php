<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Place;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Place::class)->ignore($this->route('place'))],
            'details' => ['required', 'string'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id', 'distinct'],
        ];
    }
}
