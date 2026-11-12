<?php

namespace App\Modules\Identity\Actions;

use App\Enums\UserStatus;
use App\Modules\Identity\DTO\FirebaseIdentity;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Spatie\Permission\Models\Role;

/**
 * Menyinkronkan identitas Firebase yang sudah TERVERIFIKASI ke tabel users.
 *
 * - User dicari berdasarkan firebase_uid (bukan email, bukan input klien).
 * - User baru otomatis diberi role `customer`.
 * - Email yang sudah terhubung ke akun lain ditolak (tidak ada auto-merge).
 */
final class SyncFirebaseUserAction
{
    public const DEFAULT_ROLE = 'customer';

    public function execute(FirebaseIdentity $identity): User
    {
        $user = User::withTrashed()->where('firebase_uid', $identity->uid)->first();

        if ($user instanceof User) {
            if ($user->trashed()) {
                throw new ApiException(
                    'This account has been deactivated.',
                    403,
                    ErrorCode::AuthAccountDisabled,
                );
            }

            $this->enforceActiveStatus($user);
            $this->syncExisting($user, $identity);

            return $user;
        }

        $this->guardEmailConflict($identity);

        $user = User::create([
            'firebase_uid' => $identity->uid,
            'name' => $identity->name ?: $this->fallbackName($identity),
            'email' => $identity->email,
            'phone' => null,
            'status' => UserStatus::Active,
            'email_verified_at' => $identity->emailVerified ? now() : null,
            'last_login_at' => now(),
        ]);

        $this->assignDefaultRole($user);

        return $user;
    }

    private function syncExisting(User $user, FirebaseIdentity $identity): void
    {
        if ($identity->email !== null && $identity->email !== $user->email) {
            $this->guardEmailConflict($identity, exceptUserId: $user->getKey());
            $user->email = $identity->email;
        }

        if ($identity->name !== null && $identity->name !== '' && $identity->name !== $user->name) {
            $user->name = $identity->name;
        }

        if ($identity->emailVerified && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->last_login_at = now();
        $user->save();

        if ($user->roles()->doesntExist()) {
            $this->assignDefaultRole($user);
        }
    }

    private function enforceActiveStatus(User $user): void
    {
        if (! $user->isActive()) {
            throw new ApiException(
                'Your account is not active.',
                403,
                ErrorCode::AuthAccountDisabled,
            );
        }
    }

    private function guardEmailConflict(FirebaseIdentity $identity, ?int $exceptUserId = null): void
    {
        if ($identity->email === null) {
            throw new ApiException('Firebase account has no email address.', 422, ErrorCode::ValidationFailed);
        }

        $exists = User::withTrashed()
            ->where('email', $identity->email)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();

        if ($exists) {
            throw new ApiException(
                'This email is already linked to another account.',
                409,
                ErrorCode::EmailAlreadyLinked,
            );
        }
    }

    private function assignDefaultRole(User $user): void
    {
        if (Role::query()->where('name', self::DEFAULT_ROLE)->where('guard_name', 'web')->exists()) {
            $user->assignRole(self::DEFAULT_ROLE);
        }
    }

    private function fallbackName(FirebaseIdentity $identity): string
    {
        if ($identity->email !== null) {
            return (string) str($identity->email)->before('@');
        }

        return 'Zentro User';
    }
}
