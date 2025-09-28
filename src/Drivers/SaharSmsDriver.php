<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * @internal
 *
 * See https://www.saharsms.com/panel/web-service/rest
 */
final class SaharSmsDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://www.saharsms.com/api';

    /**
     * The API response data.
     *
     * @var array<string, mixed>
     */
    private array $response = [];

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
     */
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $this->validateOtpPhone($phone);

        // For OTP, we use SendVerify endpoint
        // Since the message is dynamic, we'll use a simple template approach
        // The token will be the entire message content
        $data = [
            'receptor' => $this->formatPhoneNumber($phone),
            'token' => $message,
            'template' => 'saharsms_otp', // Default template name for OTP
        ];

        $this->execute('SendVerify', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->validatePatternPhones($phones);
        $this->validatePatternVariables($variables);

        $data = [
            'receptor' => $this->formatPhoneNumber($phones[0]),
            'name' => $patternCode,
        ];

        // Add tokens (up to 5 tokens supported)
        $tokenKeys = ['token1', 'token2', 'token3', 'token4', 'token5'];
        $values = array_values($variables);

        foreach ($tokenKeys as $index => $tokenKey) {
            if (isset($values[$index])) {
                $data[$tokenKey] = $values[$index];
            }
        }

        $this->execute('sendPatternSMS', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedMethodException
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        // Sahar SMS doesn't have a direct text SMS method
        // We can simulate it using SendVerify with the message as token
        if (count($phones) > 1) {
            throw UnsupportedMultiplePhonesException::make($this->getDriverName(), method: 'text');
        }

        // Use OTP method for text messages
        $this->sendOtp($phones[0], $message, $from);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        if (empty($this->response)) {
            return false;
        }

        // Check if we have a messageid (successful response)
        return isset($this->response['messageid']) && ! empty($this->response['messageid']);
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        if (isset($this->response['return']['message'])) {
            return $this->response['return']['message'];
        }

        $status = $this->getErrorCode();

        return match ($status) {
            400 => 'پارامترها ناقص هستند',
            402 => 'متدی با این نام پیدا نشده است',
            404 => 'متد فراخوانی Get یا Post اشتباه است',
            409 => 'سرور قادر به پاسخگوئی نیست بعدا تلاش کنید',
            418 => 'اعتبار حساب شما کافی نیست',
            422 => 'داده ها به دلیل وجود کاراکتر نامناسب قابل پردازش نیست',
            424 => 'الگوی مورد نظر پیدا نشد',
            426 => 'استفاده از این متد نیازمند سرویس پیشرفته می باشد',
            428 => 'ارسال کد از طریق تماس تلفنی امکان پذیر نیست',
            431 => 'ساختار کد صحیح نمی باشد',
            432 => 'پارامتر کد در متن پیام پیدا نشد',
            default => "خطای ناشناخته با کد {$status} رخ داده است"
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): int
    {
        if (isset($this->response['return']['status'])) {
            return (int) $this->response['return']['status'];
        }

        if (isset($this->response['status'])) {
            return (int) $this->response['status'];
        }

        return 0;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $url = sprintf('%s/%s/json/%s', $this->baseUrl, $this->token, $endpoint);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'charset' => 'utf-8',
        ];

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->post($url, $data)
            ->throw();

        $this->response = $response->json() ?? [];
    }

    /**
     * Format phone number for Sahar SMS API.
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // If it starts with 0, replace with +98
        if (str_starts_with($phone, '0')) {
            return '+98'.mb_substr($phone, 1);
        }

        // If it starts with 98, add +
        if (str_starts_with($phone, '98')) {
            return '+'.$phone;
        }

        // If it's a 10-digit number, assume it's Iranian and add +98
        if (mb_strlen($phone) === 10) {
            return '+98'.$phone;
        }

        // Return as is for international numbers
        return $phone;
    }

    private function validateOtpPhone(string $phone): void
    {
        // OTP only supports single phone number
        if (empty($phone)) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }
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
        // Sahar SMS supports up to 5 tokens
        if (count($variables) > 5) {
            throw new InvalidPatternStructureException(
                sprintf('Provider "%s" supports maximum 5 tokens in pattern messages.', $this->getDriverName())
            );
        }

        // Sahar SMS accepts both indexed arrays and key-value pairs
        // We don't need to validate the structure since we handle both
    }
}
