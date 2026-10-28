<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Fixtures;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Enums\Type;
use Exception;

/**
 * @internal
 *
 * Test class used for testing the abstract `Driver` class methods.
 */
final class TestDriver extends Driver
{
    private ?string $calledMethod = null;

    /**
     * @var array<string, mixed>
     */
    private array $receivedParameters = [];

    private bool $isSuccessful = true;

    private int $errorCode = 0;

    private string $errorMessage = '';

    private ?Exception $exception = null;

    /**
     * Test-only helper method.
     *
     * Driver should return a successful response.
     */
    public function asSuccessful(): self
    {
        $this->isSuccessful = true;

        return $this;
    }

    /**
     * Test-only helper method.
     *
     * Driver should return a failed response.
     */
    public function asFailed(): self
    {
        $this->isSuccessful = false;

        $this->errorCode = 12;
        $this->errorMessage = 'خطایی رخ داد.';

        return $this;
    }

    /**
     * Test-only helper method.
     *
     * Driver should throw the exception while sending.
     */
    public function throwing(Exception $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * Test-only helper method.
     *
     * Prepares a sample SMS of the given type through the public API.
     */
    public function prepare(Type $type): self
    {
        return match ($type) {
            Type::Otp => $this->otp('09123456789', 'OTP message'),
            Type::Pattern => $this->pattern('09123456789', 'pattern_code', ['name' => 'Ali']),
            Type::Text => $this->text('09123456789', 'Text message'),
        };
    }

    /**
     * Test-only helper method.
     *
     * Returns the child class method that was invoked.
     */
    public function calledMethod(): ?string
    {
        return $this->calledMethod;
    }

    /**
     * Test-only helper method.
     *
     * Returns parameters received by the last invoked child method.
     */
    public function receivedParameters(?string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->receivedParameters;
        }

        return $this->receivedParameters[$key];
    }

    public function credit(): int
    {
        return 100;
    }

    protected function getDefaultSender(): string
    {
        return '5000555';
    }

    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->throwExceptionIfConfigured();

        $this->calledMethod = 'sendOtp';
        $this->receivedParameters = [
            'phone' => $phone,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->throwExceptionIfConfigured();

        $this->calledMethod = 'sendPattern';
        $this->receivedParameters = [
            'phones' => $phones,
            'patternCode' => $patternCode,
            'variables' => $variables,
            'from' => $from,
        ];

        return $this;
    }

    protected function sendText(array $phones, string $message, string $from): static
    {
        $this->throwExceptionIfConfigured();

        $this->calledMethod = 'sendText';
        $this->receivedParameters = [
            'phones' => $phones,
            'message' => $message,
            'from' => $from,
        ];

        return $this;
    }

    protected function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    protected function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    protected function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Test-only helper method.
     */
    private function throwExceptionIfConfigured(): void
    {
        if ($this->exception instanceof Exception) {
            throw $this->exception;
        }
    }
}
