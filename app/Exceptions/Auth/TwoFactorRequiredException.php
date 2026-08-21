<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class TwoFactorRequiredException extends RuntimeException
{
    /**
     * The single-use token that completes the pending login challenge.
     */
    public string $challengeToken;

    /**
     * Create a two-factor challenge requirement for the pending login.
     */
    public static function withChallengeToken(string $challengeToken): self
    {
        $exception = new self(__('Two-factor authentication code required.'));

        $exception->challengeToken = $challengeToken;

        return $exception;
    }
}
