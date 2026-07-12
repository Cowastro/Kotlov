# Server Artisan Result

- Time: 2026-07-12 18:46:30 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:inspect-price --article=VM04302 --brand=Varmega --limit=10`
- Log file: `storage/logs/inspect-varmega-vm04302-price.log`
- Exit code: `1`

```text
From https://github.com/Cowastro/Kotlov
   d406635..ae9cf67  main       -> origin/main
Updating d406635..ae9cf67
Fast-forward
 .github/server-artisan-result.md                   | 172 ++++++++++++++++-----
 .github/server-artisan-task.json                   |   6 +-
 .../Commands/InspectProductPriceCommand.php        | 134 ++++++++++++++++
 3 files changed, 274 insertions(+), 38 deletions(-)
 create mode 100644 app/Console/Commands/InspectProductPriceCommand.php

   Illuminate\Database\QueryException 

  SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sp.quantity' in 'field list' (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: kotlov_marketplace, SQL: select `p`.`id`, `p`.`sku`, `p`.`name`, `p`.`price` as `product_price`, `p`.`in_stock` as `product_in_stock`, `p`.`is_active`, `p`.`is_archived`, `b`.`name` as `brand`, `c`.`name` as `category`, `s`.`code` as `supplier_code`, `s`.`name` as `supplier_name`, `sp`.`supplier_article`, `sp`.`supplier_name` as `supplier_product_name`, `sp`.`price` as `supplier_price`, `sp`.`price_byn`, `sp`.`in_stock` as `supplier_in_stock`, `sp`.`quantity`, `sp`.`source_url` from `products` as `p` left join `brands` as `b` on `b`.`id` = `p`.`brand_id` left join `categories` as `c` on `c`.`id` = `p`.`category_id` left join `supplier_products` as `sp` on `sp`.`product_id` = `p`.`id` left join `suppliers` as `s` on `s`.`id` = `sp`.`supplier_id` where (`sp`.`supplier_article` = VM04302 or `sp`.`supplier_article` like %VM04302% or `p`.`name` like %VM04302%) and `b`.`name` = Varmega order by `p`.`id` asc limit 10)

  at vendor/laravel/framework/src/Illuminate/Database/Connection.php:841
    837▕             $exceptionType = ($isUniqueConstraintError = $this->isUniqueConstraintError($e))
    838▕                 ? UniqueConstraintViolationException::class
    839▕                 : QueryException::class;
    840▕ 
  ➜ 841▕             $exception = new $exceptionType(
    842▕                 $this->getNameWithReadWriteType(),
    843▕                 $query,
    844▕                 $this->prepareBindings($bindings),
    845▕                 $e,

      [2m+7 vendor frames [22m

  8   app/Console/Commands/InspectProductPriceCommand.php:81
      Illuminate\Database\Query\Builder::get()
      [2m+13 vendor frames [22m

  22  artisan:16
      Illuminate\Foundation\Application::handleCommand()


```
