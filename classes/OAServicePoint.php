<?php

class OAServicePoint
{
    public $id;
    public $name;
    public $extId;
    public $type;
    public $rawAddress;
    public $state;
    public $countryName;
    public $localityId;
    public $localityName;
    public $postCode;
    public $error;

    protected function __construct($source, $error = '')
    {
        $data = new ArrayExtract($source);
        $this->id           = $data->get('id', 0);
        $this->name         = $data->get('name', '');
        $this->extId        = $data->get('extId', '');
        $this->type         = $data->get('type', '');
        $this->rawAddress   = $data->get('rawAddress', '');
        $this->state        = $data->get('state', '');
        $this->countryName  = $data->get('_embedded/country/name', '');
        $this->localityId   = $data->get('_embedded/locality/id', '');
        $this->localityName = $data->get('_embedded/locality/name', '');
        $this->postCode     = $data->get('raw/address/index', '');
        $this->error        = $error;
    }

    public static function getByExtId($extId, $oa_query)
    {
        $filter = new OASearchFilter();
        $filter->eq('extId', $extId);
        $oa_service_points = $oa_query->get('delivery-services/service-points', $filter->toArray());
        if(!$oa_service_points->isValid()) {
            return new static([], 'Поиск пункта выдачи. Не удалось получить данные о ПВЗ из OrderAdmin. Ошибка: ' . $oa_service_points->getError());
        }
        if(!$oa_service_points->existSubitems('_embedded/*', 1, 1)) {
            return new static([], 'Поиск населенного пункта. Количество результатов не соответствует ожидаемому. Требуется - 1, вернулось - ' . $oa_service_points->get('total_items') . '. ExtID: ' . $extId);
        }
        $item = $oa_service_points->get('_embedded/*/*');
        return new static($item);
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