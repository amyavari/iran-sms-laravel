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
 * @see https://docs.iranpayamak.com/ or https://docs2.farazsms.com/
 */
final class FarazSmsDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://api.iranpayamak.com/ws/v1';

    /**
     * The sent status returned in the API response body (e.g., `status` field).
     */
    private string $apiStatus;

    /**
     * The error message returned in the API response body (e.g., `messages` field).
     */
    private string $apiErrorMessage;

    public function __construct(
        private readonly string $token,
        private readonly string $from,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function credit(): int
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->credentials())
            ->asJson()
            ->get('account/balance')
            ->throwIfServerError();

        return (int) $response->json('data.balanceAmount');
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
        $this->validatePatternPhones($phones);

        $this->validatePatternVariables($variables);

        $data = [
            'number_format' => 'english',
            'line_number' => $from,
            'code' => $patternCode,
            'recipient' => $phones[0],
            'attributes' => $variables,
            'schedule' => null,
        ];

        $this->execute('sms/pattern', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'number_format' => 'english',
            'line_number' => $from,
            'text' => $message,
            'recipients' => $phones,
            'schedule' => null,
        ];

        $this->execute('sms/simple', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->apiStatus === 'success';
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
        return '0';
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->credentials())
            ->post($endpoint, $data)
            ->throwIfServerError();

        $this->apiStatus = $response->json('status');
        $this->apiErrorMessage = (string) $response->json('messages');
    }

    /**
     * @return array{Api-Key: string}
     */
    private function credentials(): array
    {
        return [
            'Api-Key' => $this->token,
        ];
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
