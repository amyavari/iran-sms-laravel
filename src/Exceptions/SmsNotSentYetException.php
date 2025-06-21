<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * Throw exception if user wants to check the status of SMS before sending it.
 */
final class SmsNotSentYetException extends LogicException {}
