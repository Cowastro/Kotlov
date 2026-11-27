<?php

namespace App\Services\Shipping;

use RuntimeException;

final class EvropochtaShipmentException extends RuntimeException
{
    public const LABEL_PENDING = 404;
}
