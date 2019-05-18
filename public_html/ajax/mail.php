<?php

use NikolayS93\Mailer\MailAnswer;

// @note fix path to Composer's autoloader
require __DIR__ . '/../../vendor/autoload.php';

/** @var bool  must be empty for spam filter */
$is_spam = !empty($_POST["surname"]);
if( $is_spam ) { header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403); die(); }

$mail = new MailAnswer();
$mail->fromName = 'TestMessage';

// to
$mail->addAddress('nikolays93@ya.ru');

$mail->addField( 'addit' );
$mail->setRequired('phone');

extract( $mail->getFields() );

if( $name )  $mail->Body.= "Имя отправителя: $name\r\n";
if( $email ) $mail->Body.= "Электронный адрес отправителя: $email\r\n";
if( $phone ) $mail->Body.= "Телефон отправителя: $phone\r\n";
if( $addit ) $mail->Body.= "Дополнительный текст: $addit\r\n";
if( $text )  $mail->Body.= "Текст сообщения:\r\n $text\r\n";

if( $mail->Body ) {
    $mail->Body.= "\r\n";
    $mail->Body.= "URI запроса: ". $_SERVER['REQUEST_URI'] . "\r\n";
    $mail->Body.= "URL источника запроса: ". str_replace(MailAnswer::$protocol . ':', '', $_SERVER['HTTP_REFERER']) . "\r\n";
}

$mail->sendMail();
$mail->showResult();