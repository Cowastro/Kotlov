# Server Artisan Result

- Time: 2026-07-09 16:37:47 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --supplier=maitek-group --with-source-only --max-attrs=2 --limit=80 --csv=storage/app/reports/product-content-health/maitek-with-source.csv`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
Products with content-health issues: 23
Showing rows: 23 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 3        |
| no_content | 19       |
| no_short   | 21       |
| low_attrs  | 21       |
| no_source  | 0        |
+------------+----------+
By supplier
+--------------+----------+----------+------------+-----------+
| Name         | Products | No photo | No content | Low attrs |
+--------------+----------+----------+------------+-----------+
| maitek-group | 23       | 3        | 19         | 21        |
+--------------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Greolit | 18       | 0        | 18         | 18        |
| СТЭН    | 5        | 3        | 1          | 3         |
+---------+----------+----------+------------+-----------+
By category
+------------------+----------+----------+------------+-----------+
| Name             | Products | No photo | No content | Low attrs |
+------------------+----------+----------+------------+-----------+
| Твердотопливные  | 20       | 0        | 18         | 18        |
| Комплектующие    | 2        | 2        | 0          | 2         |
| Электрические    | 1        | 1        | 1          | 1         |
+------------------+----------+----------+------------+-----------+

+-------+---------------+---------+------------------+--------------+-------+----------------------------------------+----------------+-------------------------------------------------------+
| ID    | SKU           | Brand   | Category         | Suppliers    | Attrs | Issues                                 | Source domains | Product                                               |
+-------+---------------+---------+------------------+--------------+-------+----------------------------------------+----------------+-------------------------------------------------------+
| 20759 | KOTLOV-005925 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 20 кВт без автоматики |
| 20760 | KOTLOV-005926 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 30 кВт без автоматики |
| 20761 | KOTLOV-005927 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 40 кВт без автоматики |
| 20762 | KOTLOV-005928 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 20 кВт с автоматикой  |
| 20763 | KOTLOV-005929 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 30 кВт с автоматикой  |
| 20764 | KOTLOV-005930 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit DEEP plus 40 кВт с автоматикой  |
| 20765 | KOTLOV-005931 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 50 кВт без автоматики     |
| 20766 | KOTLOV-005932 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 60 кВт без автоматики     |
| 20767 | KOTLOV-005933 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 70 кВт без автоматики     |
| 20768 | KOTLOV-005934 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 95 кВт без автоматики     |
| 20769 | KOTLOV-005935 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 99 кВт без автоматики     |
| 20770 | KOTLOV-005936 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 50 кВт с автоматикой      |
| 20771 | KOTLOV-005937 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 60 кВт с автоматикой      |
| 20772 | KOTLOV-005938 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 70 кВт с автоматикой      |
| 20773 | KOTLOV-005939 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 95 кВт с автоматикой      |
| 20774 | KOTLOV-005940 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 99 кВт с автоматикой      |
| 20775 | KOTLOV-005941 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 250 кВт без автоматики    |
| 20776 | KOTLOV-005942 | Greolit | Твердотопливные  | maitek-group | 1     | no_content,no_short,low_attrs          | greolit.by     | Greolit Котел Greolit PROFI 250 кВт                   |
| 11534 | PS-011.534    | СТЭН    | Твердотопливные  | maitek-group | 46    | no_short                               | stenbel.by     | Твердотопливный котел Каракан 20ТЭГ 3                 |
| 12005 | PS-012.005    | СТЭН    | Твердотопливные  | maitek-group | 46    | no_short                               | stenbel.by     | Котел твердотопливный Каракан 10ТПЭ-3                 |
| 20754 | KOTLOV-005920 | СТЭН    | Комплектующие    | maitek-group | 0     | no_photo,low_attrs                     | sten.ru        | СТЭН Заглушка свободного патрубка обратки G 1¼        |
| 20755 | KOTLOV-005921 | СТЭН    | Комплектующие    | maitek-group | 0     | no_photo,low_attrs                     | sten.ru        | СТЭН Заглушка свободного патрубка обратки G 1½        |
| 20798 | KOTLOV-005964 | СТЭН    | Электрические    | maitek-group | 0     | no_photo,no_content,no_short,low_attrs | stenbel.by     | СТЭН Котел "СТЭН ЭВПМ 12" 380                         |
+-------+---------------+---------+------------------+--------------+-------+----------------------------------------+----------------+-------------------------------------------------------+
CSV written: /var/www/h209767/data/www/new.kotlov.by/storage/app/reports/product-content-health/maitek-with-source.csv

```
