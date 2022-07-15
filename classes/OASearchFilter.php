<?php

class OASearchFilter
{
    protected $where;
    protected $sort;
    protected $perPage;
    protected $currentPage;

    public function __construct()
    {
        $this->where = [];
        $this->sort = [];
        $this->perPage = 50;
        $this->currentPage = null;

    }

    protected function singleValueRule($field, $operator, $value = null)
    {
        if(empty($value)) {
            $this->where[] = ['type' => $operator, 'field' => $field];
        } else {
            $this->where[] = ['type' => $operator, 'field' => $field, 'value' => $value];
        }
    }

    protected function listValueRule($field, $operator, $values)
    {
        $this->where[] = ['type' => $operator, 'field' => $field, 'values' => $values];
    }

    public function eq($field, $value)
    {
        $this->singleValueRule($field, 'eq', $value);
        return $this;
    }

    public function neq($field, $value)
    {
        $this->singleValueRule($field, 'neq', $value);
        return $this;
    }

    public function lt($field, $value)
    {
        $this->singleValueRule($field, 'lt', $value);
        return $this;
    }

    public function lte($field, $value)
    {
        $this->singleValueRule($field, 'lte', $value);
        return $this;
    }

    public function gt($field, $value)
    {
        $this->singleValueRule($field, 'gt', $value);
        return $this;
    }

    public function gte($field, $value)
    {
        $this->singleValueRule($field, 'gte', $value);
        return $this;
    }

    public function like($field, $value)
    {
        $this->singleValueRule($field, 'like', $value);
        return $this;
    }

    public function ilike($field, $value)
    {
        $this->singleValueRule($field, 'ilike', $value);
        return $this;
    }

    public function isNull($field)
    {
        $this->singleValueRule($field, 'isnull');
        return $this;
    }

    public function isNotNull($field)
    {
        $this->singleValueRule($field, 'isnotnull');
        return $this;
    }

    public function between($field, $start_value, $end_value)
    {
        $this->where[] = ['type' => 'between', 'field' => $field, 'startValue' => $start_value, 'endValue' => $end_value];
        return $this;
    }

    public function in($field, $values)
    {
        $this->listValueRule($field, 'in', $values);
        return $this;
    }

    public function notin($field, $values)
    {
        $this->listValueRule($field, 'notin', $values);
        return $this;
    }

    public function perPage($value)
    {
        $this->perPage = $value;
        return $this;
    }

    public function currentPage($value)
    {
        $this->currentPage = $value;
        return $this;
    }

    public function sort($field, $sort_direction)
    {
        $this->sort[] = ['type' => 'field', 'field' => $field, 'direction' => $sort_direction];
        return $this;
    }

    protected function compute()
    {
        $filter = [];
        $filter['filter']   = $this->where;
        $filter['order-by'] = $this->sort;
        $filter['per_page'] = $this->perPage;
        if(!empty($this->currentPage)) {
            $filter['page'] = $this->currentPage;
        }
        return $filter;
    }

    public function toArray()
    {
        return $this->compute();
    }

    public function toUrlParams()
    {
        return http_build_query($this->compute());
    }

    public function toDebugData()
    {
        $data = [];
        foreach($this->where as $params) {
            $value = 'null';
            if(!empty($params['value'])) {
                $value = '"' . $params['value'] . '"';
            }
            if(!empty($params['values'])) {
                $value = '"' . implode(', ', $params['values']) . '"';
            }
            if(!empty($params['startValue'])) {
                $value = '"' . $params['startValue'] . ' to ' . $params['endValue'] . '"';
            }
            $data[] = "{$params['field']} {$params['type']} {$value}"; 
        }
        return implode('; ', $data);
    }
}