<?php

declare(strict_types=1);

namespace App\Simulation\Infrastructure;

enum BuyerStepEnum
{
    case CREATE_CART;
    case ADD_ITEM;
    case CHECK_OUT;
    case START_PAYMENT;
    case CONFIRM_PAYMENT;
}
