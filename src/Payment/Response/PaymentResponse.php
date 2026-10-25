<?php

declare(strict_types=1);

namespace App\Payment\Response;

use App\Payment\ReadModel\PaymentView;
use App\Shared\Contract\Arrayable;
use App\Shared\Response\MoneyResponse;

final readonly class PaymentResponse implements Arrayable
{
    private const string ID_KEY = 'id';
    private const string ORDER_ID_KEY = 'order_id';
    private const string PROVIDER_KEY = 'provider';
    private const string STATUS_KEY = 'status';
    private const string AMOUNT_KEY = 'amount';
    private const string CHECKOUT_URL_KEY = 'checkout_url';

    public function __construct(
        public string $id,
        public string $orderId,
        public string $provider,
        public string $status,
        public MoneyResponse $amount,
        public string $checkoutUrl,
    ) {
    }

    public static function fromView(PaymentView $payment): self
    {
        return new self(
            $payment->id->value,
            $payment->orderId->value,
            $payment->provider->value,
            $payment->status->value,
            MoneyResponse::fromMoney($payment->amount),
            $payment->checkoutUrl,
        );
    }

    public function toArray(): array
    {
        return [
            self::ID_KEY => $this->id,
            self::ORDER_ID_KEY => $this->orderId,
            self::PROVIDER_KEY => $this->provider,
            self::STATUS_KEY => $this->status,
            self::AMOUNT_KEY => $this->amount->toArray(),
            self::CHECKOUT_URL_KEY => $this->checkoutUrl,
        ];
    }
}
