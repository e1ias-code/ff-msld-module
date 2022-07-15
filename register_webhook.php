<?php
require 'config.php';


$ms_query = HttpQuery::withTokenAuth($ms_base_url, $ms_token);
$ms_query->addHeader('Accept: application/json;charset=utf-8');
$ms_query->addHeader('Content-Type: application/json');


$log = NotifyLog::withNameFromPath(__FILE__);
$log->send(Notify::INFO, 'Start. ' . date('Y-m-d H:i'));
$notify = new NotifyManager($log);


if(empty($webhook_address)) {
    $notify->send(Notify::ERROR, 'В настройках не указан адрес вебхука');
    die();
}


# Получение списка вебхуков
$exist_webhook = false;
$res = $ms_query->execute('entity/webhook');
foreach($res->get('rows') as $hook) {
    if($hook['url'] == $webhook_address) {
        $exist_webhook = true;
    }
}

# Регистрация вебхука
if(!$exist_webhook) {
    $notify->send(Notify::INFO, 'Регистрация вебхука');
    $res = $ms_query->execute(
        'entity/webhook', [], 'POST',
        [
            'url'        => $webhook_address,
            'action'     => 'CREATE',
            'entityType' => 'customerorder'
        ]
    );
    if(!$res->isValid()) {
        $notify->send(Notify::ERROR, 'Не удалось зарегистрировать вебхук ' . $res->getError());
        die();
    }

} else {
    $notify->send(Notify::INFO, 'Вебхук уже зарегистрирован');
}



# Удалить по ID
// $hook_id = 'b932776d-cc4e-11ec-0a80-05590083bbc0';
// $res = $ms_query->execute('entity/webhook/' . $hook_id, [], 'DELETE', ['enabled' => false]);
// echo '<pre>'; var_dump($res); echo '</pre>';
