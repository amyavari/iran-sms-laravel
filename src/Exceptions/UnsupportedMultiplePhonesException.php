<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * Throw exception if driver does not support sending SMS to multiple phones.
 */
final class UnsupportedMultiplePhonesException extends LogicException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Make instance of this exception with prepared message
     */
    public static function make(string $driver): self
    {
        $message = sprintf('Provider "%s" only supports sending pattern SMS to one phone number at a time.', $driver);

        return new self($message);
    }
}
