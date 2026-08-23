<?php

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithSanctum
{
    /**
     * Authenticate subsequent requests as the user via a real Sanctum token.
     */
    protected function actingAsToken(User $user, string $deviceName = 'testing'): static
    {
        $this->withToken($user->createToken($deviceName)->plainTextToken);

        return $this;
    }
}
