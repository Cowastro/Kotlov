# Server Artisan Result

- Time: 2026-07-10 07:26:08 UTC
- Task: `tail-log`
- Artisan args: ``
- Log file: `storage/logs/ecokamin-city-seo-rewrite-rest.log`
- Exit code: `0`

```text

   Illuminate\Database\QueryException 

  SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'offset 1' at line 1 (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: kotlov_marketplace, SQL: select `p`.`id`, `p`.`sku`, `p`.`slug`, `p`.`name`, `p`.`content`, `p`.`short_description`, `p`.`meta_description`, `p`.`specs`, `p`.`video_url`, `p`.`documents`, `b`.`name` as `brand`, `c`.`name` as `category` from `products` as `p` left join `brands` as `b` on `b`.`id` = `p`.`brand_id` left join `categories` as `c` on `c`.`id` = `p`.`category_id` where `p`.`content` is not null and `p`.`content` <>  and (`b`.`name` = Ecokamin or `b`.`slug` = Ecokamin) and `p`.`is_archived` = 0 and `p`.`is_active` = 1 order by `p`.`id` asc offset 1)

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

  8   app/Console/Commands/SanitizeProductContentHtmlCommand.php:152
      Illuminate\Database\Query\Builder::get()
      [2m+13 vendor frames [22m

  22  artisan:16
      Illuminate\Foundation\Application::handleCommand()


```
