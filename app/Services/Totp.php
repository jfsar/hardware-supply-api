<?php

namespace App\Services;

use RuntimeException;

/**
 * RFC 6238 time-based one-time password generation and verification
 * with an RFC 4648 Base32 codec, SHA-1, a 30 second step, and 6 digits.
 */
class Totp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new random shared secret encoded as Base32.
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->encodeBase32(random_bytes($bytes));
    }

    /**
     * Compute the current TOTP code for a Base32 secret.
     */
    public function code(string $base32Secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        $counter = intdiv($timestamp, 30);

        $binaryCounter = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $binaryCounter, $this->decodeBase32($base32Secret), true);

        $offset = ord(substr($hash, -1)) & 0x0F;

        $truncated = (ord($hash[$offset]) & 0x7F) << 24
            | (ord($hash[$offset + 1]) & 0xFF) << 16
            | (ord($hash[$offset + 2]) & 0xFF) << 8
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-supplied code allowing +/- window steps of clock drift.
     */
    public function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $timestamp = time();

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($base32Secret, $timestamp + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the otpauth:// provisioning URI for authenticator apps.
     */
    public function otpauthUri(string $issuer, string $account, string $base32Secret): string
    {
        $query = http_build_query([
            'secret' => $base32Secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => '6',
            'period' => '30',
        ]);

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($account),
            $query
        );
    }

    /**
     * Encode raw bytes as unpadded Base32.
     */
    public function encodeBase32(string $bytes): string
    {
        if ($bytes === '') {
            throw new RuntimeException('Cannot encode an empty secret.');
        }

        $output = '';
        $bits = 0;
        $buffer = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::BASE32_ALPHABET[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $output .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $output;
    }

    /**
     * Decode an unpadded Base32 string back to raw bytes.
     */
    public function decodeBase32(string $encoded): string
    {
        $normalized = strtoupper(rtrim($encoded, '='));

        if ($normalized === '' || preg_match('/^[A-Z2-7]+$/', $normalized) !== 1) {
            throw new RuntimeException('Invalid Base32 secret.');
        }

        $output = '';
        $bits = 0;
        $buffer = 0;

        foreach (str_split($normalized) as $character) {
            $buffer = ($buffer << 5) | strpos(self::BASE32_ALPHABET, $character);
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }
}
