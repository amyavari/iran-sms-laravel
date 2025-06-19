<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * Throw exception if user wants to modify SMS content on the SMS instance.
 */
final class SmsIsImmutableException extends LogicException {}
