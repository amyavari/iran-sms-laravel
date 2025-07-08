<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * See https://www.melipayamak.com/api/sendsimplesms2/
 * See https://www.melipayamak.com/api/sendbybasenumber2/
 */
final class MeliPayamakDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://rest.payamak-panel.com/api/SendSMS';

    /**
     * The status code returned in the API response body (e.g., `Value` field).
     */
    private string $apiStatusCode;

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
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->validatePatternPhones($phones);

        $data = [
            'to' => $phones[0],
            'bodyId' => $patternCode,
            'text' => $this->toApiPattern($variables),
        ];

        $this->execute('BaseServiceNumber', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'from' => $from,
            'text' => $message,
            'to' => $this->toApiPhones($phones),
        ];

        $this->execute('SendSMS', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return mb_strlen($this->apiStatusCode) >= 15;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return match ($this->apiStatusCode) {
            '-10' => 'متن حاوی لینک می‌باشد',
            '-7' => 'خطایی در شماره فرستنده رخ داده است با پشتیبانی تماس بگیرید',
            '-6' => 'خطای داخلی رخ داده است با پشتیبانی تماس بگیرید',
            '-5' => 'متن ارسالی باتوجه به متغیرهای مشخص شده در متن پیشفرض همخوانی ندارد',
            '-4' => 'کد متن ارسالی صحیح نمی‌باشد و یا توسط مدیر سامانه تأیید نشده است',
            '-3' => 'خط ارسالی در سیستم تعریف نشده است، با پشتیبانی سامانه تماس بگیرید',
            '-2' => 'محدودیت تعداد شماره، محدودیت هربار ارسال یک شماره موبایل می‌باشد',
            '-1' => 'دسترسی برای استفاده از این وبسرویس غیرفعال است. با پشتیبانی تماس بگیرید',
            '0' => 'نام کاربری یا رمزعبور صحیح نمی‌باشد',
            '2' => 'اعتبار کافی نمی‌باشد',
            '3' => 'محدودیت در ارسال روزانه',
            '4' => 'محدودیت در حجم ارسال',
            '5' => 'شماره فرستنده معتبر نمی‌باشد',
            '6' => 'سامانه درحال بروزرسانی می‌باشد',
            '7' => 'متن حاوی کلمه فیلتر شده می‌باشد',
            '9' => 'ارسال از خطوط عمومی از طریق وب سرویس امکان‌پذیر نمی‌باشد',
            '10' => 'کاربر موردنظر فعال نمی‌باشد',
            '11' => 'ارسال نشده',
            '12' => 'مدارک کاربر کامل نمی‌باشد',
            '14' => 'متن حاوی لینک می‌باشد',
            '15' => 'ارسال به بیش از 1 شماره همراه بدون درج "لغو11" ممکن نیست',
            '16' => 'شماره گیرنده‌ای یافت نشد',
            '17' => 'متن پیامک خالی می‌باشد',
            '35' => 'شماره در لیست سیاه مخابرات می‌باشد',
            default => "خطای ناشناخته با کد {$this->apiStatusCode} رخ داده است"
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): string
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
            ->asForm()
            ->acceptJson()
            ->post($endpoint, array_merge($credentials, $data))
            ->throw();

        $this->apiStatusCode = (string) $response->json('Value');

    }

    /**
     * Transforms variables into the API's expected pattern structure.
     *
     * @param  array<string, mixed>  $variables
     *
     * @example - ['key_one' => 'value_one', 'key_two' => 'value_two'] becomes 'value_one;value_two'
     */
    private function toApiPattern(array $variables): string
    {
        return implode(';', $variables);
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
}
