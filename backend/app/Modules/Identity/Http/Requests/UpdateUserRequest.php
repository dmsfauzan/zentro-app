<?php

namespace App\Modules\Identity\Http\Requests;

use App\Enums\UserStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RolePermissionSeeder::ROLES)],
            'status' => ['sometimes', 'string', Rule::in(UserStatus::values())],
        ];
    }
}
