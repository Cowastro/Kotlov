-- ============================================
-- 1. Обновление рейтингов товаров
-- ============================================
UPDATE products SET rating = 4.5 WHERE sku = 'PS-000.065';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.068';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.088';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.329';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.439';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.550';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.556';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.623';
UPDATE products SET rating = 4.63636 WHERE sku = 'PS-000.624';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.631';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.632';
UPDATE products SET rating = 5 WHERE sku = 'PS-000.998';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.028';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.033';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.162';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.163';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.165';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.462';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.781';
UPDATE products SET rating = 4 WHERE sku = 'PS-001.821';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.849';
UPDATE products SET rating = 5 WHERE sku = 'PS-001.987';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.046';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.173';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.366';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.412';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.452';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.454';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.455';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.480';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.488';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.489';
UPDATE products SET rating = 4.5 WHERE sku = 'PS-002.614';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.623';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.624';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.631';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.632';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.655';
UPDATE products SET rating = 4.5 WHERE sku = 'PS-002.666';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.691';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.700';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.706';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.709';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.729';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.741';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.756';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.780';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.781';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.800';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.804';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.810';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.814';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.826';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.888';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.903';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.924';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.979';
UPDATE products SET rating = 5 WHERE sku = 'PS-002.984';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.141';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.142';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.198';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.225';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.481';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.482';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.534';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.563';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.684';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.959';
UPDATE products SET rating = 5 WHERE sku = 'PS-003.966';
UPDATE products SET rating = 5 WHERE sku = 'PS-004.186';
UPDATE products SET rating = 5 WHERE sku = 'PS-004.283';
UPDATE products SET rating = 5 WHERE sku = 'PS-004.805';
UPDATE products SET rating = 5 WHERE sku = 'PS-005.242';
UPDATE products SET rating = 5 WHERE sku = 'PS-005.359';
UPDATE products SET rating = 5 WHERE sku = 'PS-005.473';
UPDATE products SET rating = 5 WHERE sku = 'PS-006.110';
UPDATE products SET rating = 5 WHERE sku = 'PS-006.357';
UPDATE products SET rating = 5 WHERE sku = 'PS-006.794';

-- ============================================
-- 2. Импорт отзывов на товары
-- ============================================
INSERT INTO reviews (user_id, author_name, author_email, reviewable_type, reviewable_id, rating, text, is_approved, created_at, updated_at)
SELECT NULL, 'Игорь', '', 'App\\Models\\Product', p.id, 5, 'Купили в этом магазине по совету специалистов. Все устроило. Доставили правда с небольшим опозданием, но все равно отношение нам понравилось. Спасибо
Достоинства: красивый)
Недостатки: пока не обнаружили', 1, '2013-06-05 17:31:00', '2013-06-05 17:31:00'
FROM products p WHERE p.sku = 'PS-001.462' LIMIT 1;

INSERT INTO reviews (user_id, author_name, author_email, reviewable_type, reviewable_id, rating, text, is_approved, created_at, updated_at)
SELECT NULL, ' Никита  ', '', 'App\\Models\\Product', p.id, 5, 'Котел установил пол года назад. С установкой были определенные проблемы. Но причина скорей всего не в нем, а в установщиках. Мне прислали двух парней лет 25-ти. О самом котле могу сказать только хорошее. Обогревает очень хорошо и легко программируется. Расход газа уменьшился, правда не намного. Плохо только то, что котел шумновато работает.', 1, '2013-08-03 10:56:00', '2013-08-03 10:56:00'
FROM products p WHERE p.sku = 'PS-000.329' LIMIT 1;

INSERT INTO reviews (user_id, author_name, author_email, reviewable_type, reviewable_id, rating, text, is_approved, created_at, updated_at)
SELECT NULL, 'юлия', '', 'App\\Models\\Product', p.id, 5, 'Не слишком много ест-котел-супер', 1, '2013-10-03 20:28:00', '2013-10-03 20:28:00'
FROM products p WHERE p.sku = 'PS-000.556' LIMIT 1;

-- ============================================
-- 3. Пересчёт reviews_count и rating
-- ============================================
UPDATE products p SET
  reviews_count = (SELECT COUNT(*) FROM reviews r WHERE r.reviewable_type = 'App\\Models\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1),
  rating = COALESCE((SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.reviewable_type = 'App\\Models\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1), p.rating);
