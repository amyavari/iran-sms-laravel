<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Dtos;

final readonly class MockResponse
{
    private function __construct(
        private bool $isSuccessful,
        private string $errorMessage,
        private bool $shouldThrow,
    ) {}

    public static function successful(): self
    {
        return new self(isSuccessful: true, errorMessage: '', shouldThrow: false);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(isSuccessful: false, errorMessage: $errorMessage, shouldThrow: false);
    }

    public static function throw(): self
    {
        return new self(isSuccessful: false, errorMessage: '', shouldThrow: true);
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    public function shouldThrow(): bool
    {
        return $this->shouldThrow;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }
}
