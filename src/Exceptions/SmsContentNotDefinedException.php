<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * @internal
 *
 * Exception thrown when attempting to send an SMS without setting its content.
 */
final class SmsContentNotDefinedException extends LogicException {}
