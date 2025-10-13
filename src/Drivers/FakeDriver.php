<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Dtos\MockResponse;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * Fake SMS driver used to simulate sending behavior during tests.
 */
final class FakeDriver extends Driver
{
    public function __construct(private readonly MockResponse $response) {}

    /**
     * {@inheritdoc}
     */
    public function credit(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSender(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->response->isSuccessful();
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return $this->response->errorMessage();
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): string|int
    {
        return $this->response->errorCode();
    }

    /**
     * Throw a connection exception if defined by the user.
     */
    private function fakeHttpExceptionIfRequired(): void
    {
        if ($this->response->shouldThrow()) {
            Http::fake([
                'sms/fake/driver' => Http::failedConnection(),
            ]);

            Http::get('sms/fake/driver');
        }
    }
}
