<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * Throw exception if driver does not support the method to send SMS.
 */
final class UnsupportedMethodException extends LogicException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Make instance of this exception with prepared message
     *
     * @param  'otp'|'text'|'pattern'  $method
     * @param  'otp'|'text'|'pattern'  $alternative
     */
    public static function make(string $driver, string $method, string $alternative): self
    {
        $message = sprintf('Provider "%s" does not support sending "%s" message, please use "%s" method instead.', $driver, $method, $alternative);

        return new self($message);
    }
}
