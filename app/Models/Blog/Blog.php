<?php

/*
 * (c) 秦建波 <279250819@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Models\Blog;

use App\Models\Model;
use Cviebrock\LaravelElasticsearch\Facade as Elasticsearch;

class Blog extends Model
{
    protected $table = 'blog';

    protected $esIndex = [0 => 'blog_v0', 1 => 'blog_v1'];

    protected $esAliasIndex = 'blog';

    protected $esType = 'boboidea';

    public function __construct()
    {
        parent::__construct();
        $this->esAliasIndex = $this->esAliasIndex . env('APP_ENV');
        $this->esIndex = collect(['blog_v0', 'blog_v1'])->map(function ($item) {
            return sprintf('%s_%s', $item, env('APP_ENV'));
        });
    }

    public function getEsIndex()
    {
        return $this->esIndex;
    }

    public function getEsAliasIndex()
    {
        return $this->esAliasIndex;
    }

    public function getEsType()
    {
        return $this->esType;
    }

    public function bulkIndex()
    {
        $unused = $this->getUnusedIndice();
        $exists = Elasticsearch::indices()->exists($unused);
        if ($exists) {
            Elasticsearch::indices()->delete($unused);
        }
        $this->createIndice($unused);
        $this->putMapping($unused);
        foreach ($this->getList(collect())->chunk(10) as $chunk) {
            $params = ['body' => []];
            foreach ($chunk as $product) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $unused['index'],
                        '_type' => $this->esType,
                        '_id' => $product['id'],
                    ],
                ];
                $params['body'][] = $product->toArray();
            }
            Elasticsearch :: bulk($params);
            info('bulk '. $chunk->count() . ' ok.');
            unset($params, $chunk);
            sleep(1);
        }
        $actions = [];
        if ($used = $this->getUsedIndice()) {
            $actions[] = ['remove' => $used + ['alias' => $this->esAliasIndex]];
        }
        $actions[] = ['add' => $unused + ['alias' => $this->esAliasIndex]];
        info('actions'. json_encode($actions));

        Elasticsearch::indices()->updateAliases(['body' => ['actions' => $actions]]);

        return true;
    }

    public function getUnusedIndice()
    {
        try {
            $indice = Elasticsearch::indices()->getAlias(['name' => $this->esAliasIndex]);

            return collect($this->esIndex)->diff(collect(array_keys($indice)))
				->mapWithKeys(function ($item) {
				    return ['index' => $item];
				})
				->toArray();
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return ['index' => $this->esIndex[1]];
        }
    }

    public function createIndice(array $unused)
    {
        return Elasticsearch::indices()->create(
	        $unused + [
                   'body' => [
	              'settings' => [
	                  'number_of_shards' => 2,
	                  'number_of_replicas' => 2,
	                  'index' => [
	                       'analysis' => [
	                       'analyzer' => [
	                          'ik_max_word' => ['tokenizer' => 'ik_max_word'],
	                          'ik_smart' => ['tokenizer' => 'ik_smart'],
	                        ],
	                     ],
	                  ],
	              ],
	          ],
	     ]
        );
    }

    public function putMapping($unused)
    {
        $properties = [
	    'title' => [
	        'type' => 'text', 'boost' => 50.0, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'description' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'combined' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	    ],
	    'reading' => ['type' => 'long'],
	    'year_month' => ['type' => 'long'],
	];

        Elasticsearch::indices()->putMapping(
		    $unused + [
			'type' => $this->esType,
			'body' => [
			    $this->esType => [
				'_source' => ['enabled' => true],
			        'include_in_all' => false,
				'_all' => [
					'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart',
					'enabled' => false,
				],
				'properties' => $properties,
			    ],
		        ],
		    ]
	);
    }
}
