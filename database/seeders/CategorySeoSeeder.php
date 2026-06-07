<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        // Исправляем испорченные записи: "в в Беларуси" → "в %city%"
        // и "в Беларуси и в другие города Беларуси" → восстанавливаем %city%
        foreach ($categories as $category) {
            $fix = [];
            foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
                if (!$category->$field) continue;
                $val = $category->$field;
                // Двойной предлог
                if (str_contains($val, 'в в Беларуси')) {
                    $val = str_replace('в в Беларуси', 'в %city%', $val);
                    $fix[$field] = $val;
                }
                // Одиночный "в Беларуси" без двойного — восстанавливаем плейсхолдер
                // только в старых текстах (содержат "доставкой в Беларуси")
                if (!str_contains($val, '%city%') && str_contains($val, 'в Беларуси')) {
                    $val = str_replace('в Беларуси', 'в %city%', $val);
                    $fix[$field] = $val;
                }
            }
            if (!empty($fix)) {
                $category->update($fix);
                $category->refresh();
            }
        }

        // Перезагружаем после фикса
        $categories = Category::all();

        foreach ($categories as $category) {
            $name       = $category->name;
            $nameLower  = mb_strtolower($name);
            $updated    = [];

            // Заполняем пустые meta_title (с плейсхолдером %city%)
            if (empty($category->meta_title)) {
                $updated['meta_title'] = "{$name} — купить в %city% | KOTLOV";
            }

            // Заполняем пустые meta_description
            if (empty($category->meta_description)) {
                $updated['meta_description'] = "Купить {$nameLower} в %city% по выгодным ценам. "
                    . "Большой выбор в каталоге kotlov.by. "
                    . "Быстрая доставка, профессиональные консультации и гарантия качества.";
            }

            // Заполняем пустые meta_keywords
            if (empty($category->meta_keywords)) {
                $updated['meta_keywords'] = "{$name}, купить {$nameLower} в %city%, "
                    . "{$nameLower} цена, {$nameLower} каталог, kotlov.by";
            }

            if (!empty($updated)) {
                $category->update($updated);
            }
        }

        $this->command->info('Category SEO filled: ' . $categories->count() . ' categories processed.');
    }
}
