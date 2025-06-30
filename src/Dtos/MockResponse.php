<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Dtos;

/**
 * Represents a mocked response used in tests.
 */
final readonly class MockResponse
{
    private function __construct(
        private bool $isSuccessful,
        private string $errorMessage,
        private bool $shouldThrow,
    ) {}

    /**
     * Get a mock configuration for successful SMS sending in tests.
     */
    public static function successful(): self
    {
        return new self(isSuccessful: true, errorMessage: '', shouldThrow: false);
    }

    /**
     * Get a mock configuration for failed SMS sending in tests.
     */
    public static function failed(string $errorMessage): self
    {
        return new self(isSuccessful: false, errorMessage: $errorMessage, shouldThrow: false);
    }

    /**
     * Get a mock configuration for throwing an exception during SMS sending in tests.
     */
    public static function throw(): self
    {
        return new self(isSuccessful: false, errorMessage: '', shouldThrow: true);
    }

    /**
     * Whether the user defined the sending to be successful.
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * Whether the user defined an exception to be thrown.
     */
    public function shouldThrow(): bool
    {
        return $this->shouldThrow;
    }

    /**
     * User-defined error message for failed SMS sending.
     */
    public function errorMessage(): string
    {
        return $this->errorMessage;
    }
}
