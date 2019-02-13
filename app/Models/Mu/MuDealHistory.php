<?php

/*
 * (c) 秦建波 <279250819@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Models\Mu;

use App\Models\Model;
use Cviebrock\LaravelElasticsearch\Facade as Elasticsearch;
use Illuminate\Support\Collection;

class MuDealHistory extends Model
{
    protected $table = 'muDealHistory';

    protected $esIndex = [0 => 'mu_deal_history_v0', 1 => 'mu_deal_history_v1'];

    protected $esAliasIndex = 'mu_deal_history';

    protected $esType = 'boboidea';

    protected $connection = 'mysql_5173';

    public function __construct()
    {
        parent::__construct();
        $this->esAliasIndex = $this->esAliasIndex . env('APP_ENV');
        $this->esIndex = collect(['mu_deal_history_v0', 'mu_deal_history_v1'])->map(function ($item) {
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

    /**
     * 批量创建索引
     */
    public function bulkIndex()
    {
        $unused = $this->getUnusedIndice();
        $exists = Elasticsearch::indices()->exists($unused);
        if ($exists) {
            Elasticsearch::indices()->delete($unused);
        }
        $this->createIndice($unused);
        $this->putMapping($unused);
        $list = $this->getList(collect(['select' => 'id,name,type,price,dealTime,created_at,gameArea,link']))
            ->map(function ($item) {
                $item['created_at'] = strval($item['created_at']);
                $item['dealTime'] = strval($item['dealTime']);

                return $item;
            });
        foreach ($list->chunk(10) as $chunk) {
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

    /**
     * 获取一个未使用的索引
     *
     * @return mixed
     */
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

    /**
     * 创建索引
     *
     * @return mixed
     */
    public function createIndice(array $unused)
    {
        return Elasticsearch::indices()->create(
	        $unused + [
                   'body' => [
	              'settings' => [
	                  'number_of_shards' => 1,
	                  'number_of_replicas' => 1,
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

    /**
     * 创建字段映射
     *
     * @return mixed
     */
    public function putMapping($unused)
    {
        $properties = [
	    'name' => [
	        'type' => 'text', 'boost' => 50.0, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'type' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'link' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'gameArea' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart', 'copy_to' => 'combined',
	        'fields' => ['keyword' => ['type' => 'keyword']],
	    ],
	    'combined' => [
	        'type' => 'text', 'boost' => 2, 'analyzer' => 'ik_max_word',
	        'search_analyzer' => 'ik_smart',
	    ],
	    'reading' => ['type' => 'long'],
	    'price' => ['type' => 'double'],
	    'dealTime' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'],
	    'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'],
	    'id' => ['type' => 'long'],
    ];

        Elasticsearch::indices()->putMapping(
		    $unused + [
			'type' => $this->esType,
			'body' => [
			    $this->esType => [
				'_source' => ['enabled' => true],
				'properties' => $properties,
			    ],
		        ],
		    ]
	    );
    }

    /**
     * 获取在使用中的索引
     *
     * @return mixed
     */
    public function getUsedIndice()
    {
        try {
            $indice = Elasticsearch::indices()->getAlias(['name' => $this->esAliasIndex]);

            return collect(array_keys($indice))->mapWithKeys(function ($item) {
                return ['index' => $item];
            })
            ->toArray();
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return;
        }
    }

    /**
     * 获取博文列表
     *
     * @return mixed
     */
    public function getList(Collection $input)
    {
        return self::select(explode(',', $input->get('select', '*')))
            ->get();
    }
}
