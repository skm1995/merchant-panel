<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Order\Entity\OrderId;
use App\Order\Exception\OrderNotFoundException;
use App\Order\Request\OrderAccessTokenHeader;
use App\Payment\Entity\PaymentId;
use App\Payment\Exception\InvalidWebhookPayloadException;
use App\Payment\Exception\InvalidWebhookSignatureException;
use App\Payment\Exception\OrderNotPayableException;
use App\Payment\Exception\PaymentAmountMismatchException;
use App\Payment\Exception\PaymentIdempotencyKeyReusedException;
use App\Payment\Exception\PaymentNotFoundException;
use App\Payment\Exception\UnknownPaymentProviderException;
use App\Payment\Query\GetPaymentQuery;
use App\Payment\Request\PaymentWebhookRequest;
use App\Payment\Request\StartPaymentRequest;
use App\Payment\Response\PaymentResponse;
use App\Payment\Response\WebhookResponse;
use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use App\Shared\Http\MapException;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use App\Shared\Messaging\Bus\CommandBusInterface;
use App\Shared\Messaging\Bus\QueryBusInterface;

final readonly class PaymentApiController
{
    private const string START_ROUTE_PATH = '/api/v1/orders/{id:' . OrderId::PATTERN . '}/payments';
    private const string PAYMENT_ROUTE_PATH = '/api/v1/payments/{id:' . PaymentId::PATTERN . '}';
    private const string WEBHOOK_ROUTE_PATH = '/api/v1/payments/webhooks/{provider:[a-z]+}';
    private const string PAYMENT_LOCATION_FORMAT = '/api/v1/payments/%s';
    private const string ID_PARAMETER = 'id';

    public function __construct(
        private CommandBusInterface $commands,
        private QueryBusInterface $queries,
        private Responder $responder,
    ) {
    }

    #[Route(HttpMethodEnum::POST, self::START_ROUTE_PATH)]
    #[MapException(OrderNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(OrderNotPayableException::class, ResponseStatusEnum::CONFLICT)]
    #[MapException(PaymentIdempotencyKeyReusedException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function start(Request $request): void
    {
        $command = StartPaymentRequest::toCommand($request);
        $paymentId = $this->commands->dispatch($command);
        $payment = $this->queries->ask(new GetPaymentQuery($paymentId, $command->orderAccessToken));

        $this->responder->created(PaymentResponse::fromView($payment), sprintf(self::PAYMENT_LOCATION_FORMAT, $paymentId->value));
    }

    #[Route(HttpMethodEnum::GET, self::PAYMENT_ROUTE_PATH)]
    #[MapException(PaymentNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    public function show(Request $request): void
    {
        $paymentId = PaymentId::fromString($request->routeParameter(self::ID_PARAMETER));

        $this->responder->json(PaymentResponse::fromView($this->queries->ask(new GetPaymentQuery($paymentId, OrderAccessTokenHeader::read($request)))));
    }

    #[Route(HttpMethodEnum::POST, self::WEBHOOK_ROUTE_PATH)]
    #[MapException(UnknownPaymentProviderException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(InvalidWebhookSignatureException::class, ResponseStatusEnum::UNAUTHORIZED)]
    #[MapException(InvalidWebhookPayloadException::class, ResponseStatusEnum::BAD_REQUEST)]
    #[MapException(PaymentNotFoundException::class, ResponseStatusEnum::NOT_FOUND)]
    #[MapException(PaymentAmountMismatchException::class, ResponseStatusEnum::UNPROCESSABLE_CONTENT)]
    public function webhook(Request $request): void
    {
        $result = $this->commands->dispatch(PaymentWebhookRequest::toCommand($request));

        $this->responder->json(WebhookResponse::fromResult($result));
    }
}
