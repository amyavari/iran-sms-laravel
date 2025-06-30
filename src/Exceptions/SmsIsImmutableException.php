<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * @internal
 *
 * Exception thrown when attempting to modify the content of an SMS instance.
 */
final class SmsIsImmutableException extends LogicException {}
