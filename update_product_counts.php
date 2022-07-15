<?php
require 'config.php';


$oa_query = new OAQuery($oa_base_url, $oa_user, $oa_password);
$ms_query = HttpQuery::withTokenAuth($ms_base_url, $ms_token);
$ms_query->addHeader('Accept: application/json;charset=utf-8');


$log = NotifyLog::withNameFromPath(__FILE__);
$log->send(Notify::INFO, 'Start. ' . date('Y-m-d H:i'));
$notify = new NotifyManager($log);


# Загрузка товаров из OrderAdmin 
$notify->send(Notify::INFO, 'Загрузка данных о товарах из OrderAdmin');
$oa_offer_pages = $oa_query->getAllPages('products/offer');


# Данные о кол-ве товаров на складах в FF
$oa_product_stat = [];
foreach($oa_offer_pages as $oa_offer_page) {
    if(!$oa_offer_page->isValid()) {
        $notify->send(Notify::ERROR, 'Не удалось загрузить данные о товарах из OrderAdmin. ' . $oa_offer_page->getError());
        die();
    }
    foreach($oa_offer_page->get('_embedded/product_offer') as $offer) {
        $offer_ext = new ArrayExtract($offer);
        $oa_uniq = $offer_ext->get($oa_product_uniq_field);
        if(empty($oa_uniq)) {
            continue;
        }
        $oa_product_stat[$oa_uniq] = ['name' => $offer['name'], 'price' => $offer['price'], 'wrs' => []];
        foreach($offer_ext->getIterable('items') as $offer_counts) {
            if($offer_counts['state'] == 'normal') {
                $wrs_id = (string)$offer_counts['warehouse'];
                $count = $oa_product_stat[$oa_uniq]['wrs'][$wrs_id] ?? 0;
                $count += $offer_counts['count'];
                $oa_product_stat[$oa_uniq]['wrs'][$wrs_id] = $count;
            }
        }
    }
}


# Загрузка списка товаров из MySklad
$notify->send(Notify::INFO, 'Загрузка данных о товарах из MoySklad');
$ms_products = $ms_query->execute('entity/product');
if(!$ms_products->isValid()) {
    $notify->send(Notify::ERROR, 'Не удалось загрузить данные о товарах из MoySklad. ' . $ms_products->getError());
    die();
}


# Загрузка остатков по товарам из MoySklad
$notify->send(Notify::INFO, 'Загрузка данных об остатках из MoySklad');
$ms_counts = $ms_query->execute('report/stock/bystore/current?stockType=freeStock');
if(!$ms_counts->isValid()) {
    $notify->send(Notify::ERROR, 'Не удалось загрузить остатки по товарам из MoySklad. ' . $ms_counts->getError());
    die();
}


# Данные о количестве товаров на складах в MoySklad
$ms_product_stat = [];
foreach($ms_products->get('rows') as $ms_product) {
    $ms_product_ext = new ArrayExtract($ms_product);
    if(empty($ms_product_ext->get($ms_product_uniq_field))) {
        $notify->send(Notify::WARNING, 'Нет артикула у товара ' . $ms_product['id'] . ' в MoySklad');
        continue;
    }
    $ms_product_stat[$ms_product_ext->get($ms_product_uniq_field)] = [
        'id'  => $ms_product['id'],
        'wrs' => []
    ];
    foreach($ms_counts->getAll() as $ms_count) {
        if($ms_count['assortmentId'] != $ms_product['id']) {
            continue;
        }
        $ms_product_stat[$ms_product_ext->get($ms_product_uniq_field)]['wrs'][$ms_count['storeId']] = $ms_count['freeStock'];
    }
}


# Формирование данных для создания оприходований и списаний
$ms_add_products = [];
$ms_remove_products = [];
foreach($oa_product_stat as $oa_uniq_id => $oa_product) {
    if(!array_key_exists($oa_uniq_id, $ms_product_stat)) {
        $notify->send(Notify::WARNING, 'Для товара OrderAdmin ' . $oa_product['name'] . ' (' . $oa_uniq_id . ') не найдено совпадение в MoySklad');
        continue;
    }
    foreach($oa_product['wrs'] as $oa_wrs_id => $oa_wrs_count) {
        $index = array_search($oa_wrs_id, $ms_warehouses);
        if($index === false) {
            $notify->send(Notify::WARNING, 'Для склада OrderAdmin ' . $oa_wrs_id . ' не найдено сопоставление склада MoySkald. Добавьте сопоставление в файле настроек.');
            continue;
        }

        $ms_wrs_count = $ms_product_stat[$oa_uniq_id]['wrs'][$index] ?? 0;
        if($oa_wrs_count > $ms_wrs_count) {
            $ms_add_products[$index][] = [
                'product' => $ms_product_stat[$oa_uniq_id]['id'], 
                'count'   => $oa_wrs_count - $ms_wrs_count,
                'price'   => $oa_product['price']
            ];
        }
        if($oa_wrs_count < $ms_wrs_count) {
            $ms_remove_products[$index][] = [
                'product' => $ms_product_stat[$oa_uniq_id]['id'], 
                'count'   => $ms_wrs_count - $oa_wrs_count,
            ];
        }
    }
}


# Списать все, если товар в ФФ есть, но остатков по нему нет
foreach($ms_product_stat as $ms_uniq_id => $ms_product_data) {
    if(!array_key_exists($ms_uniq_id, $oa_product_stat)) {
        continue;
    }
    foreach($ms_product_data['wrs'] as $ms_store_id => $ms_store_count) {
        if(!array_key_exists($ms_store_id, $ms_warehouses)) {
            $notify->send(Notify::WARNING, 'Для склада MoySklad ' . $ms_store_id . ' не найдено сопоставление в настройках интеграции');
            continue;
        }
        if(!array_key_exists($ms_warehouses[$ms_store_id], $oa_product_stat[$ms_uniq_id]['wrs'])) {
            $ms_remove_products[$ms_store_id][] = [
                'product' => $ms_product_data['id'],
                'count'   => $ms_store_count
            ];
        }

    }
}


# Добавить Оприходования в MoySklad
foreach($ms_add_products as $ms_store_id => $ms_product_data) {
    $add_counts_data = [
        'moment'=> date('Y-m-d H:i:s'),
        'applicable'=> true,
        'organization'=> [
            'meta'=> [
                'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/organization/' . $ms_organization_id,
                'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/organization/metadata',
                'type'=> 'organization',
                'mediaType'=> 'application/json'
            ]
        ],
        'store'=> [
            'meta'=> [
                'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/store/' . $ms_store_id,
                'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/store/metadata',
                'type'=> 'store',
                'mediaType'=> 'application/json'
            ]
        ],
        'attributes'=> []
    ];
    $products = [];
    foreach($ms_product_data as $ms_product) {
        $products[] = [
            'quantity'=> $ms_product['count'],
            'price'=> $ms_product['price'] * 100,
            'assortment'=> [
                'meta'=> [
                    'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/product/' . $ms_product['product'],
                    'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/product/metadata',
                    'type'=> 'product',
                    'mediaType'=> 'application/json'
                ]
            ],
            'overhead'=> 0
        ];
    }
    $add_counts_data['positions'] = $products;
    
    $notify->send(Notify::INFO, 'Создание оприходования в MoySklad');
    $ms_add_status = $ms_query->execute('entity/enter', [], 'POST', $add_counts_data);
    if(!$ms_add_status->isValid()) {
        $notify->send(Notify::ERROR, 'Не удалось добавить оприходование в MoySklad. ' . $ms_add_status->getError());
        die();
    }
}


# Добавить Списание в MoySklad
foreach($ms_remove_products as $ms_store_id => $ms_product_data) {
    $remove_counts_data = [
        'organization'=> [
            'meta'=> [
                'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/organization/' . $ms_organization_id,
                'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/organization/metadata',
                'type'=> 'organization',
                'mediaType'=> 'application/json'
            ]
        ],
        'store'=> [
            'meta'=> [
                'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/store/' . $ms_store_id,
                'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/store/metadata',
                'type'=> 'store',
                'mediaType'=> 'application/json'
            ]
        ]
    ];
    $products = [];
    foreach($ms_product_data as $ms_product) {
        $products[] = [
            'quantity'=> $ms_product['count'],
            'assortment'=> [
                'meta'=> [
                    'href'=> 'https://online.moysklad.ru/api/remap/1.2/entity/product/' . $ms_product['product'],
                    'metadataHref'=> 'https://online.moysklad.ru/api/remap/1.2/entity/product/metadata',
                    'type'=> 'product',
                    'mediaType'=> 'application/json'
                ]
            ]
        ];
    }
    $remove_counts_data['positions'] = $products;
    
    $notify->send(Notify::INFO, 'Создание списания в MoySklad');
    $ms_remove_status = $ms_query->execute('entity/loss', [], 'POST', $remove_counts_data);
    if(!$ms_remove_status->isValid()) {
        $notify->send(Notify::ERROR, 'Не удалось добавить списание в MoySklad. ' . $ms_remove_status->getError());
        die();
    }
}
