<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Models\Blog\Blog;
use Cviebrock\LaravelElasticsearch\Facade as ElasticSearch;

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
        $size = $request->input('ps', 10);
        $from = ($request->input('p', 1) - 1) * $size;
        $q = $request->get('q', '');
        if (!$q) {
            return collect();
        }
        $default = [
            'index' => (new Blog())->getEsAliasIndex(),
            'type' => (new Blog())->getEsType(),
            'size' => $size,
            'from' => $from,
        ];
        $criteria = [
            'sort' => ['reading' => 'desc'],
            'query' => [
                'multi_match' => [
                    'query' => $q,
                    'fields' => ['title^3', 'description'],
                ],
            ],
        ];
        $result = ElasticSearch::search(array_merge($default, ['body' => $criteria]));

        return collect([
            'count' => $result['hits']['total'],
            'list' => $this->buildResponse(collect($result['hits']['hits']))]
        );
    }

    /**
     * buildResponse
     *
     * @param Illuminate\Support\Collection $result
     *
     * @return Illuminate\Support\Collection
     */
    public function buildResponse(Collection $result): Collection
    {
        return $result->map(function ($item) {
            return $item['_source'];
        });
    }

    public function suggest(Request $request): Collection
    {

    }
}
