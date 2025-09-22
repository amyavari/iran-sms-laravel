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
 * @see https://kavenegar.com/rest.html
 */
final class KavenegarDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://api.kavenegar.com/v1';

    /**
     * The status code returned in the API response body (e.g., `return.status` field).
     */
    private int $apiStatusCode;

    public function __construct(
        private readonly string $token,
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

        $data = array_merge([
            'receptor' => $phones[0],
            'template' => $patternCode,
        ], $variables);

        $this->execute('verify/lookup', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'sender' => $from,
            'message' => $message,
            'receptor' => $this->toApiPhones($phones),
        ];

        $this->execute('sms/send', $data);

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
            400 => 'پارامترها ناقص هستند',
            401 => 'حساب کاربری غیرفعال شده است',
            402 => 'عملیات ناموفق بود',
            403 => 'کد شناسائی API-Key معتبر نمی‌باشد',
            404 => 'متد نامشخص است',
            405 => 'متد Get/Post اشتباه است',
            406 => 'پارامترهای اجباری خالی ارسال شده اند',
            407 => 'دسترسی به اطلاعات مورد نظر برای شما امکان پذیر نیست',
            409 => 'سرور قادر به پاسخگوئی نیست بعدا تلاش کنید',
            411 => 'دریافت کننده نامعتبر است',
            412 => 'ارسال کننده نامعتبر است',
            413 => 'پیام خالی است و یا طول پیام بیش از حد مجاز می‌باشد. حداکثر طول کل متن پیامک 900 کاراکتر می باشد',
            414 => 'حجم درخواست بیشتر از حد مجاز است ،ارسال پیامک :هر فراخوانی حداکثر 200 رکورد و کنترل وضعیت :هر فراخوانی 500 رکورد',
            415 => 'اندیس شروع بزرگ تر از کل تعداد شماره های مورد نظر است',
            416 => 'IP سرویس مبدا با تنظیمات مطابقت ندارد',
            417 => 'تاریخ ارسال اشتباه است و فرمت آن صحیح نمی باشد.',
            418 => 'اعتبار شما کافی نمی‌باشد',
            419 => 'طول آرایه متن و گیرنده و فرستنده هم اندازه نیست',
            420 => 'استفاده از لینک در متن پیام برای شما محدود شده است',
            422 => 'داده ها به دلیل وجود کاراکتر نامناسب قابل پردازش نیست',
            424 => 'الگوی مورد نظر پیدا نشد',
            426 => 'استفاده از این متد نیازمند سرویس پیشرفته می‌باشد',
            427 => 'استفاده از این خط نیازمند ایجاد سطح دسترسی می باشد',
            428 => 'ارسال کد از طریق تماس تلفنی امکان پذیر نیست',
            429 => 'IP محدود شده است',
            431 => 'ساختار کد صحیح نمی‌باشد',
            432 => 'پارامتر کد در متن پیام پیدا نشد',
            451 => 'فراخوانی بیش از حد در بازه زمانی مشخص IP محدود شده',
            501 => 'فقط امکان ارسال پیام تست به شماره صاحب حساب کاربری وجود دارد',
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
        $credentials = '/'.$this->token;

        $response = Http::baseUrl($this->baseUrl.$credentials)
            ->withHeader('charset', 'utf-8')
            ->asForm()
            ->acceptJson()
            ->post($endpoint.'.json', $data)
            ->throw();

        $this->apiStatusCode = (int) $response->json('return.status');
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
