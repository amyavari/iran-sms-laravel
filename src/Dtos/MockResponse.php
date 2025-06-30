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
        private string|int $errorCode,
        private bool $shouldThrow,
    ) {}

    /**
     * Get a mock configuration for successful SMS sending in tests.
     */
    public static function successful(): self
    {
        return new self(isSuccessful: true, errorMessage: '', errorCode: '', shouldThrow: false);
    }

    /**
     * Get a mock configuration for failed SMS sending in tests.
     */
    public static function failed(string $errorMessage, string|int $errorCode): self
    {
        return new self(isSuccessful: false, errorMessage: $errorMessage, errorCode: $errorCode, shouldThrow: false);
    }

    /**
     * Get a mock configuration for throwing an exception during SMS sending in tests.
     */
    public static function throw(): self
    {
        return new self(isSuccessful: false, errorMessage: '', errorCode: '', shouldThrow: true);
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

    /**
     * User-defined error code for failed SMS sending.
     */
    public function errorCode(): string|int
    {
        return $this->errorCode;
    }
}
