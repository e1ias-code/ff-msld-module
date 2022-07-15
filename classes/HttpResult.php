<?php

class HttpResult
{
    protected $httpCode;
    protected $contentType;
    protected $url;
    protected $arrExt;

    public function __construct($data, $url, $code, $contentType)
    {
        $this->httpCode = $code;
        $this->contentType = $contentType;
        $this->url = $url;
        $this->arrExt = new ArrayExtract($data);
    }

    public function get($path, $default_value = null)
    {
        return $this->arrExt->get($path, $default_value);
    }

    public function getIterable($path, $convert = false)
    {
        return $this->arrExt->getIterable($path, $convert);
    }

    public function getFirstNotEmpty(...$paths)
    {
        return $this->arrExt->getFirstNotEmpty($paths);
    }

    public function isValid()
    {
        if (in_array($this->httpCode, [200, 201, 202])) {
            return true;
        } else {
            return false;
        }
    }

    public function existSubitems($path, $low = null, $high = null)
    {
        $items = $this->get($path);
        if(is_array($items)) {
            $size = sizeof($items);
            // echo $size . '<br>';
            if((is_null($low)) && (is_null($high)) && ($size > 0)) {
                return true;
            }
            if((!is_null($low)) && (is_null($high)) && ($size >= $low)) {
                return true;
            }
            if((is_null($low)) && (!is_null($high)) && ($size <= $high)) {
                return true;
            }
            if((!is_null($low)) && (!is_null($high)) && ($size <= $high) && ($size >= $low)) {
                return true;
            }
        }
        return false;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getError()
    {
        if($this->isValid()) {
            return '';
        } else {
            $data = $this->arrExt->getAll();
            if(is_array($data)) {
                $data = json_encode($data);
            }
            return strip_tags($data);
        }
    }

    public function getAll()
    {
        return $this->arrExt->getAll();
    }

}