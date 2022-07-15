<?php
#######################   Настройки   #############################

/* Настройки OrderAdmin */
$oa_base_url = 'https://cdek.orderadmin.ru/api/';

# ID магазина (ЛК > Настройки > Магазины)
$oa_shop ='';
# ID отправителя (ЛК > Настройки >Отправители)
$oa_sender = '';
# Логин и пароль для доступа к REST API
$oa_user = '';
$oa_password = '';
# Тариф на доставку
# https://cdekff.freshdesk.com/support/solutions/articles/69000368957-Сопоставление-кодов-тарифов-СДЭК-доставка-и-СДЭК-Фулфилмент-
$oa_rate = 49;
# Сопоставление товаров в обоих системах
# Товар_Фулфилмент.{$oa_product_uniq_field} == Товар_MoySklad.{$ms_product_uniq_field}
# При изменении - убедитель что в OAProduct присутствует поле с таким же названием
$oa_product_uniq_field = 'article';


/* Настройки MoySkald */
$ms_base_url = 'https://online.moysklad.ru/api/remap/1.2/';
# Токен для доступа к данным МойСклад
# Настройки > Обмен данными > "+ Токен"
$ms_token = '';
# Идентификатор Юр. лица в МойСклад
# Настройки > Справочники > Юр. лица > Свойства > Свойства Юр. лица
# Содержится в адресе страницы. Имеет вид: 087a3ce4-c464-11ec-0a80-0d1d00253ab9
$ms_organization_id = '';
# Соответствие кодов складов в ПО МойСклад и складов в ЛК Фулфилмент
# МойСклад: Настройки > Справочники > Склады > Форма редактирования склада
#   id находится в url-адресе
#   Пример: app/#warehouse/edit?id=087b26f0-c464-11ec-0a80-0d1d00253abb
#   id - 087b26f0-c464-11ec-0a80-0d1d00253abb
# OrderAdmin: ЛК > Настройки > Склады > Столбец ID
$ms_warehouses = [
#   'id склада МойСклад' => 'id склада OrderAdmin',
#    Пример:
#   '087b26f0-c464-11ec-0a80-0d1d00253abb' => '1234',

];
# Сопоставление товаров в обоих системах
# Товар_Фулфилмент.{$oa_product_uniq_field} == Товар_MoySklad.{$ms_product_uniq_field}
$ms_product_uniq_field = 'article';
# В товарах заказа название параметра может отличаться
# Например параметр товара article в товарах заказа называется assortment/article
$ms_order_product_uniq_field = 'assortment/article';
# URL-адрес скрипта import_order.php на вашем хостинге
# Пример: $webhook_address = 'https://maydomain.com/import_order.php'; 
$webhook_address = '';


/* Отправить письмо если во время создания заказа произошла ошибка */
# e-mail адрес. Пример: your-mail@mail.ru
$mail_to = '';

###################################################################


$DEBUG_MODE = false;

if($DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

spl_autoload_register(function ($class) {
    require 'classes/' . $class . '.php';
});
