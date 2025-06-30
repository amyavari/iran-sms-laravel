<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * @internal
 *
 * Exception thrown when attempting to check the SMS sending status before the message has been sent.
 */
final class SmsNotSentYetException extends LogicException {}
