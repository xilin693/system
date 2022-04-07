<?php

namespace king\lib;

use king\core\Instance;
use king\core\Error;
use Elasticsearch\ClientBuilder;

class Es7 extends Instance
{
    private $config = ['hosts' => ['127.0.0.1:9200'], 'index' => 'my_index'];
    private $client;

    public function __construct($config)
    {
        foreach ($config as $key => $value) {
            if (isset($this->config[$key])) {
                $this->config[$key] = $value;
            }
        }

        $this->client = ClientBuilder::create()->setHosts($this->config['hosts'])->build();
        $this->params = [
            'index' => $this->config['index'],
            'body' => []
        ];
    }

    public function createIndex($properties, $shared = 5, $replicas = 0)
    {
        $params = [
            'index' => $this->config['index'],
            'body' => [
                'settings' => [
                    'number_of_shards' => $shared,
                    'number_of_replicas' => $replicas
                ],
                'mappings' => [
                    'properties' => $properties
                ]
            ]
        ];

        return $this->client->indices()->create($params);
    }

    public function deleteIndex()
    {
        $params = [
            'index' => $this->config['index']
        ];

        return $this->client->indices()->delete($params);
    }

    public function existIndex()
    {
        $params = [
            'index' => $this->config['index']
        ];

        return $this->client->indices()->exists($params);
    }

    public function searchScrollAllDoc($query, $size = 10000, $time = '10s')
    {
        $params['index'] = $this->config['index'];
        if (!empty($query)) {
            if (!is_array($query)) {
                $query = json_decode($query, true);
            }
            $params['body']['query'] = $query;
        }
        $params['body']['size'] = $size;
        $params['scroll'] = $time;
        $params['body']['sort'] = ["_doc"];
        $response = $this->client->search($params);
        $data = [];
        $total = $response['hits']['total']['value'] ?? 0;
        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            $hits = $response['hits']['hits'];
            foreach ($hits as $item) {
                $data[] = ['id' => $item['_id'], 'content' => $item['_source']];
            }
            $scroll_id = $response['_scroll_id'];
            $response = $this->client->scroll([
                    "scroll_id" => $scroll_id,
                    "scroll" => $time
                ]
            );
        }

        $rs = ['total' => $total, 'data' => $data];
        return $rs;
    }

    public function addDoc($doc, $id = '')
    {
        $doc = (array)$doc;

        $params = [
            'index' => $this->config['index'],
            'body' => $doc
        ];

        if ($id) {
            $params['id'] = $id;
        }

        return $this->client->index($params);
    }

    public function addBulkDoc($bulk, $pk = 'id')
    {
        if (count($bulk) > 0) {
            foreach ($bulk as $row) {
                $row = (array)$row;
                $index = [
                    '_index' => $this->config['index'],
                ];

                if (isset($row[$pk])) {
                    $index['_id'] = $row[$pk];
                }

                $params['body'][] = [
                    'index' => $index
                ];
                $params['body'][] = $row;
            }

            return $this->client->bulk($params);
        }
    }

    public function getDoc($id)
    {
        $params = [
            'index' => $this->config['index'],
            'id' => $id
        ];

        return $this->client->get($params);
    }

    public function updateDoc($id, $row)
    {
        $params = [
            'index' => $this->config['index'],
            'id' => $id,
            'body' => [
                'doc' => $row
            ]
        ];

        return $this->client->update($params);
    }

    public function deleteDoc($id)
    {
        $params = [
            'index' => $this->config['index'],
            'id' => $id
        ];

        return $this->client->delete($params);
    }

    public function searchDoc($query, $from = 0, $size = 10, $mix = [])
    {
        $order = $mix['order'] ?? '';
        $field = $mix['field'] ?? '';
        $params['index'] = $this->config['index'];
        if (!is_array($query)) {
            $query = json_decode($query, true);
        }

        if (!empty($query)) {
            $params['body']['query'] = $query;
        }

        if (!empty($field)) {
            if (!is_array($field)) {
                $field = json_decode($field, true);
            }
            $params['body']['_source'] = $field;
        }

        if ($size > 0) {
            $params['body']['size'] = $size;
        }

        if ($from !== false) {
            $params['body']['from'] = $from;
        }

        if (is_array($order)) {
            $params['body']['sort'] = $order;
        }

        $response = $this->client->search($params);
        $total = $response['hits']['total'];
        $hits = $response['hits']['hits'];
        $rs = ['total' => $total, 'data' => []];

        foreach ($hits as $item) {
            if (isset($item['sort'])) {
                $item['_source']['sort'] = $item['sort'];
            }

            $rs['data'][] = ['id' => $item['_id'], 'content' => $item['_source']];
        }

        return $rs;
    }

    public function searchDocGroup($query, $from = 0, $size = 10, $mix = [])
    {
        $order = $mix['order'] ?? '';
        $group = $mix['group'] ?? '';
        $count = $mix['count'] ?? '';
        $field = $mix['field'] ?? '';
        $params['index'] = $this->config['index'];
        if (!is_array($query)) {
            $query = json_decode($query, true);
        }

        if (!empty($query)) {
            $params['body']['query'] = $query;
        }
        if (!empty($field)) {
            if (!is_array($field)) {
                $field = json_decode($field, true);
            }
            $params['body']['_source'] = $field;
        }
        $params['body']['from'] = 0;
        $params['body']['size'] = 10000;
        if (is_array($order)) {
            $params['body']['sort'] = $order;
        }
        if (!empty($group)) {
            $params['body']['aggs']['rs']['terms']['field'] = $group;
            $params['body']['aggs']['rs']['terms']['order'] = ["_term" => "desc"];
            if ($size > 0) {
                $params['body']['aggs']['rs']['terms']['size'] = 10000;
            }
            if (is_array($count)) {
                $params['body']['aggs']['rs']['aggs'] = $count;
            }
        }
        $response = $this->client->search($params);
        $hits = $response['hits']['hits'];
        $aggregations = $response['aggregations']['rs']['buckets'];
        $data = [];
        foreach ($aggregations as $key => &$item) {
            if ($key < $from || $key >= ($from + $size)) {
                continue;
            }
            foreach ($hits as $value) {
                if ($item['key'] == $value['_source'][$group]) {
                    $value['_source']['sort'] = $value['sort'];
                    $item['list'][] = $value['_source'];
                }
            }
            $data[] = $item;
        }
        $rs['total'] = count($aggregations);
        $rs['data'] = $data;
        return $rs;
    }

    public function deleteBulk($ids)
    {
        foreach ($ids as $id) {
            $this->deleteDoc($id);
        }
    }
}