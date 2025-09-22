<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * See https://github.com/ippanelcom/Edge-Document
 */
final class FarazSmsDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://edge.ippanel.com/v1/api/send';

    /**
     * The sent status returned in the API response body (e.g., `meta.status` field).
     */
    private bool $apiStatus;

    /**
     * The status code returned in the API response body (e.g., `meta.message_code` field).
     */
    private string $apiStatusCode;

    /**
     * The error message returned in the API response body (e.g., `meta.message` field).
     */
    private string $apiErrorMessage;

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
     * @throws InvalidPatternStructureException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->validatePatternVariables($variables);

        $data = [
            'sending_type' => 'pattern',
            'from_number' => $from,
            'code' => $patternCode,
            'recipients' => $phones,
            'params' => $variables,
        ];

        $this->execute($data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'sending_type' => 'normal',
            'from_number' => $from,
            'message' => $message,
            'params' => [
                'recipients' => $phones,
            ],
        ];

        $this->execute($data);

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
    protected function getErrorCode(): string
    {
        return $this->apiStatusCode;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(array $data): void
    {
        $credentials = [
            'Authorization' => $this->token,
        ];

        $response = Http::withHeaders($credentials)
            ->post($this->baseUrl, $data)
            ->throw();

        $meta = $response->json('meta');

        $this->apiStatus = $meta['status'];
        $this->apiStatusCode = $meta['message_code'];
        $this->apiErrorMessage = $meta['message'];
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
