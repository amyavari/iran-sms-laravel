<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * Throw exception if user didn't set the content of SMS before sending it.
 */
final class SmsContentNotDefinedException extends LogicException {}
