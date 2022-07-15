<?php
require 'config.php';


$oa_query = new OAQuery($oa_base_url, $oa_user, $oa_password);
$ms_query = HttpQuery::withTokenAuth($ms_base_url, $ms_token);
$ms_query->addHeader('Accept: application/json;charset=utf-8');


$log = NotifyLog::withNameFromPath(__FILE__);
$log->send(Notify::INFO, 'Start. ' . date('Y-m-d H:i'));
$notify = new NotifyManager($log);


# Загрузка идентификаторов существующих товаров из OrderAdmin
$notify->send(Notify::INFO, 'Загрузка товаров из OrderAdmin');
$oa_uniq_ids = [];
$product_pages = $oa_query->getAllPages('products/offer');
foreach($product_pages as $product_page) {
    if(!$product_page->isValid()) {
        $notify->send(Notify::ERROR, 'Не удалось загрузить данные о товарах из OrderAdmin. ' . $product_page->getError());
        die();
    }
    foreach($product_page->get('_embedded/product_offer') as $offer) {
        $offer_ext = new ArrayExtract($offer);
        $oa_uniq = $offer_ext->get($oa_product_uniq_field);
        if(!empty($oa_uniq)) {
            $oa_uniq_ids[$oa_uniq] = 1;
        }
    }
}


# Загрузка списка товаров из MySklad (limit 1000)
$notify->send(Notify::INFO, 'Загрузка товаров из MoySklad');
$ms_products = $ms_query->execute('entity/product');
if(!$ms_products->isValid()) {
    $notify->send(Notify::ERROR, 'Не удалось загрузить данные о товарах из MoySklad. ' . $ms_products->getError());
    die();
}


# Добавление товаров OrderAdmin
$notify->send(Notify::INFO, 'Создание товаров в OrderAdmin');
$new_product_count = 0;
foreach($ms_products->get('rows') as $ms_product) {
    $ms_product_ext = new ArrayExtract($ms_product);
    $ms_uniq = $ms_product_ext->get($ms_product_uniq_field);
    if((!empty($ms_uniq)) && (!isset($oa_uniq_ids[$ms_uniq]))) {
        $new_product = [
            'shop'    => $oa_shop,
            'type'    => 'simple',
            'name'    => $ms_product_ext->get('name'),
            'article' => $ms_product_ext->get('article'),
            'extId'   => $ms_product_ext->get('id'),
            'sku'     => $ms_product_ext->get('barcodes/*/ean13'),
            'price'   => $ms_product_ext->get('salePrices/*/value') / 100,
            'weight'  => $ms_product_ext->get('weight'),
        ];
        $add_result = $oa_query->add('products/offer', $new_product);
        if(!$add_result->isValid()) {
            $notify->send(Notify::ERROR, 'Не удалось создать товар в OrderAdmin. ' . $add_result->getError());
            die();
        }
        $new_product_count++;
    }
}
$notify->send(Notify::INFO, 'Создано товаров: ' . $new_product_count);
