<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Fixtures;

use AliYavari\IranSms\Abstracts\Driver;

/**
 * Test fixture class for the `Driver` abstract class.
 *
 * This class implements the required methods expected by
 * the `Driver` abstract class for isolated testing purposes.
 */
final class ConcreteTestDriver extends Driver
{
    public array $dataToAssert; // To test

    public function __construct(
        private readonly string $from,
        private readonly bool $successful,
    ) {}

    public function credit(): int
    {
        return 0;
    }

    protected function getDefaultSender(): string
    {
        return $this->from;
    }

    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->dataToAssert = [
            'type' => 'otp',
            'phone' => $phone,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->dataToAssert = [
            'type' => 'pattern',
            'phones' => $phones,
            'code' => $patternCode,
            'variables' => $variables,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendText(array $phones, string $message, string $from): static
    {
        $this->dataToAssert = [
            'type' => 'text',
            'phones' => $phones,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function isSuccessful(): bool
    {
        return $this->successful;
    }

    protected function getErrorMessage(): string
    {
        return 'Test error message';
    }

    protected function getErrorCode(): string|int
    {
        return 40;
    }
}
