<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Contracts;

interface Sms
{
    /**
     * Create OTP SMS instance
     */
    public function otp(string $phone, string $message): static;

    /**
     * Create Pattern SMS instance
     *
     * @param  string|list<string>  $phones
     * @param  array<string, mixed>  $variables
     */
    public function pattern(string|array $phones, string $patternCode, array $variables): static;

    /**
     * Create regular text SMS instance
     *
     * @param  string|list<string>  $phones
     */
    public function text(string|array $phones, string $message): static;

    /**
     * Set the sender number for the SMS
     */
    public function from(string $from): static;

    /**
     * Send the SMS
     */
    public function send(): static;

    /**
     * Specify whether to log all SMS types
     */
    public function log(bool $log = true): static;

    /**
     * Specify whether to log OTP messages
     */
    public function logOtp(bool $log = true): static;

    /**
     * Specify whether to log pattern messages
     */
    public function logPattern(bool $log = true): static;

    /**
     * Specify whether to log text messages
     */
    public function logText(bool $log = true): static;

    /**
     * Log only successful SMS messages
     */
    public function logSuccessful(): static;

    /**
     * Log only failed SMS messages
     */
    public function logFailed(): static;

    /**
     * Check if SMS sending was successful
     */
    public function successful(): bool;

    /**
     * Check if SMS sending failed
     */
    public function failed(): bool;

    /**
     * Get the error message if SMS sending failed
     */
    public function error(): ?string;
}
