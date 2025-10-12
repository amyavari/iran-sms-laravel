<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Drivers;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * @internal
 *
 * @see https://api.limosms.com/
 */
final class LimoSmsDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://api.limosms.com/api';

    /**
     * The sent status returned in the API response body (e.g., `Success` field).
     */
    private bool $apiStatus;

    /**
     * The message returned in the API response body (e.g., `Message` field).
     */
    private string $apiMessage;

    public function __construct(
        private readonly string $token,
        private readonly string $from,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function credit(): int
    {
        throw new LogicException('The "credit()" method is not implemented due to insufficient documentation.');
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
        $this->validatePatternPhones($phones);

        $data = [
            'OtpId' => $patternCode,
            'MobileNumber' => $phones[0],
            'ReplaceToken' => $this->toApiPattern($variables),
        ];

        $this->execute('sendpatternmessage', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'SenderNumber' => $from,
            'Message' => $message,
            'MobileNumber' => $phones,
            'SendToBlocksNumber' => true,
        ];

        $this->execute('sendsms', $data);

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
        return $this->apiMessage;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode(): int
    {
        return 0;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $credentials = [
            'ApiKey' => $this->token,
        ];

        $response = Http::withHeaders($credentials)
            ->baseUrl($this->baseUrl)
            ->post($endpoint, $data)
            ->throw();

        $response = $response->json();

        $this->apiStatus = (bool) $response['Success'];
        $this->apiMessage = (string) $response['Message'];
    }

    /**
     * Transforms variables into the API's expected pattern structure.
     *
     * @param  array<mixed>  $variables
     * @return list<string>
     *
     * @example - ['key_one' => 'value_one', 'key_two' => 'value_two'] becomes "['value_one', 'value_two']"
     */
    private function toApiPattern(array $variables): array
    {
        return collect($variables)
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
}
