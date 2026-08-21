<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    /**
     * Create a customer account from normalized registration data.
     *
     * @param  array{first_name: string, last_name: string, email: string, password: string, phone?: ?string}  $data
     */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'status' => UserStatus::Active,
                'password' => Hash::make($data['password']),
            ]);

            event(new Registered($user));

            return $user;
        });
    }
}
