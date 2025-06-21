<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Dtos\MockResponse;
use Illuminate\Support\Facades\Http;

final class FakeDriver extends Driver
{
    public function __construct(private readonly MockResponse $response) {}

    /**
     * Get the default sender number from config
     */
    protected function getDefaultSender(): string
    {
        return '';
    }

    /**
     * Send OTP SMS
     */
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * Send pattern SMS
     *
     * @param  list<string>  $phones
     * @param  array<string, mixed>  $variables
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * Send regular text SMS
     *
     * @param  list<string>  $phones
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $this->fakeHttpExceptionIfRequired();

        return $this;
    }

    /**
     * Check if SMS sending was successful
     */
    protected function isSuccessful(): bool
    {
        return $this->response->isSuccessful();
    }

    /**
     * Get the error message if SMS sending failed
     */
    protected function getErrorMessage(): string
    {
        return $this->response->errorMessage();
    }

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
