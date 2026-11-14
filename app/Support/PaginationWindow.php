<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginationWindow
{
    /** @return array<int, string> */
    public static function urls(LengthAwarePaginator $paginator): array
    {
        $page = $paginator->currentPage();
        $last = $paginator->lastPage();
        if ($page < 1 || $page > $last) {
            return [];
        }

        // range(start, end) counts DOWN when start > end. Never pass it
        // an unchecked current page supplied by an external request.
        return $paginator->getUrlRange(max(1, $page - 2), min($last, $page + 2));
    }
}
