<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Contracts\Sms;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Override;

/**
 * @internal Behind the SMS facade
 */
final class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('iran-sms.default');
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function driver($driver = null): Sms
    {
        /**
         * Make sure we get a new SMS instance each time by removing it from the manager cache.
         */
        $this->forgetDrivers();

        return parent::driver($driver);
    }

    /**
     * Get an SMS instance to send by specific SMS provider
     *
     * @throws InvalidArgumentException
     */
    public function provider(?string $provider = null): Sms
    {
        return $this->driver($provider);
    }
}
