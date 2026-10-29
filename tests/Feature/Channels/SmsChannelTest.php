<?php

declare(strict_types=1);

use AliYavari\IranSms\Channels\SmsChannel;
use AliYavari\IranSms\Contracts\Sms;
use Illuminate\Notifications\Notification;

it('sends the SMS returned by the notification', function (): void {
    $sms = Mockery::spy(Sms::class);

    app(SmsChannel::class)->send(new stdClass, notificationReturning($sms)); // The channel does not use the notifiable

    $sms->shouldHaveReceived('send')->once();
});

it('throws an exception when the notification does not return an SMS', function (mixed $message, string $type): void {
    expect(fn () => app(SmsChannel::class)->send(new stdClass, notificationReturning($message)))
        ->toThrow(
            UnexpectedValueException::class,
            sprintf('The toSms() method must return an instance of "\AliYavari\IranSms\Contracts\Sms", "%s" given.', $type)
        );
})->with([
    'string' => ['message' => 'SMS', 'type' => 'string'],
    'null' => ['message' => null, 'type' => 'null'],
    'object' => ['message' => new stdClass, 'type' => 'stdClass'],
]);

// ------------
// Helpers
// ------------

function notificationReturning(mixed $message): Notification
{
    return new class($message) extends Notification
    {
        public function __construct(private readonly mixed $message) {}

        public function toSms(object $notifiable): mixed
        {
            return $this->message;
        }
    };
}
