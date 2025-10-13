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
 * @see https://doc.sms-webservice.com/
 */
final class PayamResanDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://api.sms-webservice.com/api/V3';

    /**
     * The SMS ID returned in the API response body (e.g., `id` field).
     */
    private int $smsId;

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
        $this->validatePatternPhones($phones);

        $this->validatePatternVariables($variables);

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
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return sprintf('شناسه پیام برای پیگیری "%s" می باشد.', $this->smsId);
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): int
    {
        return $this->smsId;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->get($endpoint, array_merge($this->credentials(), $data))
            ->throw();

        $this->smsId = (int) $response->json('id');
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
        if (count($variables) !== 3) {
            throw new InvalidPatternStructureException(
                sprintf('Provider "%s" only accepts pattern data with exactly 3 items.', $this->getDriverName())
            );
        }

        if (Arr::isList($variables)) {
            throw new InvalidPatternStructureException(
                sprintf('Provider "%s" only accepts pattern data as key-value pairs.', $this->getDriverName())
            );
        }
    }
}
