<?php

class OAQuery
{
    protected $httpQuery;

    public function __construct($baseUrl, $user, $password)
    {
        $this->httpQuery = HttpQuery::withBasicAuth($baseUrl, $user, $password);
        $this->httpQuery->addHeader('Accept: application/json');
    }

    public function get($subUrl, $params = [])
    {
        return $this->httpQuery->execute($subUrl, $params, 'GET', [], 1);
    }

    public function getAllPages($subUrl, $params = [])
    {
        $oa_pager = function($iteration, $init_url, $init_params, $res) {
            if(!$res) {
                return [$init_url, $init_params];
            }
            $page = $iteration;
            $page_count = $res->get('page_count', 1);
            if($page <= $page_count) {
                $new_params = $init_params;
                $new_params['page'] = $page;
                return [$init_url, $new_params];
            }
        };
        if(empty($params['per_page'])) {
            $params['per_page'] = 250;
        }
        return $this->httpQuery->batchExecute($oa_pager, $subUrl, $params, 1);
    }

    public function add($subUrl, $json)
    {
        return $this->httpQuery->execute($subUrl, [], 'POST', $json, 1);
    }

    public function update($subUrl, $json)
    {
        return $this->httpQuery->execute($subUrl, [], 'PATCH', $json, 1);
    }






}