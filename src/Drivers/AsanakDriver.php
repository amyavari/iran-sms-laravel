<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * See https://asanak.com/api-docs
 */
final class AsanakDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://sms.asanak.ir/webservice/v2rest';

    /**
     * The status code returned in the API response body (e.g., `meta.status` field).
     */
    private int $apiStatusCode;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $from,
    ) {}

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSender(): string
    {
        return $this->from;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedMethodException
     */
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        throw UnsupportedMethodException::make($this->getDriverName(), method: 'otp', alternative: 'pattern');
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedMultiplePhonesException
     * @throws InvalidPatternStructureException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->validatePatternPhones($phones);

        $this->validatePatternVariables($variables);

        $data = [
            'template_id' => $patternCode,
            'destination' => $phones[0],
            'parameters' => $variables,
            'send_to_blacklist' => 1,
        ];

        $this->execute('template', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'source' => $from,
            'message' => $message,
            'destination' => $this->toApiPhones($phones),
            'send_to_blacklist' => 1,
        ];

        $this->execute('sendsms', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->apiStatusCode === 200;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return match ($this->apiStatusCode) {
            1008 => 'خطای اعتبار سنجی پارامتر های ورودی',
            1014 => 'شماره فرستنده (مبدا) مجاز به ارسال لینک نمی باشد.',
            1015 => 'خطای مربوط به منقضی شدن کلمه عبور وب سرویس',
            1006 => 'خطای مربوط به نداشتن اعتبار کافی برای ارسال',
            1005 => 'خطای مربوطه به نداشتن اعتبار کافی پنل نمایندگی',
            1013 => 'دربازه زمانی غیر مجاز (تبلیغاتی) فقط شماره های خدماتی مجاز به ارسال می باشند.',
            1002 => 'شماره فرستنده (مبدا) فعال نمی باشد.',
            1010 => 'لیست شماره های مقصد (گیرنده) صحیح و معتبر نمی باشد.',
            1009 => 'خطای مربوطه به محدودیت ارسال روزانه وب سرویس می باشد.',
            429 => 'محدودیت درخواست‌ها رسیده است. تعداد درخواست‌ها بیش از حد مجاز است.',
            1004 => 'خطای داخلی در سرور رخ داده است.',
            default => "خطای ناشناخته با کد {$this->apiStatusCode} رخ داده است"
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): int
    {
        return $this->apiStatusCode;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $credentials = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->post($endpoint, array_merge($credentials, $data))
            ->throw();

        $this->apiStatusCode = (int) $response->json('meta.status');

    }

    /**
     * Transforms phones into the API's expected phone structure.
     *
     * @param  list<string>  $phones
     *
     * @example - ['0913', '0914'] becomes "0913,0914"
     */
    private function toApiPhones(array $phones): string
    {
        return implode(',', $phones);
    }

    /**
     * @param  array<string>  $phones
     *
     * @throws UnsupportedMultiplePhonesException
     */
    private function validatePatternPhones(array $phones): void
    {
        if (count($phones) !== 1) {
            throw UnsupportedMultiplePhonesException::make($this->getDriverName(), method: 'pattern');
        }

    }

    /**
     * @param  array<mixed>  $variables
     *
     * @throws InvalidPatternStructureException
     */
    private function validatePatternVariables(array $variables): void
    {
        if (Arr::isList($variables)) {
            throw new InvalidPatternStructureException(
                sprintf('Provider "%s" only accepts pattern data as key-value pairs.', $this->getDriverName())
            );
        }
    }
}
