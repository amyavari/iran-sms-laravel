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
    private string $baseUrl = 'https://api.payamakapi.ir/api/v1';

    /**
     * The sent status returned in the API response body (e.g., `succeeded` field).
     */
    private bool $apiStatus;

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
        $response = Http::withHeaders($this->credentials())
            ->baseUrl($this->baseUrl)
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
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $data = [
            'ToNumber' => $phone,
            'Content' => $message,
        ];

        $this->execute('SMS/SmartOTP', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedMethodException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        throw UnsupportedMethodException::make($this->getDriverName(), method: 'pattern', alternative: 'text');
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'From' => $from,
            'ToNumbers' => $phones,
            'Content' => $message,
        ];

        $this->execute('SMS/Send', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->apiStatus;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return match ($this->apiStatusCode) {
            0 => 'ارسال با موفقيت انجام شد',
            1 => 'نام كاربر يا كلمه عبور نامعتبر مي باشد',
            2 => 'كاربر مسدود شده است',
            3 => 'محدوديت در ارسال روزانه',
            4 => 'شماره فرستنده نامعتبر است',
            5 => 'تعداد گيرندگان حداكثر 100 شماره مي باشد',
            6 => 'خط فرستنده غيرفعال است',
            7 => 'متن پيامك شامل كلمات فيلتر شده است',
            8 => 'اعتبار كافي نيست',
            9 => 'سامانه در حال بروز رساني است',
            10 => 'وب سرويس غيرفعال است',
            12 => 'تعداد پيامها و شماره ها بايد يكسان باشد',
            13 => 'حداكثر تعداد مجاز در يك درخواست ارسال متناظر 500 شماره مي باشد',
            14 => 'كاربر فاقد تعرفه مي باشد',
            15 => 'ارسال تكراري متن مشابه به شماره مشابه در مدت زمان مشخص',
            16 => 'موبايل گيرنده يافت نشد',
            17 => 'خط OTP براي كاربر يافت نشد',
            18 => 'با اين شماره فقط ارسال تكي مجاز است',
            19 => 'متن ارسالي شما با الگوي تعريفي شما مطابقت ندارد',
            21 => 'آي پي شما براي ارسال از وب سرويس مجاز نمي باشد',
            22 => 'عدم تاييد يا ارسال كارت ملي كاربر',
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
        $response = Http::withHeaders($this->credentials())
            ->baseUrl($this->baseUrl)
            ->post($endpoint, $data)
            ->throw()
            ->json();

        $this->apiStatus = (bool) $response['succeeded'];
        $this->apiStatusCode = (int) $response['resultCode'];
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
