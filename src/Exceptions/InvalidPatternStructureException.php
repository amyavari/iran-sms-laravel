<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Exceptions;

use LogicException;

/**
 * @internal
 *
 * Exception thrown when the format of the pattern variables does not match the SMS provider's expected structure.
 */
final class InvalidPatternStructureException extends LogicException {}
