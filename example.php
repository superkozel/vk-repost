<?php

/**
 *
 * @param type текст сообщения
 * @param type массив ссылок картинок-вложений
 * такого вида:http://ebash.org/logo.png
 */
function vkrepost($message, $images = null){
    $public = new Vkontakte('ID ГРУППЫ', 'ID ПРИЛОЖЕНИЯ ВКОНТАКТЕ', 'СЕКРЕТНЫЙ КЛЮЧ ПРИЛОЖЕНИЯ', 'avraamlinkoln@ya.ru - ВАША ЛОГИН VK ИЛИ ПОЧТА', '1234 - ВАШ ПАРОЛЬ');
    
    $public->login();
    $callback = 'http://api.vk.com/blank.html';
    $code = $public->auth($callback);
    $secret = $public->getSecret($callback, $code);
    $public->setAccessData($secret['access_token'], $secret['secret']);

    if (!empty($images)){
        $uploads = [];
        foreach ($images as $image){
            $uploads[] = $public->createPhotoAttachment($image);
        }
        $chunks = array_chunk($uploads, 10);//разбиваем картинки по 10 штук - ограничение VK
        foreach ($chunks as $chunk){
            $attachString = join(',', $chunk);
            $public->wallPostAttachment($attachString, $message);
        }
    }
    else{
        $public->wallPostMsg($message);
    }
}