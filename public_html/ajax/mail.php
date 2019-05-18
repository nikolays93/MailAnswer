<?php

use NikolayS93\Mailer\MailAnswer;

// @note fix path to Composer's autoloader
require __DIR__ . '/../../vendor/autoload.php';

/** @var bool  must be empty for spam filter */
$is_spam = !empty($_POST["surname"]);
if( $is_spam ) { header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403); die(); }

$mail = new MailAnswer();
$mail->fromName = 'TestName';

// to
$mail->addAddress('nikolays93@ya.ru');

$mail->addField( 'test-name', 'Тестовое имя' );
$mail->setRequired('phone');

$fields = $mail->getFields();
$fieldNames = $mail->getFieldNames();

foreach ($fields as $key => $value)
{
    if( $value ) $mail->Body.= $fieldNames[$key] . ": $value\r\n";
}

if( $mail->Body ) {
    $mail->Body.= "\r\n";
    $mail->Body.= "URI запроса: ". $_SERVER['REQUEST_URI'] . "\r\n";
    $mail->Body.= "URL источника запроса: ". str_replace(MailAnswer::$protocol . ':', '', $_SERVER['HTTP_REFERER']) . "\r\n";
}

$mail->sendMail();
$mail->showResult();