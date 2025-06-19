<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Fixtures;

use AliYavari\IranSms\Abstracts\Driver;

final class TestDriver extends Driver
{
    public string $whatIsCalled = ''; // To test

    public array $receivedArguments; // To test

    public function __construct(
        private readonly string $from,
        private readonly bool $successful,
    ) {}

    protected function getDefaultSender(): string
    {
        $this->whatIsCalled = 'getDefaultSender';

        return $this->from;
    }

    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->whatIsCalled = 'sendOtp';
        $this->receivedArguments = [
            'phone' => $phone,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->whatIsCalled = 'sendPattern';
        $this->receivedArguments = [
            'phones' => $phones,
            'code' => $patternCode,
            'variables' => $variables,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendText(array $phones, string $message, string $from): static
    {
        $this->whatIsCalled = 'sendText';
        $this->receivedArguments = [
            'phones' => $phones,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function isSuccessful(): bool
    {
        $this->whatIsCalled = 'isSuccessful';

        return $this->successful;
    }

    protected function getErrorMessage(): string
    {
        $this->whatIsCalled = 'getErrorMessage';

        return 'Test error message';
    }
}
