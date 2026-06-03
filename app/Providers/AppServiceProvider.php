<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // View::share — доступно во ВСЕХ вьюхах включая @include партиалы
        View::share('navCategories', $this->getNavCategories());
        View::share('navBrands', $this->getNavBrands());
    }

 // Временно — убираем кеш чтобы исключить его как причину
private function getNavBrands(): \Illuminate\Support\Collection
{
    return Brand::active()
        ->orderBy('sort_order')
        ->get(['name', 'slug'])
        ->keyBy('slug');
}

private function getNavCategories()
{
    return Category::query()
        ->where('parent_id', 0)
        ->where('is_active', true)
        ->with(['children' => function ($q) {
            $q->where('is_active', true)
              ->orderBy('sort_order');
        }])
        ->orderBy('sort_order')
        ->get();
}
}