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
 * @see https://ghasedak.me/docs
 */
final class GhasedakDriver extends Driver
{
    /**
     * The base URL for the API.
     */
    private string $baseUrl = 'https://gateway.ghasedak.me/rest/api/v1/WebService';

    /**
     * The sent status returned in the API response body (e.g., `IsSuccess` field).
     */
    private bool $apiStatus;

    /**
     * The status code returned in the API response body (e.g., `StatusCode` field).
     */
    private int $apiStatusCode;

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
        $response = Http::withHeaders($this->credentials())
            ->baseUrl($this->baseUrl)
            ->get('GetAccountInformation')
            ->throw();

        return (int) $response->json('Data.Credit');
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
        $this->validatePatternVariables($variables);

        $data = [
            'templateName' => $patternCode,
            'receptors' => $this->toApiPhones($phones),
            'inputs' => $this->toApiPattern($variables),
            'udh' => false,
            'sendDate' => now()->toISOString(),
        ];

        $this->execute('SendOtpSMS', $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function sendText(array $phones, string $message, string $from): static
    {
        $data = [
            'lineNumber' => $from,
            'message' => $message,
            'receptors' => $phones,
            'clientReferenceId' => null,
            'isVoice' => false,
            'udh' => false,
            'sendDate' => now()->toISOString(),
        ];

        $this->execute('SendBulkSMS', $data);

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
        return $this->apiStatusCode;
    }

    /**
     * Executes the API request to the specified endpoint with given data.
     *
     * @param  array<string, mixed>  $data
     */
    private function execute(string $endpoint, array $data): void
    {
        $response = Http::withHeaders($this->credentials())
            ->baseUrl($this->baseUrl)
            ->post($endpoint, $data)
            ->throw();

        $response = $response->json();

        $this->apiStatus = (bool) $response['IsSuccess'];
        $this->apiStatusCode = (int) $response['StatusCode'];
        $this->apiMessage = (string) $response['Message'];
    }

    /**
     * @return array{ApiKey: string}
     */
    private function credentials(): array
    {
        return [
            'ApiKey' => $this->token,
        ];
    }

    /**
     * Transforms phones into the API's expected phone structure.
     *
     * @param  list<string>  $phones
     * @return array<array{mobile: string, clientReferenceId: null}>
     *
     * @example - ['0913', '0914'] becomes "[['mobile' => '0913', 'clientReferenceId' => null], ['mobile' => '0914', 'clientReferenceId' => null]]"
     */
    private function toApiPhones(array $phones): array
    {
        return collect($phones)
            ->map(fn (string $phone) => [
                'mobile' => $phone,
                'clientReferenceId' => null,
            ])
            ->all();
    }

    /**
     * Transforms variables into the API's expected pattern structure.
     *
     * @param  array<string, mixed>  $variables
     * @return array<array{param: string, value: mixed}>
     *
     * @example - ['key_one' => 'value_one', 'key_two' => 'value_two'] becomes "[['param' => key_one, 'value' => 'value_one], ['param' => key_two, 'value' => 'value_two]]"
     */
    private function toApiPattern(array $variables): array
    {
        return collect($variables)
            ->map(fn (string $value, string $key) => [
                'param' => $key,
                'value' => $value,
            ])
            ->values()
            ->all();
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
