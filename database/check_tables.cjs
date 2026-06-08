const fs = require('fs');
const sql = fs.readFileSync('C:/Users/dzian/Downloads/kotlovby.sql', 'utf8');

const tables = ['catalog_comments', 'reviews', 'mont_reviews', 'videoreviews', 'post_comments'];
for (const t of tables) {
    const parts = sql.split('`' + t + '`');
    // Найти INSERT INTO блок
    let count = 0;
    let maxDate = 'нет';
    const idx = sql.indexOf('INSERT INTO `' + t + '`');
    if (idx > -1) {
        const block = sql.substring(idx, idx + 500000);
        const endIdx = block.indexOf(';\n\n--');
        const insertBlock = endIdx > -1 ? block.substring(0, endIdx) : block.substring(0, 100000);
        const rows = insertBlock.match(/\(\d+,/g) || [];
        count = rows.length;
        const dates = insertBlock.match(/'\d{4}-\d{2}-\d{2}/g) || [];
        maxDate = dates.sort().pop() || 'нет';
    }
    console.log(`${t}: ${count} строк, последняя дата: ${maxDate}`);
}

// Также проверим есть ли другие таблицы с "review" в названии
const allTables = sql.match(/CREATE TABLE `([^`]+)`/g) || [];
console.log('\nВсе таблицы:');
allTables.forEach(t => console.log(' ', t));
