<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\Banner;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Coupon;
use App\Models\EmailSubscriber;
use App\Models\Faq;
use App\Models\InstallerApplication;
use App\Models\InstallerProfile;
use App\Models\InstallRequest;
use App\Models\IntegrationCategory;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\MarketPriceObservation;
use App\Models\MarketPriceSource;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Review;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\SupplierSync;
use App\Models\SupplierSyncRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AdminRoleAccess
{
    /**
     * Models exposed through the administrator panel. A manager is denied by
     * default and receives only the explicitly listed operational abilities.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const MANAGER_ABILITIES = [
        Order::class => ['viewAny', 'view', 'update'],
        ContactRequest::class => ['viewAny', 'view', 'update'],
        InstallRequest::class => ['viewAny', 'view', 'update'],
        Review::class => ['viewAny', 'view', 'update'],
        MarketPriceObservation::class => ['viewAny', 'view'],
    ];

    /** @var list<class-string<Model>> */
    private const ADMIN_PANEL_MODELS = [
        Attribute::class,
        Banner::class,
        BlogCategory::class,
        BlogPost::class,
        Brand::class,
        Category::class,
        ContactRequest::class,
        Coupon::class,
        EmailSubscriber::class,
        Faq::class,
        InstallerApplication::class,
        InstallerProfile::class,
        InstallRequest::class,
        IntegrationCategory::class,
        IntegrationExchangeRun::class,
        IntegrationIssue::class,
        IntegrationProduct::class,
        IntegrationSource::class,
        MarketPriceObservation::class,
        MarketPriceSource::class,
        Order::class,
        Page::class,
        Product::class,
        Redirect::class,
        Review::class,
        Supplier::class,
        SupplierApplication::class,
        SupplierSync::class,
        SupplierSyncRun::class,
        User::class,
    ];

    /**
     * Return null when this access layer is not responsible for the decision.
     */
    public static function authorize(User $user, string $ability, array $arguments): ?bool
    {
        if (! $user->isOperationalManager()) {
            return null;
        }

        $subject = $arguments[0] ?? null;
        $modelClass = $subject instanceof Model ? $subject::class : $subject;

        if (! is_string($modelClass) || ! in_array($modelClass, self::ADMIN_PANEL_MODELS, true)) {
            return null;
        }

        return in_array($ability, self::MANAGER_ABILITIES[$modelClass] ?? [], true);
    }
}
