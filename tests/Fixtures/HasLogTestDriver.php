<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Fixtures;

use AliYavari\IranSms\Concerns\HasLog;
use AliYavari\IranSms\Enums\Type;

/**
 * Test fixture class for the `HasLog` trait.
 *
 * This class implements the required properties and methods
 * expected by the `HasLog` trait for isolated testing purposes.
 */
final class HasLogTestDriver
{
    use HasLog;

    public function __construct(
        private readonly Type $type,
        private readonly string $phones,
        private readonly array|string $content,
        private readonly ?string $patternCode,
        private readonly bool $successful,
        private readonly ?string $error,
        private readonly string $sender,
    ) {}

    public function successful(): bool
    {
        return $this->successful;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function callHandleLog(): void
    {
        $this->handleLog();
    }

    private function getSender(): string
    {
        return $this->sender;
    }

    private function ensureIsArray(string|array $phones): array
    {
        return [$phones];
    }
}
