<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Concerns;

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Models\SmsLog;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @internal
 *
 * Handles logging operations related to SMS sending.
 * Provides implementations for log methods defined in the
 * \AliYavari\IranSms\Contracts\Sms interface.
 *
 * Core Method:
 * - handleLog(): Handle SMS log operation.
 *
 * This trait relies on the following methods and properties
 * provided by the consuming class:
 *
 * @method ?string error()
 * @method bool successful()
 * @method string getSender()
 * @method list<string> ensureIsArray(string|list<string> $phones)
 *
 * @property Type $type
 * @property string|list<string> $phones
 * @property string|array<mixed> $content
 * @property string $patternCode
 */
trait HasLog
{
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
     * Get driver name from class name
     */
    protected function getDriverName(): string
    {
        $class = new ReflectionClass(static::class);

        return Str::of($class->getShortName())
            ->before('Driver')
            ->snake()
            ->toString();
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
