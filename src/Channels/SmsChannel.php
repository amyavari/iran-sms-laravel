<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Channels;

use AliYavari\IranSms\Contracts\Sms;
use Illuminate\Notifications\Notification;
use UnexpectedValueException;

final class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        /** @phpstan-ignore method.notFound */
        $message = $notification->toSms($notifiable);

        if (! $message instanceof Sms) {
            throw new UnexpectedValueException(
                sprintf('The toSms() method must return an instance of "\AliYavari\IranSms\Contracts\Sms", "%s" given.', get_debug_type($message))
            );
        }

        $message->send();
    }
}
