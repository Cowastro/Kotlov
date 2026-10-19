<?php

namespace Tests\Unit;

use App\Filament\Pages\StockDemandAnalytics;
use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use App\Filament\Resources\InstallRequests\InstallRequestResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use PHPUnit\Framework\TestCase;

class AdminNavigationGroupTest extends TestCase
{
    public function test_sales_group_contains_only_sales_channels_and_commercial_documents(): void
    {
        $this->assertSame('Продажи', OrderResource::getNavigationGroup());
        $this->assertSame('Продажи', SupportConversationResource::getNavigationGroup());
        $this->assertSame('Продажи', CommercialDocumentResource::getNavigationGroup());
        $this->assertSame('Продажи', ContactRequestResource::getNavigationGroup());
        $this->assertSame('Продажи', DealByOrderResource::getNavigationGroup());

        $this->assertSame('Аналитика', StockDemandAnalytics::getNavigationGroup());
        $this->assertSame('Партнёры', InstallerProfileResource::getNavigationGroup());
        $this->assertSame('Партнёры', InstallRequestResource::getNavigationGroup());
        $this->assertSame('Контент', ReviewResource::getNavigationGroup());
        $this->assertSame('Маркетинг', CouponResource::getNavigationGroup());
    }
}
