<?php

require_once __DIR__ . '/vendor/autoload.php';

use controllers\ImsiController;

//error_reporting(0);
//register_shutdown_function('catchFatalErrors');

$receive = new ImsiController();
$receive->listenFromQueu();

function catchFatalErrors()
{
    $error = error_get_last();

    if ( ! empty($error['type'])
         && $error['type'] == E_ERROR
    ) // Проверяем на наличие ошибок в коде
    {
        // Отправляем ползователю сообщение об ошибке
        $response = [
            'status'  => 'error',
            'message' => 'Произошла ошибка, обратитесь позднее',
        ];

        echo json_encode($response);
        // Посылаем сигнал, что есть ошибка (передаем массив с ошибками $error)

    }
}



