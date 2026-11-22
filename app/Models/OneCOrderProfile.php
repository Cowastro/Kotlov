<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A way to sell in 1C («Розница СБГ бай», «Опт СанБизнесГруп»): the header of a real «Заказ клиента» it was made
 * from (organization, warehouse, agreement, subdivision, payment terms…), with the customer's own data removed.
 */
class OneCOrderProfile extends Model
{
    /** The customer is the profile's own partner, e.g. «Розничный покупатель». */
    public const RETAIL = 'retail';

    /** The customer is the company of the order, found in 1C by its УНП. */
    public const COMPANY = 'company';

    /** The 1C document the scheme makes. */
    public const CUSTOMER_ORDER = 'customer';

    public const SUPPLIER_ORDER = 'supplier';

    public const DOCUMENTS = [
        self::CUSTOMER_ORDER => 'Заказ клиента',
        self::SUPPLIER_ORDER => 'Заказ поставщику',
    ];

    public const CUSTOMER_MODES = [
        self::RETAIL => 'Клиент из схемы (розничный покупатель)',
        self::COMPANY => 'Компания заказа — по УНП',
    ];

    protected $table = 'onec_order_profiles';

    protected $fillable = ['name', 'document', 'customer_mode', 'is_active', 'is_default', 'header', 'row_defaults', 'payment_stage', 'meta', 'template_number', 'sort'];

    protected $casts = [
        'header' => 'array',
        'row_defaults' => 'array',
        'payment_stage' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $profile): void {
            if ($profile->is_default) {
                static::query()->whereKeyNot($profile->id)->where('document', $profile->document ?: self::CUSTOMER_ORDER)
                    ->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }
}
