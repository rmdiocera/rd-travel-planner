<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailAndLoginRequest extends FormRequest
{
    private ?User $verifiableUser = null;

    /**
     * Determine if the link's hash matches the email of the user it was issued for.
     */
    public function authorize(): bool
    {
        $this->verifiableUser = User::query()->find($this->route('id'));

        return $this->verifiableUser !== null
            && hash_equals(sha1($this->verifiableUser->getEmailForVerification()), (string) $this->route('hash'));
    }

    /**
     * Get the user the link was issued for. Only available once the request is authorized.
     */
    public function verifiableUser(): User
    {
        return $this->verifiableUser;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
