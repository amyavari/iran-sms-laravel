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
 * @see https://sms.ir/rest-api/
 */
final class SmsIrDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://api.sms.ir/v1/send';

    /**
     * The status code returned in the API response body (e.g., `status` field).
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

        $data = [
            'mobile' => $phones[0],
            'templateId' => $patternCode,
            'parameters' => $this->toApiPattern($variables),
        ];

        $this->execute('verify', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'lineNumber' => $from,
            'messageText' => $message,
            'mobiles' => $phones,
            'sendDateTime' => null,
        ];

        $this->execute('bulk', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->apiStatusCode === 1;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return match ($this->apiStatusCode) {
            1 => 'عملیات با موفقیت انجام شد',
            0 => 'مشکلی در سامانه رخ داده است، لطفا با پشتیبانی در تماس باشید',
            10 => 'کلیدوب سرویس نامعتبر است شد',
            11 => 'کلید وب سرویس غیرفعال است',
            12 => 'کلیدوب‌ سرویس محدود به  IP های تعریف شده می‌باشد',
            13 => 'حساب کاربری غیر فعال است',
            14 => 'حساب کاربری در حالت تعلیق قرار دارد',
            20 => 'تعداد درخواست بیشتر از حد مجاز است',
            101 => 'شماره خط نامعتبر میباشد',
            102 => 'اعتبار کافی نمیباشد',
            103 => 'درخواست شما دارای متن(های) خالی است',
            104 => 'درخواست شما دارای موبایل(های) نادرست است',
            105 => 'تعداد موبایل ها بیشتر از حد مجاز (100عدد)میباشد',
            106 => 'تعداد متن ها بیشتر ازحد مجاز (100عدد) میباشد',
            107 => 'لیست موبایل ها خالی میباشد',
            108 => 'لیست متن ها خالی میباشد',
            109 => 'زمان ارسال نامعتبر میباشد',
            110 => 'تعداد شماره موبایل ها و تعداد متن ها برابر نیستند',
            111 => 'با این شناسه ارسالی ثبت نشده است',
            112 => 'رکوردی برای حذف یافت نشد',
            113 => 'قالب یافت نشد',
            114 => 'طول رشته مقدار پارامتر، بیش از حد مجاز (25 کاراکتر) می‌باشد',
            115 => 'شماره موبایل ها  در لیست سیاه سامانه می‌باشند',
            116 => 'نام پارامتر نمی‌تواند خالی باشد',
            117 => 'متن ارسال شده مورد تایید نمی‌باشد',
            118 => 'تعداد پیام ها بیش از حد مجاز می باشد.',
            119 => 'به منظور استفاده از قالب‌ شخصی سازی شده پلن خود را ارتقا دهید',
            123 => 'خط ارسال‌کننده نیاز به فعال‌سازی دارد',
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
            'x-api-key' => $this->token,
        ];

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($credentials)
            ->acceptJson()
            ->post($endpoint, $data)
            ->throw();

        $this->apiStatusCode = (int) $response->json('status');
    }

    /**
     * Transforms variables into the API's expected pattern structure.
     *
     * @param  array<string, mixed>  $variables
     * @return list<array{name: string, value: mixed}>
     *
     * @example - ['key_one' => 'value_one'] becomes [['name' => 'key_one', 'value' => 'value_one']]
     */
    private function toApiPattern(array $variables): array
    {
        return collect($variables)
            ->map(fn (mixed $value, string $key) => [
                'name' => $key,
                'value' => $value,
            ])
            ->values()
            ->all();
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
