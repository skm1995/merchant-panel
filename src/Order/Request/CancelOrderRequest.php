<?php

declare(strict_types=1);

namespace App\Order\Request;

use App\Order\Command\CancelOrderCommand;
use App\Order\Entity\OrderId;
use App\Shared\Http\EntityTag;
use App\Shared\Http\Enum\HeaderNameEnum;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\Request;

final readonly class CancelOrderRequest
{
    private const string ORDER_ID_PARAMETER = 'id';
    private const string MISSING_PRECONDITION_MESSAGE = 'Send the order ETag in the If-Match header to cancel it.';

    public function __construct(public OrderId $orderId, public EntityTag $expectedTag)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $ifMatch = $request->header(HeaderNameEnum::IF_MATCH->value)
            ?? throw new PreconditionRequiredException(self::MISSING_PRECONDITION_MESSAGE);

        return new self(
            OrderId::fromString((string) $request->routeParameter(self::ORDER_ID_PARAMETER)),
            EntityTag::fromHeader($ifMatch),
        );
    }

    public function toCommand(): CancelOrderCommand
    {
        return new CancelOrderCommand($this->orderId, $this->expectedTag->version);
    }
}
