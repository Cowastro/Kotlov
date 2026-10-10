<?php

namespace App\Services\Market\Contracts;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;

interface MarketPriceSourceAdapter
{
    public function key(): string;

    public function collect(MarketPriceSource $source, MarketPriceCollectionRun $run): void;
}
