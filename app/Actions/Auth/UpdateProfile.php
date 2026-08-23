<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Services\RecordSecurityEvent;

class UpdateProfile
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Apply a validated profile update, recording password changes as
     * security events (FR-AUTH-009).
     *
     * @param  array{first_name?: string, last_name?: string, phone?: ?string, password?: ?string}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        $changesPassword = isset($data['password']) && $data['password'] !== null && $data['password'] !== '';

        $user->fill([
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
        ]);

        if ($changesPassword) {
            $user->password = (string) $data['password'];
        }

        $user->save();

        if ($changesPassword) {
            ($this->recordSecurityEvent)($user, 'password_changed', SecuritySeverity::Info);
        }

        return $user->refresh();
    }
}
