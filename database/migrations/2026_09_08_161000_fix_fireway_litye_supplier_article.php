<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SupplierProduct;

/**
 * Fix double "fireway-fireway-" prefix in supplier_article for Fireway litye batch.
 *
 * In the litye migration slugs already start with "fireway-" (e.g. "fireway-a101-nabor-furnitury"),
 * but supplier_article was built as "fireway-" . slug → "fireway-fireway-a101-nabor-furnitury".
 * This strips the leading duplicate prefix.
 */
return new class extends Migration
{
    public function up(): void
    {
        SupplierProduct::where('supplier_article', 'like', 'fireway-fireway-%')
            ->each(function (SupplierProduct $sp) {
                $fixed = preg_replace('/^fireway-fireway-/', 'fireway-', $sp->supplier_article);
                $sp->update([
                    'supplier_article'            => $fixed,
                    'supplier_article_normalized' => $fixed,
                    'supplier_article_compact'    => $fixed,
                ]);
            });
    }

    public function down(): void {}
};
