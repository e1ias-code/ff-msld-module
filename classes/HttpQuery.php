<?php

class HttpQuery
{

    protected $debug;
    protected $baseUrl;
    protected $user;
    protected $password;
    protected $token;
    protected $headers;
    protected $batchLimit;
    protected $paramsEncodeType = PHP_QUERY_RFC1738;
    protected $resultClass;

    protected const METHODS = [
        'POST'    => 'POST',
        'GET'     => 'GET',
        'PUT'     => 'PUT',
        'PATCH'   => 'PATCH',
        'POSTXML' => 'POST',
    ];
    
    public function __construct($baseUrl, $kwargs = [])
    {
        $this->baseUrl = $baseUrl;
        $this->resultClass = 'HttpResult';
        $this->user = empty($kwargs['user']) ? '' : $kwargs['user'];
        $this->password = empty($kwargs['password']) ? '' : $kwargs['password'];
        $this->token = empty($kwargs['token']) ? '' : $kwargs['token'];
        $this->headers = [];
        $this->batchLimit = 20;
        $this->debug = empty($kwargs['debug']) ? false : true;
    }

    public static function simpleSession($baseUrl)
    {
        return new static($baseUrl);
    }

    public static function withBasicAuth($baseUrl, $user, $password)
    {
        return new static($baseUrl, ['user' => $user, 'password' => $password]);
    }

    public static function withTokenAuth($baseUrl, $token)
    {
        return new static($baseUrl, ['token' => $token]);
    }

    public function setResultClass($class)
    {
        $this->resultClass = $class;
    }

    /**
     * param $type == PHP_QUERY_RFC1738 or PHP_QUERY_RFC3986
     * PHP_QUERY_RFC1738 - пробелы знаком +
     * PHP_QUERY_RFC3986 - пробелы %20
     */
    public function setParamsEncodeType($type)
    {
        $this->paramsEncodeType = $type;
    }

    public function addHeader($value)
    {
        $this->headers[] = $value;
    }

    protected function curlInit($url, $http_method)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_method);
        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }
        return $ch;
    }

    protected function setBasicAuth($ch)
    {
        if ($this->user && $this->password) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);
        }
    }

    protected function getTokenHeader()
    {
        if ($this->token) {
            return 'Authorization: Bearer ' . $this->token;
        }
    }

    protected function setCurlHeaders($ch, $headers = [])
    {
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }

    protected function convertResult($data, $format)
    {
        switch($format) {
            case 'application/json;charset=utf-8':
            case 'application/hal+json':
            case 'application/problem+json':
            case 'application/vnd.api+json;charset=UTF-8':
            case 'application/vnd.api+json; charset=utf-8':
            case 'application/json':
                $res = json_decode($data, true);
                break;
            default:
                $res = $data;
        }
        return $res;
    }

    public function executeOne($url = '', $url_params = [], $method = 'GET', $post_data=[]) {
        if(stristr($url, 'http://') === false && stristr($url, 'https://') === false) {
            $url = $this->baseUrl . $url;
        }
        if(!empty($url_params)) {
            $url = $url . '?' . http_build_query($url_params, '', '&', $this->paramsEncodeType);
        }
        $headers = $this->headers;
        $ch = $this->curlInit($url, self::METHODS[$method]);
        $this->setBasicAuth($ch);
        $token = $this->getTokenHeader();
        if($token) {
            $headers[] = $token;
        }
        if((in_array($method, ['POST', 'PATCH', 'PUT'])) && (!empty($post_data))) {
            $data = json_encode($post_data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Type:application/json';
        }
        if((in_array($method, ['POSTXML'])) && (!empty($post_data))) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $headers[] = 'Content-Type:application/xml';
        }
        $this->setCurlHeaders($ch, $headers);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $c_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $res = $this->convertResult($res, $c_type);
        $result = new $this->resultClass($res, $url, $code, $c_type);
        curl_close($ch);
        return  $result;
    }

    public function execute($url = '', $params = [], $method='GET', $post_data=[], $retryCount=1)
    {
        $retry = ++$retryCount;
        $res = null;
        while($retry) {
            $res = $this->executeOne($url, $params, $method, $post_data);
            // var_dump($res->getHttpCode(), $res->getError());
            if($res->isValid()) {
                break;
            }
            if($res->getHttpCode() != 429) {
                break;
            }
            $retry--;
            sleep(1);
        }
        return $res;
    }

    public function batchExecute($condition_func, $url, $params = [], $retryCount=1)
    {
        $results = [];
        $last_result = null;
        $i = 1;
        while(true) {
            if($i > $this->batchLimit) {
                break;
            }
            list($url, $params) = $condition_func($i, $url, $params, $last_result);
            if(!$url) {
                break;
            }
            $last_result = $this->execute($url, $params, 'GET', [], $retryCount);
            $results[] = $last_result;
            $i++;
        }
        return $results;
    }

}


