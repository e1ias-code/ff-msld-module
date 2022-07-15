<?php

class OATrackingNumber
{
    public $orderId;
    public $deliveryRequestId;
    public $trackingNumber;
    public $error;

    protected function __construct($source, $error = '')
    {
        $data = new ArrayExtract($source);
        $this->orderId           = $data->get('order');
        $this->deliveryRequestId = $data->get('request');
        $this->trackingNumber    = $data->get('trackingNumber');
        $this->error             = $error;
    }

    public static function getByOrderId($orderId, $oa_query)
    {
        $order = $oa_query->get('products/order/' . $orderId);
        if(!$order->isValid()) {
            return new static([], 'Не удалось получить данные заказа ' . $orderId . '. Ошибка: ' . $order->getError());
        }
        $item = [
            'order'          => $order->get('id'),
            'request'        => $order->get('_embedded/deliveryRequest/id'),
            'trackingNumber' => $order->get('_embedded/deliveryRequest/trackingNumber'),
        ];
        return new static($item);
    }

    public static function getByOrderExtId($orderExtId, $oa_query)
    {
        $filter = (new OASearchFilter())->eq('extId', $orderExtId);
        $order = $oa_query->get('products/order', $filter->toArray());
        if(!$order->isValid()) {
            return new static([], 'Не удалось получить данные заказа. Поиск по extId: ' . $orderExtId .  '. Ошибка: ' . $order->getError());
        }
        if(!$order->existSubitems('_embedded/*', 1, 1)) {
            return new static([], 'Не удалось получить данные заказа. Поиск по extId: ' . $orderExtId .  '. Количество результатов не соответствует ожидаемому. Требуется - 1, вернулось - ' . $order->get('total_items'));
        }
        $order_obj = new ArrayExtract($order->get('_embedded/*/*'));
        $item = [
            'order'          => $order_obj->get('id'),
            'request'        => $order_obj->get('_embedded/deliveryRequest/id'),
            'trackingNumber' => $order_obj->get('_embedded/deliveryRequest/trackingNumber'),
        ];
        return new static($item);
    }

    public static function getByRequestId($requestId, $oa_query)
    {
        $request = $oa_query->get('delivery-services/requests/' . $requestId);
        if(!$request->isValid()) {
            return new static([], 'Не удалось получить данные заявки на доставку ' . $requestId . '. Ошибка: ' . $request->getError());
        }
        $item = [
            'order'          => $request->get('eav/delivery-request-products-order'),
            'request'        => $request->get('id'),
            'trackingNumber' => $request->get('trackingNumber'),
        ];
        return new static($item);
    }

    public static function getByRequestExtId($requestExtId, $oa_query)
    {
        $filter = (new OASearchFilter())->eq('extId', $requestExtId);
        $request = $oa_query->get('delivery-services/requests', $filter->toArray());
        if(!$request->isValid()) {
            return new static([], 'Не удалось получить данные заявки на доставку. Поиск по extId: ' . $requestExtId .  '. Ошибка: ' . $request->getError());
        }
        if(!$request->existSubitems('_embedded/*', 1, 1)) {
            return new static([], 'Не удалось получить данные заявки на доставку. Поиск по extId: ' . $requestExtId .  '. Количество результатов не соответствует ожидаемому. Требуется - 1, вернулось - ' . $request->get('total_items'));
        }
        $request_obj = new ArrayExtract($request->get('_embedded/*/*'));
        $item = [
            'order'          => $request_obj->get('eav/delivery-request-products-order'),
            'request'        => $request_obj->get('id'),
            'trackingNumber' => $request_obj->get('trackingNumber'),
        ];
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