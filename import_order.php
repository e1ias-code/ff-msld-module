<?php
ignore_user_abort(true);
header('HTTP/1.1 200 OK');
header('Content-Length: 0');
header('Connection: close');
require 'config.php';

 
$oa_query = new OAQuery($oa_base_url, $oa_user, $oa_password);
$ms_query = HttpQuery::withTokenAuth($ms_base_url, $ms_token);
$ms_query->addHeader('Accept: application/json;charset=utf-8');


$mail_send = new NotifyMail();
$mail_send->setEmail($mail_to);
$mail_send->setTitle('Ошибка создания заказа в ЛК Фулфилмент');
$mail_send->addParagraph('Во время импорта заказа из MoySklad в ЛК Фулфилмент произошла ошибка.');
$mail_send->setLevel(Notify::ERROR);
$log = NotifyLog::withNameFromPath(__FILE__);
$log->setLevel(Notify::INFO);
$log->send(Notify::INFO, 'Start. ' . date('Y-m-d H:i'));
$notify = new NotifyManager($log, $mail_send);
$notify->send(Notify::INFO, 'Принят вебхук');


# Параметры запроса
$post_data = file_get_contents('php://input');
if(empty($post_data)) {
    $notify->send(Notify::WARNING, 'Некорректные данные получены через MoySklad вебхук.');
    die();
}
$post_data = json_decode($post_data, true);


# В MoySklad таймаут на ответ 1500mc
flush();
ob_flush();
session_write_close();


# Список заказов в вебхуке
foreach($post_data['events'] as $hook_order) {
    try {
        $oa_order_parts = new ArrayParts();
        $hook_order_ext = new ArrayExtract($hook_order);
        $hook_order_url = $hook_order_ext->get('meta/href');

        # Данные заказа MoySklad
        $notify->send(Notify::INFO, 'Загрузка данных заказа из MoySklad: ' . $hook_order_url);
        $ms_order = $ms_query->execute($hook_order_url . '?expand=positions,store,agent');
        if(!$ms_order->isValid()) {
            throw new VerifyException('Не удалось загрузить данные заказа из MoySklad. ' . $ms_order->getError());
        }


        # Базовые поля заказа OA
        $oa_order_parts->add([
            'shop'       => $oa_shop,
            'extId'      => str_replace('-', '', $ms_order->get('id')), # в OA max size - 32
            'date'       => $ms_order->get('moment'),
            'orderPrice' => $ms_order->get('sum') / 100,
            'comment'    => $ms_order->get('description'),
            'deliveryRequest'     => [
                'deliveryService' => 1,
                'sender'          => $oa_sender,
                'rate'            => $oa_rate,
            ],
            'eav'                 => [
                'delivery-services-request' => true,
            ],
        ]);


        # Данные покупателя
        $oa_order_parts->add([
            'recipientName' => $ms_order->get('agent/name'),
            'profile'       => [
                'name'      => $ms_order->get('agent/name'),
                'type'      => 'physical',
                'phone'     => $ms_order->get('agent/phone'),
                'email'     => $ms_order->get('agent/email'),
            ],
        ]);


        # Данные об оплате
        if($ms_order->get('payedSum') == 0) {
            $oa_order_parts->add([
                'paymentState' => 'not_paid',
            ]);
        } elseif($ms_order->get('payedSum') == $ms_order->get('sum')) {
            $oa_order_parts->add([
                'paymentState' => 'paid',
            ]);
        } else {
            throw new VerifyException('Заказ ' . $ms_order->get('id') . '. Не поддерживаются частично оплаченные заказы');
        }


        # Данные о складе
        $ms_warehouse_id = $ms_order->get('store/id');
        if(!array_key_exists($ms_warehouse_id, $ms_warehouses)) {
            throw new VerifyException('Не найдено сопоставление для склада MoySklad в настройках скрипта. Склад: ' . $ms_warehouse_id);
        }
        $oa_order_parts->add([
            'eav' => [
                'order-reserve-warehouse' => $ms_warehouses[$ms_warehouse_id],
            ],
        ]);


        # Поиск населенного пункта в OrderAdmin
        $search_params = [];
        $search_params['postcode'] = $ms_order->get('shipmentAddressFull/postalCode');
        $search_params['name'] = $ms_order->get('shipmentAddressFull/city');
        if(strpos($search_params['name'], 'г ') === 0) {
            $search_params['name'] = trim(substr($search_params['name'], 3));
            $search_params['type'] = OALocality::TYPE_CITY;
        }
        $oa_locality = OALocality::search($search_params, $oa_query);


        # Данные об адресе получателя
        $oa_order_parts->add([
            'address' => [
                'locality'  => $oa_locality->id,
                'postcode'  => $search_params['postcode'] ?? $oa_locality->postcode,
                'street'    => $ms_order->get('shipmentAddressFull/street'),
                'house'     => $ms_order->get('shipmentAddressFull/house'),
                'apartment' => $ms_order->get('shipmentAddressFull/apartment'),
                'notFormal' => $ms_order->get('shipmentAddress'),
            ]
        ]);


        # Загрузка данных о товарах в заказе
        $notify->send(Notify::INFO, 'Загрузка данных о товарах в заказе MoySklad');
        $ms_order_products = $ms_query->execute(
            "entity/customerorder/{$ms_order->get('id')}/positions?expand=assortment"
        );
        if(!$ms_order_products->isValid()) {
            throw new VerifyException('Не удалось получить данные товара из MoySklad. Error: ' . $ms_order_products->getError());
        }


        # Формирование списка товаров для последующей обработки
        $ms_products = [];
        foreach($ms_order_products->getIterable('rows') as $ms_order_product) {
            $ms_order_product_ext = new ArrayExtract($ms_order_product);
            $ms_products[$ms_order_product_ext->get($ms_order_product_uniq_field)] = $ms_order_product;
        }


        # Поиск товаров в OrderAdmin по атрибуту $oa_product_uniq_field
        $notify->send(Notify::INFO, 'Загрузка данных о товарах из OrderAdmin');
        $oa_products = OAProduct::getByAttributeValueList($oa_product_uniq_field, array_keys($ms_products), $oa_query);
        if(!$oa_products[0]->isValid()) {
            throw new VerifyException($oa_products[0]->getError());
        }


        # Формирование списка товаров в заказе
        foreach($oa_products as $oa_product) {
            $ms_current_item = $ms_products[$oa_product->$oa_product_uniq_field];
            $oa_order_parts->add([
                'orderProducts' => [[
                    'productOffer' => $oa_product->id,
                    'shop'         => $oa_shop,
                    'price'        => $ms_current_item['price'] / 100,
                    'count'        => $ms_current_item['quantity'],
                ]]
            ]);
        }


        # Сохранение данных в OrderAdmin
        $return_order = $oa_query->add('products/order', $oa_order_parts->getAll());
        if(!$return_order->isValid()) {
            $notify->send(Notify::ERROR, 'Не удалось сохранить данные заказа в OrderAdmin. Error: ' . $return_order->getError());
            continue;
        }
        $notify->send(Notify::INFO, 'Создан заказ в OrderAdmin: ' . $return_order->get('id'));
    } catch (VerifyException $e) {
        $notify->send(Notify::ERROR, $e->getMessage());
    }
}
