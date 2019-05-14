<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

if( !function_exists('sanitize_post_data') ) {
    /**
     * @param  string  $key        key in global post array
     * @param  boolean $strip_tags do you want clear tags?
     * @return string  $result     sanitized value || ''
     */
    function sanitize_post_data( $key, $strip_tags = true ) {
        if( $result = !empty($_POST[ $key ]) ? (string) $_POST[ $key ] : '' ) {
            if( $strip_tags ) {
                $result = strip_tags( $result );
            }

            $result = htmlspecialchars( $result );
            $result = mysql_escape_string( $result );
        }

        return $result;
    }
}

if( !function_exists('sanitize_email') ) {
    /**
     * @param  string &$email    Saitize field
     * @param  string $fieldname
     * @return string $error     Error message if exists
     */
    function sanitize_email( &$email, $fieldname = 'e-mail' ) {
        // Test for the minimum length the email can be
        if ( strlen( $email ) < 6 ) {
            return "Короткий $fieldname, он должен содержать не менее 6 символов.";
        }

        // Test for an @ character after the first position
        if ( strpos( $email, '@', 1 ) === false ) {
            return "Не верно указан $fieldname";
        }

        // Split out the local and domain parts
        list( $local, $domain ) = explode( '@', $email, 2 );

        // LOCAL PART
        // Test for invalid characters
        $local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
        if ( '' === $local ) {
            return "Не верное имя поля $fieldname";
        }

        // DOMAIN PART
        // Test for sequences of periods
        $domain = preg_replace( '/\.{2,}/', '', $domain );
        if ( '' === $domain ) {
            return "Не верно введен домен поля $fieldname";
        }

        // Test for leading and trailing periods and whitespace
        $domain = trim( $domain, " \t\n\r\0\x0B." );
        if ( '' === $domain ) {
            return "Не верно введен домен поля $fieldname";
        }

        // Split the domain into subs
        $subs = explode( '.', $domain );

        // Assume the domain will have at least two subs
        if ( 2 > count( $subs ) ) {
            return "Не верно указана зона домена поля $fieldname";
        }

        // Create an array that will contain valid subs
        $new_subs = array();

        // Loop through each sub
        foreach ( $subs as $sub ) {
            // Test for leading and trailing hyphens
            $sub = trim( $sub, " \t\n\r\0\x0B-" );

            // Test for invalid characters
            $sub = preg_replace( '/[^a-z0-9-]+/i', '', $sub );

            // If there's anything left, add it to the valid subs
            if ( '' !== $sub ) {
                $new_subs[] = $sub;
            }
        }

        // If there aren't 2 or more valid subs
        if ( 2 > count( $new_subs ) ) {
            return "Не верно указан домен поля $fieldname";
        }

        // Join valid subs into the new domain
        $domain = join( '.', $new_subs );

        // Put the email back together
        $email = $local . '@' . $domain;

        // Congratulations your email made it!
        return '';
    }
}

if( !function_exists('sanitize_phone') ) {
    function sanitize_phone( &$phone, $fieldname = 'номер телефона' ) {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if( strlen( $phone ) < 6 ) {
            return "Короткий $fieldname, он должен содержать не менее 6 символов.";
        }

        if( strlen( $phone ) > 12 ) {
            return "Длинный $fieldname, он должен содержать не более 12 символов.";
        }

        if( 0 === strpos($phone, '7') ) {
            $phone = '+' . $phone;
        }

        return '';
    }
}

if( !class_exists('MailAnswer') ) {
    class MailAnswer
    {
        /** @var boolean */
        static $is_ajax;

        /** @var string */
        static $protocol;

        static $isHttpStatusExists = false;

        /** @var array */
        public $errors = array();

        public $status = 'success';
        public $message = 'Заявка успешно отправлена.';

        function __construct()
        {
            static::$is_ajax = isset($_REQUEST["is_ajax"]) ? 'false' !== $_REQUEST["is_ajax"] : false;
            static::$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        }

        public function addError( $msg, $serverError = false )
        {
            if( !static::$isHttpStatusExists ) {
                if( !$serverError ) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
                }
                else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                }

                static::$isHttpStatusExists = true;
            }

            $this->errors[] = sprintf("<p>%s</p>\r\n", $msg);
            $this->status = 'failure';
            $this->message = 'Ошибка, заявка не отправлена!';
        }

        public function show()
        {
            if( static::$is_ajax ) {
                echo json_encode( get_object_vars($this) );
                die();
            }

            else {
                $msg = '<p>' . $this->message . '</p>';

                echo ( !sizeof( $this->errors ) ) ? $msg
                    : $msg . '<p>' . implode('<br>', $this->errors) . '</p>';
            }
        }
    }
}

$MailAnswer = new MailAnswer();

/** @var bool  must be empty for spam filter */
$is_spam = !empty($_POST["surname"]);
if( $is_spam ) { header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403); die(); }

/**
 * Get fields
 */
$name  = sanitize_post_data( 'name' );
$email = sanitize_post_data( 'email' );
$phone = sanitize_post_data( 'phone' );
$text  = sanitize_post_data( 'text' );

/**
 * Sanitize fields
 */
if( $phone && $error = sanitize_phone( $phone ) ) {
    $MailAnswer->addError( $error );
}

if( $email && $error = sanitize_email( $email ) ) {
    $MailAnswer->addError( $error );
}

/********************************** Edit this *********************************/

$from = array('TestMessage', 'no-reply@' . str_replace('www.', '', $_SERVER['SERVER_NAME']));
$to = 'test@domain.ltd';

// Instantiation and passing `true` enables exceptions
$mail = new PHPMailer(true);
$mail->CharSet = 'utf-8';
// uncomment this for debug:
// $mail->addCC('trashmailsizh@ya.ru');

// $mail->isHTML(true);
$mail->Subject = "Сообщение с сайта";

if( $name )  $mail->Body.= "Имя отправителя: $name\r\n";
if( $email ) $mail->Body.= "Электронный адрес отправителя: $email\r\n";
if( $phone ) $mail->Body.= "Телефон отправителя: $phone\r\n";
if( $text )  $mail->Body.= "Текст сообщения:\r\n $text\r\n";

if( $mail->Body ) {
    $mail->Body.= "\r\n";
    $mail->Body.= "URI запроса: ". $_SERVER['REQUEST_URI'] . "\r\n";
    $mail->Body.= "URL источника запроса: ". str_replace(MailAnswer::$protocol . ':', '', $_SERVER['HTTP_REFERER']) . "\r\n";
}

/********************************* Do not edit ********************************/

try {
    // Recipients
    if( !empty($from[1]) ) {
        if( $from[0] ) $mail->setFrom($from[1], $from[0]);
        else           $mail->setFrom($from[1]);
    }
    else {
        $mail->setFrom( (string) $from );
    }

    if( !is_array($to) ) $to = array( $to );
    foreach ($to as $tomail) $mail->addAddress( $tomail );

    $mail->send();
}
catch (Exception $e) {
    $MailAnswer->addError( "Mailer Error: {$mail->ErrorInfo}", true );
}

$MailAnswer->show();
