<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Resources\AdminUserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $users = User::query()
            ->with(['roles:id,name', 'seller:id,user_id,store_name,status'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas(
                'roles',
                fn ($roles) => $roles->where('name', $request->query('role')),
            ))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $users,
            fn (User $user): array => (new AdminUserResource($user))->resolve(),
            'Daftar pengguna berhasil diambil.',
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor !== null && (int) $actor->getKey() === (int) $user->getKey()) {
            throw new ApiException(
                'Tidak dapat mengubah role atau status akun sendiri.',
                422,
                ErrorCode::ValidationFailed,
            );
        }

        $data = $request->validated();

        if ($data === []) {
            throw new ApiException('Tidak ada perubahan yang dikirim.', 422, ErrorCode::ValidationFailed);
        }

        $before = [
            'roles' => $user->getRoleNames()->all(),
            'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
        ];

        if (array_key_exists('roles', $data)) {
            $this->guardLastSuperAdmin($user, $data['roles']);
            $user->syncRoles($data['roles']);
        }

        if (array_key_exists('status', $data)) {
            $status = UserStatus::from((string) $data['status']);
            $this->guardDeactivatingSuperAdmin($user, $status);
            $user->status = $status;
        }

        $user->save();

        AuditLog::record(
            'user.updated',
            'user',
            (int) $user->getKey(),
            $before,
            [
                'roles' => $user->getRoleNames()->all(),
                'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
            ],
            $actor?->getKey(),
        );

        return ApiResponse::success(
            (new AdminUserResource($user->fresh(['roles', 'seller'])))->resolve(),
            'Pengguna berhasil diperbarui.',
        );
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function guardLastSuperAdmin(User $user, array $roles): void
    {
        if (! $user->hasRole('super_admin') || in_array('super_admin', $roles, true)) {
            return;
        }

        if (User::role('super_admin')->count() <= 1) {
            throw new ApiException(
                'Minimal harus ada satu super admin.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }
    }

    private function guardDeactivatingSuperAdmin(User $user, UserStatus $status): void
    {
        if (! $user->hasRole('super_admin') || $status === UserStatus::Active) {
            return;
        }

        $activeSuperAdmins = User::role('super_admin')
            ->where('status', UserStatus::Active->value)
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw new ApiException(
                'Minimal harus ada satu super admin aktif.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }
    }
}
