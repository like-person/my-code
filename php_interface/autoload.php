<?php

use Bitrix\Main\Loader;

//Автозагрузка наших классов
Loader::registerAutoLoadClasses(null, [
    'lib\usertype\CUserTypeStrFileHtml' => APP_CLASS_FOLDER . 'usertype/CUserTypeStrFileHtml.php',
]);