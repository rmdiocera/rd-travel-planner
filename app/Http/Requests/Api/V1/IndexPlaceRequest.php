<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPlaceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        $fail("The {$attribute} field must be valid UTF-8 text.");
                    }
                },
            ],
        ];
    }
}
