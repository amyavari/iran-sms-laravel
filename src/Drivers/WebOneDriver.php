<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * @see https://webone-sms.ir/Home/WebServices
 */
final class WebOneDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private const string BASE_URL = 'https://api.payamakapi.ir/api/v1';

    /**
     * The sent status returned in the API response body (e.g., `succeeded` field).
     */
    private bool $isSuccessful;

    /**
     * The status code returned in the API response body (e.g., `resultCode` field).
     */
    private int $apiStatusCode;

    public function __construct(
        private readonly string $token,
        private readonly string $from,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function credit(): int
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->withHeaders($this->credentials())
            ->get('SMS/GetCredit')
            ->throw();

        return (int) $response->body();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSender(): string
    {
        return $this->from;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendOtp(string $phone, string $message, string $from): void
    {
        $data = [
            'ToNumber' => $phone,
            'Content' => $message,
        ];

        $this->execute('SMS/SmartOTP', $data);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedMethodException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): void
    {
        throw UnsupportedMethodException::make($this->getDriverName(), method: 'pattern', alternative: 'text');
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): void
    {
        $data = [
            'From' => $from,
            'ToNumbers' => $phones,
            'Content' => $message,
        ];

        $this->execute('SMS/Send', $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return match ($this->apiStatusCode) {
            0 => 'ارسال با موفقیت انجام شد',
            1 => 'نام کاربر یا کلمه عبور نامعتبر می باشد',
            2 => 'کاربر مسدود شده است',
            3 => 'محدودیت در ارسال روزانه',
            4 => 'شماره فرستنده نامعتبر است',
            5 => 'تعداد گیرندگان حداکثر 100 شماره می باشد',
            6 => 'خط فرستنده غیرفعال است',
            7 => 'متن پیامک شامل کلمات فیلتر شده است',
            8 => 'اعتبار کافی نیست',
            9 => 'سامانه در حال بروز رسانی است',
            10 => 'وب سرویس غیرفعال است',
            12 => 'تعداد پیامها و شماره ها باید یکسان باشد',
            13 => 'حداکثر تعداد مجاز در یک درخواست ارسال متناظر 500 شماره می باشد',
            14 => 'کاربر فاقد تعرفه می باشد',
            15 => 'ارسال تکراری متن مشابه به شماره مشابه در مدت زمان مشخص',
            16 => 'موبایل گیرنده یافت نشد',
            17 => 'خط OTP برای کاربر یافت نشد',
            18 => 'با این شماره فقط ارسال تکی مجاز است',
            19 => 'متن ارسالی شما با الگوی تعریفی شما مطابقت ندارد',
            21 => 'آی پی شما برای ارسال از وب سرویس مجاز نمی باشد',
            22 => 'عدم تایید یا ارسال کارت ملی کاربر',
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
        $response = Http::baseUrl(self::BASE_URL)
            ->withHeaders($this->credentials())
            ->post($endpoint, $data)
            ->throw();

        $this->isSuccessful = (bool) $response->json('succeeded');
        $this->apiStatusCode = (int) $response->json('resultCode', -1);
    }

    /**
     * @return array{X-API-KEY: string}
     */
    private function credentials(): array
    {
        return [
            'X-API-KEY' => $this->token,
        ];

    }
}
