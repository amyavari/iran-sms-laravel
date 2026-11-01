<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * @see https://doc.sms-webservice.com/
 */
final class BehinPayamDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private const string BASE_URL = 'https://api.sms-webservice.com/api/V3';

    /**
     * The sent status returned in the API response body (`Success` field).
     * The docs show no such field, so a missing one means success.
     */
    private bool $isSuccessful;

    /**
     * The error code returned in the API response body (`ErrorCode` field).
     */
    private int $apiStatusCode;

    /**
     * The error message returned in the API response body (`Error` field).
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
        $response = Http::baseUrl(self::BASE_URL)
            ->post('AccountInfo', $this->credentials())
            ->throw();

        return (int) $response->json('Credit');
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
     * @throws UnsupportedMultiplePhonesException
     * @throws InvalidPatternStructureException
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->ensureOnePatternPhone($phones);

        $this->ensureKeyValuePattern($variables);

        $this->ensureThreePatternVariables($variables);

        $data = array_merge([
            'Destination' => $phones[0],
            'TemplateKey' => $patternCode,
        ], $variables);

        $this->execute('SendTokenSingle', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'Sender' => $from,
            'Text' => $message,
            'Recipients' => $phones,
        ];

        $this->execute('Send', $data);

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
        $response = Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->get($endpoint, array_merge($this->credentials(), $data))
            ->throw();

        $this->isSuccessful = (bool) $response->json('Success', true);
        $this->apiStatusCode = (int) $response->json('ErrorCode');
        $this->apiErrorMessage = (string) $response->json('Error');
    }

    /**
     * @return array{ApiKey: string,}
     */
    private function credentials(): array
    {
        return [
            'ApiKey' => $this->token,
        ];
    }

    /**
     * @param  array<mixed>  $variables
     *
     * @throws InvalidPatternStructureException
     */
    private function ensureThreePatternVariables(array $variables): void
    {
        if (count($variables) !== 3) {
            throw new InvalidPatternStructureException(
                sprintf('Provider "%s" only accepts pattern data with exactly 3 items.', $this->getDriverName())
            );
        }
    }
}
