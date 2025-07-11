<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Models;

use AliYavari\IranSms\Enums\Type;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 *
 * @property-read int $id
 * @property-read Type $type
 * @property-read string $driver
 * @property-read string $from
 * @property-read list<string> $to
 * @property-read array<string, mixed> $content
 * @property-read bool $is_successful
 * @property-read ?string $error
 */
final class SmsLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'driver',
        'from',
        'to',
        'content',
        'is_successful',
        'error',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => Type::class,
            'to' => 'array',
            'content' => 'array',
            'is_successful' => 'bool',
        ];
    }
}
