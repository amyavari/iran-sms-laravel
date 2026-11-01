<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * @internal
 *
 * @see https://portal.amootsms.com/Documentation
 */
final class AmootSmsDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://portal.amootsms.com/rest';

    /**
     * The status returned in the API response body (e.g., `Status` field).
     */
    private string $apiStatus;

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
            ->get('AccountStatus', $this->credentials())
            ->throw();

        return (int) $response->json('RemaindCredit');
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
     */
    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static
    {
        $this->ensureOnePatternPhone($phones);

        $data = [
            'Mobile' => $phones[0],
            'PatternCodeID' => $patternCode,
            'PatternValues' => $this->toApiPattern($variables),
        ];

        $this->execute('SendWithPattern', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'LineNumber' => $from,
            'SMSMessageText' => $message,
            'Mobiles' => $this->toCommaSeparatedPhones($phones),
            'SendDateTime' => now('Asia/Tehran')->toIso8601String(),
        ];

        $this->execute('SendSimple', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isSuccessful(): bool
    {
        return $this->apiStatus === 'Success';
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage(): string
    {
        return $this->apiStatus;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): string
    {
        return '';
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $response = Http::baseUrl($this->baseUrl)
            ->get($endpoint, array_merge($this->credentials(), $data))
            ->throw();

        $this->apiStatus = (string) $response->json('Status');
    }

    /**
     * @return array{Token: string}
     */
    private function credentials(): array
    {
        return [
            'Token' => $this->token,
        ];
    }

    /**
     * Transforms variables into the API's expected pattern structure.
     *
     * @param  array<string, mixed>  $variables
     *
     * @example - ['key_one' => 'value_one', 'key_two' => 'value_two'] becomes 'value_one,value_two'
     */
    private function toApiPattern(array $variables): string
    {
        return Arr::join($variables, ',');
    }
}
