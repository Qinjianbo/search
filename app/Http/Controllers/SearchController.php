<?php

namespace App\Http\Controllers;

use App\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * 搜索
     *
     * @param Request $request
     *
     * @return Illuminate\Support\Collection
     */
    public function search(Request $request): Collection
    {
        $size = $request->get('size', 10);
        $from = $request->get('from', 0);
        $q = $request->get('query', '');
        if (!$q) {
            return collect();
        }

    }
}
