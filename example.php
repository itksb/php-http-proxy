<?php

require __DIR__ . '/src/ServiceProxy.php';

use itksb\ato\proxy\taxi\CheckTaxi;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'on');
ini_set("default_socket_timeout", 5); // in seconds

$checker = new CheckTaxi();
$result = $checker->checkGosNumber('К090XY70');

if ($result->isConnError()){
    // Ошибка при работе с удаленным сервисом
    print($result->proxyError);
}

if ($result->isFound()){
    // такси зарегистрирован
    print($result->message);
} else  {
    // такси не зарегистрирован
    print($result->message);
}


