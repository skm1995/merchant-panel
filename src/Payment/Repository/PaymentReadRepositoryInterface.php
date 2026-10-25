<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\PaymentId;
use App\Payment\ReadModel\PaymentView;

interface PaymentReadRepositoryInterface
{
    public function find(PaymentId $id): ?PaymentView;
}
