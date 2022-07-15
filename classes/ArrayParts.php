<?php

class ArrayParts
{
    private $data;

    public function __construct()
    {
        $this->data = [];
    }

    public function add($arr)
    {
        if(is_array($arr)) {
            $this->data = array_merge_recursive($this->data, $arr);
        }
    }

    public function getAll()
    {
        return $this->data;
    }
}