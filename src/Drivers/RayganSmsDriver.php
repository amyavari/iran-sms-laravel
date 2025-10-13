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
 * @see https://raygansms.com/webservice/api/home#samplecode
 */
final class RayganSmsDriver extends Driver
{
    /**
     * The URL for sending OTP message
     */
    private string $otpUrl = 'https://raygansms.com/SendMessageWithCode.ashx';

    /**
     * The URL for getting credit
     */
    private string $restBaseUrl = 'https://smspanel.trez.ir/api/';

    /**
     * Sending status based on the API response code (`$apiStatusCode`).
     */
    private bool $isSuccessful;

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
    public function credit(): int
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->baseUrl($this->restBaseUrl)
            ->post('smsAPI/GetCredit')
            ->throw();

        return (int) $response->json('Result');
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

        $this->execute('smsApiWithPattern/SendMessage', $data);

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

        $this->execute('smsAPI/SendMessage', $data);

        return $this;
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
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $response = Http::withBasicAuth($this->username, $this->password)
            ->baseUrl($this->restBaseUrl)
            ->post($endpoint, $data)
            ->throw();

        $this->parseRestResponse($response->json());
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

        $this->isSuccessful = $this->apiStatusCode > 2000;
    }

    /**
     * Extracts REST API response data into status properties.
     *
     * @param  array<string,mixed>  $response
     */
    private function parseRestResponse(array $response): void
    {
        $this->apiStatusCode = (int) $response['Code'];
        $this->apiErrorMessage = $response['Message'];

        $this->isSuccessful = $this->apiStatusCode === 0;
    }
}
