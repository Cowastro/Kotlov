const fs = require('fs');
const sql = fs.readFileSync('C:/Users/dzian/Downloads/kotlovby.sql', 'utf8');

// 1. Парсим catalog_products: {old_id -> {sku, rating}}
const prodMap = {};
const prodRegex = /\((\d+), \d+, \d+, \d+, \d+, \d+, \d+, '[^']+', '[^']*', '[^']+', [^,]+, [01], '(PS-[^']+)', [^,]+, [^,]+, [^,]+, [^,]+, [^,]+, ([0-9.]+)/g;
for (const m of sql.matchAll(prodRegex)) {
    prodMap[m[1]] = { sku: m[2], rating: parseFloat(m[3]) };
}
const rated = Object.values(prodMap).filter(p => p.rating > 0);
console.log('Товаров с рейтингом > 0:', rated.length);

// 2. Парсим catalog_comments
const commBlock = (sql.split('Zrzut danych tabeli `catalog_comments`')[1] || '').split('-- ')[0];
const commRows = commBlock.match(/\([^\n]+\)/g) || [];
console.log('catalog_comments строк:', commRows.length);

// 3. Генерируем SQL
let out = '';

// UPDATE ratings
out += '-- ============================================\n';
out += '-- 1. Обновление рейтингов товаров\n';
out += '-- ============================================\n';
for (const p of rated) {
    out += `UPDATE products SET rating = ${p.rating} WHERE sku = '${p.sku}';\n`;
}
out += '\n';

// INSERT reviews
out += '-- ============================================\n';
out += '-- 2. Импорт отзывов на товары\n';
out += '-- ============================================\n';

let inserted = 0;
for (const row of commRows) {
    // (id, product_id, active, date, subject, phone, email, name, message, adv, disadv)
    const m = row.match(/\((\d+),\s*(\d+),\s*([01]),\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']+)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/);
    if (!m) { console.log('Не распарсилось:', row.substring(0, 100)); continue; }

    const [, id, prodId, active, date, subject, phone, email, name, message, adv, disadv] = m;
    const prod = prodMap[prodId];
    if (!prod) { console.log('Товар не найден old_id:', prodId); continue; }

    const rating = prod.rating > 0 ? Math.min(5, Math.max(1, Math.round(prod.rating))) : 4;

    let parts = [];
    if (message.trim()) parts.push(message.trim());
    if (adv.trim()) parts.push('Достоинства: ' + adv.trim());
    if (disadv.trim()) parts.push('Недостатки: ' + disadv.trim());
    let text = parts.join('\n');
    if (!text || text.length < 5) { console.log('Пустой текст, пропускаем id:', id); continue; }

    const esc = s => s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const createdAt = date || '2020-01-01 00:00:00';

    out += `INSERT INTO reviews (user_id, author_name, author_email, reviewable_type, reviewable_id, rating, text, is_approved, created_at, updated_at)\n`;
    out += `SELECT NULL, '${esc(name)}', '${esc(email)}', 'App\\\\Models\\\\Product', p.id, ${rating}, '${esc(text)}', ${active}, '${createdAt}', '${createdAt}'\n`;
    out += `FROM products p WHERE p.sku = '${prod.sku}' LIMIT 1;\n\n`;
    inserted++;
}
console.log('Отзывов к импорту:', inserted);

// Пересчёт рейтинга
out += '-- ============================================\n';
out += '-- 3. Пересчёт reviews_count и rating\n';
out += '-- ============================================\n';
out += `UPDATE products p SET
  reviews_count = (SELECT COUNT(*) FROM reviews r WHERE r.reviewable_type = 'App\\\\Models\\\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1),
  rating = COALESCE((SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.reviewable_type = 'App\\\\Models\\\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1), p.rating);\n`;

fs.writeFileSync('C:/laragon/www/kotlov-new2026/database/import_reviews.sql', out);
console.log('Файл записан: database/import_reviews.sql');
