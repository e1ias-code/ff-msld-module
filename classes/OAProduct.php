<?php

class OAProduct
{
    public $id;
    public $name;
    public $article;
    public $state;
    public $extId;
    public $sku;
    public $price;
    public $error;

    protected function __construct($source, $error = '')
    {
        $this->id      = $source['id']      ?? 0;
        $this->name    = $source['name']    ?? '';
        $this->article = $source['article'] ?? '';
        $this->state   = $source['state']   ?? '';
        $this->extId   = $source['extId']   ?? '';
        $this->sku     = $source['sku']     ?? '';
        $this->price   = $source['price']   ?? '';
        $this->error   = $error;
    }

    public static function getByFilter($oa_query, $oa_filter, $min_count = null, $max_count = null, $single_result = true)
    {
        $oa_products = $oa_query->get('products/offer', $oa_filter->toArray());
        if(!$oa_products->isValid()) {
            return new static([], 'Поиск товаров. Не удалось получить информацию о товаре из OrderAdmin. Фильтр: ' . $oa_filter->toDebugData()  . '. Ошибка: ' . $oa_products->getError());
        }
        if(!is_null($min_count) || !is_null($max_count)) {
            if(!$oa_products->existSubitems('_embedded/*', $min_count, $max_count)) {
                return new static([], 'Поиск товаров. Вернулось некорректное количество результатов. Требуется от ' . $min_count . ' до ' . $max_count . '. Вернулось: ' . $oa_products->get('total_items') . ' Фильтр: ' . $oa_filter->toDebugData());
            }
        }
        if($single_result ){
            return new static($oa_products->get('_embedded/*/*'));
        } else {
            $res_arr = [];
            foreach($oa_products->get('_embedded/*') as $product) {
                $res_arr[] = new static($product);
            }
            return $res_arr;
        }
    }

    public static function getByAttributeValue($attr_name, $value, $oa_query)
    {
        $filter = (new OASearchFilter())->eq($attr_name, $value);
        return static::getByFilter($oa_query, $filter, 1, 1);
    }

    public static function getByAttributeValueList($attr_name, $value_list, $oa_query)
    {
        $filter = (new OASearchFilter())->in($attr_name, $value_list)->perPage(200);
        $res = static::getByFilter($oa_query, $filter, sizeof($value_list), sizeof($value_list), false);
        return (is_array($res)) ? $res : [$res];
    }

    public function isValid()
    {
        if (empty($this->error)) {
            return true;
        } else {
            return false;
        }
    }

    public function getError()
    {
        return $this->error;
    }

}