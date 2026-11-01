<?php

declare(strict_types=1);

use AliYavari\IranSms\Enums\Type;

dataset('sms_types', [
    'OTP' => ['type' => Type::Otp],
    'Pattern' => ['type' => Type::Pattern],
    'Text' => ['type' => Type::Text],
]);
