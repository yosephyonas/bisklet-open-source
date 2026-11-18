<?php

namespace App\Services\Fyda\Dtos;

use App\Services\Fyda\Exceptions\FydaException;

class FydaIdentity
{
    public function __construct(
        public readonly string $nationalId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $fatherName,
        public readonly string $dateOfBirth,
        public readonly ?string $gender,
        public readonly ?string $phone,
        public readonly ?string $email = null,
        public readonly ?array $address = null,
        public readonly ?array $raw = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        $nationalId = $payload['national_id']
            ?? $payload['nationalId']
            ?? $payload['individual_id']
            ?? $payload['individualId']
            ?? $payload['fayda_id']
            ?? $payload['faydaId']
            ?? $payload['sub']
            ?? null;

        if ($nationalId === null || $nationalId === '') {
            throw new FydaException('Fayda userinfo did not include a usable national identifier.');
        }

        return new self(
            nationalId: (string) $nationalId,
            firstName: $payload['first_name'] ?? $payload['given_name'] ?? self::namePart($payload, 0),
            lastName: $payload['last_name'] ?? $payload['family_name'] ?? self::namePart($payload, 1),
            fatherName: $payload['father_name'] ?? $payload['middle_name'] ?? null,
            dateOfBirth: $payload['date_of_birth'] ?? $payload['birthdate'] ?? $payload['dob'] ?? '',
            gender: $payload['gender'] ?? null,
            phone: $payload['phone'] ?? $payload['phone_number'] ?? null,
            email: $payload['email'] ?? null,
            address: $payload['address'] ?? null,
            raw: $payload,
        );
    }

    public function toArray(): array
    {
        return [
            'national_id' => $this->nationalId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'father_name' => $this->fatherName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
        ];
    }

    private static function namePart(array $payload, int $index): string
    {
        $name = (string) ($payload['name'] ?? $payload['name#en'] ?? $payload['name#am'] ?? '');

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($index === 0) {
            return $parts[0] ?? '';
        }

        return implode(' ', array_slice($parts, 1));
    }
}
