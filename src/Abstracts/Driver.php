<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Abstracts;

use AliYavari\IranSms\Contracts\Sms;
use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Exceptions\SmsContentNotDefinedException;
use AliYavari\IranSms\Exceptions\SmsIsImmutableException;
use AliYavari\IranSms\Exceptions\SmsNotSentYetException;
use AliYavari\IranSms\Models\SmsLog;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @internal
 *
 * Base implementation of the public API and common foundation for all drivers.
 */
abstract class Driver implements Sms
{
    /**
     * Sms Type
     */
    private Type $type;

    /**
     * User defined sender number
     */
    private string $from;

    /**
     * Phone(s) to send message to
     *
     * @var string|list<string>
     */
    private string|array $phones;

    /**
     * Message content or variables for pattern
     *
     * @var string|array<mixed>
     */
    private string|array $content;

    /**
     * Message pattern code
     */
    private string $patternCode;

    /**
     * Whether SMS is sent
     */
    private bool $isSent = false;

    /**
     * Which types must be logged
     *
     * @var list<Type>
     */
    private array $typesToLog;

    /**
     * Which statuses must be logged
     *
     * @var list<string>
     */
    private array $statusesToLog = ['successful', 'failed'];

    /**
     * Get the default sender number from config
     */
    abstract protected function getDefaultSender(): string;

    /**
     * Send OTP SMS
     */
    abstract protected function sendOtp(string $phone, string $message, string $from): static;

    /**
     * Send pattern SMS
     *
     * @param  list<string>  $phones
     * @param  array<mixed>  $variables
     */
    abstract protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): static;

    /**
     * Send regular text SMS
     *
     * @param  list<string>  $phones
     */
    abstract protected function sendText(array $phones, string $message, string $from): static;

    /**
     * Check if SMS sending was successful
     */
    abstract protected function isSuccessful(): bool;

    /**
     * Get the error message if SMS sending failed
     */
    abstract protected function getErrorMessage(): string;

    /**
     * Get the error code if SMS sending failed
     */
    abstract protected function getErrorCode(): string|int;

    /**
     * {@inheritdoc}
     *
     * @throws SmsIsImmutableException
     */
    final public function otp(string $phone, string $message): static
    {
        $this->checkSmsIsNotSet();

        $this->type = Type::Otp;

        $this->phones = $phone;
        $this->content = $message;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsIsImmutableException
     */
    final public function pattern(string|array $phones, string $patternCode, array $variables): static
    {
        $this->checkSmsIsNotSet();

        $this->type = Type::Pattern;

        $this->phones = $this->ensureIsArray($phones);
        $this->patternCode = $patternCode;
        $this->content = $variables;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsIsImmutableException
     */
    final public function text(string|array $phones, string $message): static
    {
        $this->checkSmsIsNotSet();

        $this->type = Type::Text;

        $this->phones = $this->ensureIsArray($phones);
        $this->content = $message;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsContentNotDefinedException
     */
    final public function send(): static
    {
        if (! isset($this->type)) {
            throw new SmsContentNotDefinedException('Before sending an SMS you must define its content by one of these methods "otp, pattern, text".');
        }

        match ($this->type) {
            Type::Otp => $this->sendOtp($this->phones, $this->content, $this->getSender()),
            Type::Pattern => $this->sendPattern($this->phones, $this->patternCode, $this->content, $this->getSender()),
            Type::Text => $this->sendText($this->phones, $this->content, $this->getSender()),
        };

        $this->isSent = true;

        $this->handleLog();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function log(bool $log = true): static
    {
        $this->typesToLog = $log ? Type::cases() : [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function logOtp(bool $log = true): static
    {
        $log ? $this->addTypeToLog(Type::Otp) : $this->removeTypeFromLog(Type::Otp);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function logPattern(bool $log = true): static
    {
        $log ? $this->addTypeToLog(Type::Pattern) : $this->removeTypeFromLog(Type::Pattern);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function logText(bool $log = true): static
    {
        $log ? $this->addTypeToLog(Type::Text) : $this->removeTypeFromLog(Type::Text);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function logSuccessful(): static
    {
        if (! isset($this->typesToLog)) {
            $this->log(true);
        }

        $this->statusesToLog = ['successful'];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    final public function logFailed(): static
    {
        if (! isset($this->typesToLog)) {
            $this->log(true);
        }

        $this->statusesToLog = ['failed'];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsNotSentYetException
     */
    final public function successful(): bool
    {
        $this->checkSmsIsSent();

        return $this->isSuccessful();
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsNotSentYetException
     */
    final public function failed(): bool
    {
        $this->checkSmsIsSent();

        return ! $this->isSuccessful();
    }

    /**
     * {@inheritdoc}
     *
     * @throws SmsNotSentYetException
     */
    final public function error(): ?string
    {
        $this->checkSmsIsSent();

        if ($this->isSuccessful()) {
            return null;
        }

        return sprintf('Code %s - %s', $this->getErrorCode(), $this->getErrorMessage());
    }

    /**
     * Get sender number to send SMS
     */
    private function getSender(): string
    {
        return $this->from ?? $this->getDefaultSender();
    }

    /**
     * Throw an exception if SMS content is set before
     *
     * @throws SmsIsImmutableException
     */
    private function checkSmsIsNotSet(): void
    {
        if (isset($this->type)) {
            throw new SmsIsImmutableException('SMS object is immutable, to create new SMS content you need to create new instance.');
        }
    }

    /**
     * Wrap data to array if it's not
     *
     * @return list<string>
     */
    private function ensureIsArray(mixed $data): array
    {
        return is_array($data) ? $data : [$data];
    }

    /**
     * Throw an exception if SMS is not sent yet
     *
     * @throws SmsNotSentYetException
     */
    private function checkSmsIsSent(): void
    {
        if (! $this->isSent) {
            throw new SmsNotSentYetException('To check SMS status, you first must send it with "send".');
        }
    }

    /**
     * Serialize content of SMS to save in the Database
     *
     * @return array<string, mixed>
     */
    private function serializeContent(): array
    {
        return is_string($this->content)
        ? ['message' => $this->content]
        : ['code' => $this->patternCode, 'variables' => $this->content];
    }

    /**
     * Get driver name from class name
     */
    private function getDriverName(): string
    {
        $class = new ReflectionClass(static::class);

        return Str::of($class->getShortName())
            ->before('Driver')
            ->snake()
            ->toString();
    }

    /**
     * Store SMS log
     */
    private function storeLog(): void
    {
        SmsLog::query()->create([
            'type' => $this->type,
            'driver' => $this->getDriverName(),
            'from' => $this->getSender(),
            'to' => $this->ensureIsArray($this->phones),
            'content' => $this->serializeContent(),
            'is_successful' => $this->successful(),
            'error' => $this->error(),
        ]);
    }

    /**
     * Handle log based on the user setup
     */
    private function handleLog(): void
    {
        if (! isset($this->typesToLog) || $this->typesToLog === []) {
            return;
        }

        if (! in_array($this->type, $this->typesToLog, strict: true)) {
            return;
        }

        $statusText = $this->successful() ? 'successful' : 'failed';

        if (! in_array($statusText, $this->statusesToLog, strict: true)) {
            return;
        }

        $this->storeLog();
    }

    /**
     * Add SMS type to be logged
     */
    private function addTypeToLog(Type $type): void
    {
        $this->typesToLog ??= [];

        $this->typesToLog = collect($this->typesToLog)->push($type)->unique()->all();
    }

    /**
     * Remove SMS type from being logged
     */
    private function removeTypeFromLog(Type $type): void
    {
        $this->typesToLog ??= [];

        $this->typesToLog = collect($this->typesToLog)->reject(fn (Type $value) => $value === $type)->all();
    }
}
