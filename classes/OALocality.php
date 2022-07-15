<?php

class OALocality
{
    public $id;
    public $name;
    public $type;
    public $postcode;
    public $error;

    public const TYPE_CITY = 1;

    protected function __construct($source, $error = '')
    {
        $this->id        = $source['id']   ?? 0;
        $this->name      = $source['name'] ?? '';
        $this->type      = $source['type'] ?? '';
        $this->postcode  = $source['postcode'] ?? '';
        $this->error     = $error;
    }

    public static function getByPostalCode($code, $oa_query)
    {
        $filter = new OASearchFilter();
        $filter->eq('extId', $code);
        $oa_postcodes = $oa_query->get('delivery-services/postcodes', $filter->toArray());
        if(!$oa_postcodes->isValid()) {
            return new static([], 'Поиск населенного пункта. Не удалось получить список почтовых индексов из OrderAdmin. Ошибка: ' . $oa_postcodes->getError());
        }
        if(!$oa_postcodes->existSubitems('_embedded/*', 1)) {
            return new static([], 'Поиск населенного пункта. Не удалось найти почтовый индекс в OrderAdmin. Передан: ' . $code);
        }
        $item = $oa_postcodes->get('_embedded/*/*/_embedded/locality');
        return new static($item);
    }

    public static function getByName($name, $oa_query, $country = null, $type = null)
    {
        $filter = new OASearchFilter();
        $filter->eq('name', $name);
        if(!is_null($country)) {
            $filter->eq('country', $country);
        }
        if(!is_null($type)) {
            if(is_array($type)) {
                $filter->in('type', $type);
            } else {
                $filter->eq('type', $type);
            }
        }
        $oa_locality = $oa_query->get('locations/localities', $filter->toArray());
        if(!$oa_locality->isValid()) {
            return new static([], 'Поиск населенного пункта. Не удалось получить информацию о Locality из OrderAdmin. Ошибка: ' . $oa_locality->getError());
        }
        if(!$oa_locality->existSubitems('_embedded/*', 1, 1)) {
            return new static([], 'Поиск населенного пункта. Вернулось некорректное количество результатов. Требуется - 1, вернулось - ' . $oa_locality->get('total_items') . '. Название: ' . $name);
        }
        $item = $oa_locality->get('_embedded/*/*');
        return new static($item);
    }

    /**
     *  Поиск locality в зависимости от переданных значений
     */
    public static function search($values, $oa_query)
    {
        # $values['postcode']
        # $values['name']
        # $values['type']

        if(!empty($values['postcode'])) {
            $oa_locality = self::getByPostalCode($values['postcode'], $oa_query);
            if(!$oa_locality->isValid()) {
                throw new VerifyException($oa_locality->getError());
            }
            return $oa_locality;
        }

        $l_type = null;
        if(!empty($values['type'])) {
            if($values['type'] == self::TYPE_CITY) {
                $l_type = ['город', 'Город', 'city'];
            }
        }

        if(!empty($values['name'])) {
            $oa_locality = self::getByName($values['name'], $oa_query, null, $l_type);
            if(!$oa_locality->isValid()) {
                throw new VerifyException($oa_locality->getError());
            }
            return $oa_locality;
        }

        throw new VerifyException('Не указаны параметры для поиска locality: название города или почтовый индекс');
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