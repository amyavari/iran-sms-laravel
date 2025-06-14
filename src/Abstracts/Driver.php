<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Abstracts;

use AliYavari\IranSms\Contracts\Sms;

abstract class Driver implements Sms
{
    /**
     * Get the default sender number from config
     */
    abstract protected function getDefaultSender(): string;

    /**
     * Send OTP SMS
     */
    abstract protected function sendOtp(string $phone, string $message, string $from): static;

    /**
     * Send pattern SMS
     *
     * @param  list<string>  $phones
     * @param  array<string, mixed>  $variables
     */
    abstract protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static;

    /**
     * Send regular text SMS
     *
     * @param  list<string>  $phones
     */
    abstract protected function sendText(array $phones, string $message, string $from): static;

    /**
     * Check if SMS sending was successful
     */
    abstract protected function isSuccessful(): bool;

    /**
     * Get the error message if SMS sending failed
     */
    abstract protected function getErrorMessage(): string;
}
