<?php

/**
 *
 * @param type текст сообщения
 * @param type массив ссылок картинок-вложений
 * такого вида:http://ebash.org/logo.png
 */
function vkrepost($message, $images = null){
    $public = new Vkontakte('59117844', '3913461', 'Pgf3Oo0N9FYKrSEMZD0l', 'nacsymka@mail.ru', '#0991111unu#');
    
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
