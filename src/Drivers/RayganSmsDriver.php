<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @internal
 *
 * See https://raygansms.com/webservice/api/home#samplecode
 */
final class RayganSmsDriver extends Driver
{
    /**
     * The URL for sending OTP message
     */
    private string $otpUrl = 'https://raygansms.com/SendMessageWithCode.ashx';

    /**
     * The URL for sending text message
     */
    private string $textUrl = 'http://smspanel.trez.ir/api/smsAPI/SendMessage';

    /**
     * The URL for sending pattern message
     */
    private string $patternUrl = 'https://smspanel.trez.ir/api/smsApiWithPattern/SendMessage';

    /**
     * Sending status based on the API response code (`$apiStatusCode`).
     */
    private bool $apiStatus;

    /**
     * The status code returned in the API response body.
     */
    private int $apiStatusCode;

    /**
     * The error message returned in the API response body.
     */
    private string $apiErrorMessage;

    public function __construct(
        private readonly string $token,
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
     */
    protected function sendOtp(string $phone, string $message, string $from): static
    {
        $data = [
            'Username' => $this->username,
            'Password' => $this->password,
            'Mobile' => $phone,
            'Message' => $message,
        ];

        $response = Http::get($this->otpUrl, $data)
            ->throw();

        $this->parseOtpResponse($response->body());

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidPatternStructureException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->validatePatternVariables($variables);

        $data = array_merge([
            'AccessHash' => $this->token,
            'PhoneNumber' => $from,
            'PatternId' => $patternCode,
            'Mobiles' => $phones,
            'UserGroupID' => (string) Str::ulid(),
            'SendDateInTimeStamp' => now()->timestamp,
        ], $variables);

        $response = Http::post($this->patternUrl, $data)
            ->throw();

        $this->parsePostResponse($response->json());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'PhoneNumber' => $from,
            'Message' => $message,
            'Mobiles' => $phones,
            'UserGroupID' => (string) Str::ulid(),
            'SendDateInTimeStamp' => now()->timestamp,
        ];

        $response = Http::withBasicAuth($this->username, $this->password)
            ->post($this->textUrl, $data)
            ->throw();

        $this->parsePostResponse($response->json());

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
        return $this->apiErrorMessage;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): int
    {
        return $this->apiStatusCode;
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

    /**
     * Extracts OTP API response data into status properties.
     */
    private function parseOtpResponse(string|int $response): void
    {
        $this->apiStatusCode = (int) $response;
        $this->apiErrorMessage = sprintf('خطا با کد "%s" رخ داده است.', $this->apiStatusCode);
        $this->apiStatus = $this->apiStatusCode > 2000;
    }

    /**
     * Extracts POST API response data into status properties.
     *
     * @param  array<string,mixed>  $response
     */
    private function parsePostResponse(array $response): void
    {
        $this->apiStatusCode = (int) $response['Code'];
        $this->apiErrorMessage = $response['Message'];
        $this->apiStatus = $this->apiStatusCode === 0;
    }
}
