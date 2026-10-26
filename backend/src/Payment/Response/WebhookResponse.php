<?php

declare(strict_types=1);

namespace App\Payment\Response;

use App\Payment\Enum\WebhookResultEnum;
use App\Shared\Contract\Arrayable;

final readonly class WebhookResponse implements Arrayable
{
    private const string STATUS_KEY = 'status';

    public function __construct(public string $status)
    {
    }

    public static function fromResult(WebhookResultEnum $result): self
    {
        return new self($result->value);
    }

    public function toArray(): array
    {
        return [self::STATUS_KEY => $this->status];
    }
}
