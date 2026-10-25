<?php

declare(strict_types=1);

namespace App\Report\Query;

use App\Report\ReadModel\TopProduct;
use App\Report\Repository\ReportReadRepositoryInterface;

final class TopProductsHandler
{
    public function __construct(private readonly ReportReadRepositoryInterface $reports)
    {
    }

    /** @return list<TopProduct> */
    public function handle(TopProductsQuery $query): array
    {
        return $this->reports->topProducts($query->period, $query->limit);
    }
}
