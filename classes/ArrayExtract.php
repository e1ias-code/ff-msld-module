<?php

class ArrayExtract
{

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    protected function searchSubArrValue($arr, $key, $default_value = null)
    {
        if(empty($key)) {
            return $default_value;
        }
        foreach($arr as $item) {
            if(!is_array($item)) {
                continue;
            }
            if(array_key_exists($key, $item)) {
                return $item;
            }
        }
        return $default_value;
    }

    protected function getFirstElem($arr, $default_value = null)
    {
        if(sizeof($arr) == 0) {
            return $default_value;
        }
        return $arr[array_key_first($arr)];
    }

    public function get($path, $default_value = null)
    {
        $data = $this->data;
        $keys = explode('/', $path);
        $last_index = sizeof($keys) - 1;
        for($i = 0; $i <= $last_index; $i++) {
            if(!is_array($data)) {
                return $default_value;
            }
            if($keys[$i] == '*') {
               $data = $this->getFirstElem($data, $default_value);
               continue;
            }
            if(array_key_exists($keys[$i], $data)) {
                $data = $data[$keys[$i]];
            } else {
                return $default_value;
            }
        }
        return $data;
    }

    public function getIterable($path, $convert = false)
    {
        $res = $this->get($path);
        if(is_array($res)) {
            return $res;
        } else {
            if($convert) {
                return [$res];
            } else {
                return [];
            }
        }
    }

    public function getScalar($path, $default_value = null)
    {
        $res = $this->get($path);
        if(is_scalar($res)) {
            return $res;
        } else {
           return $default_value;
        }
    }

    public function getFirstNotEmpty(...$paths)
    {
        foreach($paths as $path) {
            $res = $this->get($path);
            if(!empty($res)) {
                return $res;
            }
        }
    }

    public function getAll()
    {
        return $this->data;
    }
}