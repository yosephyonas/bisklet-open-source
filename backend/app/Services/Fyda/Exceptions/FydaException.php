<?php

namespace App\Services\Fyda\Exceptions;

use RuntimeException;

class FydaException extends RuntimeException
{
    public static function verificationFailed(string $nationalId): self
    {
        return new self("FYDA verification failed for national id {$nationalId}.");
    }

    public static function serviceUnavailable(): self
    {
        return new self('FYDA service is currently unavailable.');
    }

    public static function authorizationExpired(): self
    {
        return new self('FYDA authorization session expired. Please try again.');
    }
}
