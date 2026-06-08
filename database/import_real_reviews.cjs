const fs = require('fs');
const sql = fs.readFileSync('C:/Users/dzian/Downloads/kotlovby.sql', 'utf8');

// ============================================================
// 1. Парсим catalog_products: {old_id -> {sku, rating}}
// ============================================================
const prodMap = {};
const idx = sql.indexOf('INSERT INTO `catalog_products`');
if (idx > -1) {
    const endIdx = sql.indexOf(';\n\n--', idx);
    const block = sql.substring(idx, endIdx > -1 ? endIdx + 1 : idx + 2000000);
    // Строки VALUES могут быть многострочными, парсим через регулярку по articul
    const re = /\((\d+),\s*\d+,\s*\d+,\s*\d+,\s*\d+,\s*\d+,\s*\d+,\s*'[^']*',\s*'[^']*',\s*'[^']*',\s*[^,]+,\s*[01],\s*'(PS-[^']+)',\s*[^,]+,\s*[^,]+,\s*[^,]+,\s*[^,]+,\s*[^,]+,\s*([0-9.]+)/g;
    for (const m of block.matchAll(re)) {
        prodMap[m[1]] = { sku: m[2], rating: parseFloat(m[3]) };
    }
}
console.log('Товаров в catalog_products:', Object.keys(prodMap).length);
console.log('С рейтингом > 0:', Object.values(prodMap).filter(p => p.rating > 0).length);

// ============================================================
// 2. Парсим catalog_comments (506 строк, многострочные)
// ============================================================
const commIdx = sql.indexOf('INSERT INTO `catalog_comments`');
const commEnd = sql.indexOf(';\n\n--', commIdx);
const commBlock = sql.substring(commIdx, commEnd > -1 ? commEnd + 1 : commIdx + 500000);

// Разбиваем на отдельные строки VALUE. Ищем начало каждой: цифра в начале после "("
// Структура: (id, product_id, active, date, subject, phone, email, name, message, adv, disadv)
// Сначала вытащим весь VALUES (...),(...) блок
const valuesStart = commBlock.indexOf('VALUES');
const valuesBlock = commBlock.substring(valuesStart + 6);

// Парсим аккуратно: ищем каждую запись по id
const records = [];
// Используем split по \n(\d+ — начало новой записи
const lines = valuesBlock.split('\n');
let current = '';
for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed) continue;
    if (trimmed.startsWith('(') && current && current.trim().length > 2) {
        records.push(current.trim());
        current = trimmed;
    } else {
        current += ' ' + trimmed;
    }
}
if (current.trim()) records.push(current.trim());

console.log('catalog_comments строк:', records.length);

// ============================================================
// 3. Парсим каждую запись
// ============================================================
function extractFields(row) {
    // Убираем trailing ),  или );
    row = row.replace(/[,;]+$/, '').trim();
    if (row.startsWith('(')) row = row.substring(1);
    if (row.endsWith(')')) row = row.substring(0, row.length - 1);

    // Парсим поля: id, product_id, active, date, subject, phone, email, name, message, adv, disadv
    // Используем state machine для учёта кавычек
    const fields = [];
    let inQuote = false;
    let field = '';
    let i = 0;
    while (i < row.length) {
        const ch = row[i];
        if (!inQuote && ch === "'") {
            inQuote = true;
            i++;
            continue;
        }
        if (inQuote && ch === "'" && row[i+1] === "'") {
            field += "'";
            i += 2;
            continue;
        }
        if (inQuote && ch === "'") {
            inQuote = false;
            i++;
            continue;
        }
        if (!inQuote && ch === ',') {
            fields.push(field.trim());
            field = '';
            i++;
            // skip spaces
            while (i < row.length && row[i] === ' ') i++;
            continue;
        }
        if (!inQuote && (ch === ' ' || ch === '\t') && field === '') {
            i++;
            continue;
        }
        field += ch;
        i++;
    }
    fields.push(field.trim());
    return fields;
}

let imported = 0;
let skipped = 0;
const insertLines = [];

for (const row of records) {
    if (row.includes('`id`') || row.length < 10) continue; // заголовок

    const fields = extractFields(row);
    if (fields.length < 9) { skipped++; continue; }

    const [id, prodId, active, date, subject, phone, email, name, message, adv, disadv] = fields;

    const prod = prodMap[prodId];
    if (!prod) { skipped++; continue; }

    // Собираем текст
    const parts = [];
    if (message && message.trim() && message.trim() !== 'NULL') parts.push(message.trim());
    if (adv && adv.trim() && adv.trim() !== 'NULL') parts.push('Достоинства: ' + adv.trim());
    if (disadv && disadv.trim() && disadv.trim() !== 'NULL') parts.push('Недостатки: ' + disadv.trim());
    const text = parts.join('\n');

    if (!text || text.length < 3) { skipped++; continue; }
    if (!name || name.trim() === 'NULL' || name.trim() === '') { skipped++; continue; }

    const rating = prod.rating > 0 ? Math.min(5, Math.max(1, Math.round(prod.rating))) : 4;
    const isApproved = active === '1' ? 1 : 0;
    const createdAt = (date && date !== 'NULL') ? date : '2020-01-01 00:00:00';

    const esc = s => (s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");

    // Определяем: телефон или email
    const rawPhone = (phone && phone !== 'NULL') ? phone.trim() : '';
    const rawEmail = (email && email !== 'NULL' && email.includes('@')) ? email.trim() : '';
    const rawPhone2 = (!rawPhone && email && email !== 'NULL' && !email.includes('@')) ? email.trim() : rawPhone;

    insertLines.push(
        `INSERT INTO reviews (user_id, author_name, author_phone, author_email, reviewable_type, reviewable_id, rating, text, is_approved, created_at, updated_at)\n` +
        `SELECT NULL, '${esc(name)}', '${esc(rawPhone2)}', ${rawEmail ? "'" + esc(rawEmail) + "'" : 'NULL'}, 'App\\\\Models\\\\Product', p.id, ${rating}, '${esc(text)}', ${isApproved}, '${createdAt}', '${createdAt}'\n` +
        `FROM products p WHERE p.sku = '${prod.sku}' LIMIT 1;\n`
    );
    imported++;
}

console.log('Отзывов к импорту:', imported, '| Пропущено:', skipped);

// ============================================================
// 4. Итоговый SQL
// ============================================================
let output = '-- Импорт отзывов на товары из kotlovby.sql (catalog_comments)\n\n';
output += insertLines.join('\n');
output += '\n-- Пересчёт reviews_count и rating\n';
output += `UPDATE products p SET\n`;
output += `  reviews_count = (SELECT COUNT(*) FROM reviews r WHERE r.reviewable_type = 'App\\\\Models\\\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1),\n`;
output += `  rating = COALESCE(NULLIF((SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.reviewable_type = 'App\\\\Models\\\\Product' AND r.reviewable_id = p.id AND r.is_approved = 1), 0), p.rating);\n`;

fs.writeFileSync('C:/laragon/www/kotlov-new2026/database/import_real_reviews.sql', output);
console.log('SQL записан: database/import_real_reviews.sql');
