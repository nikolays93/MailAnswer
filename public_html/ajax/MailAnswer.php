<?php

namespace NikolayS93\Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if( !class_exists('MailAnswer') ) {
class MailAnswer extends PHPMailer
{
    public $CharSet = 'utf-8';
    public $Subject = 'Сообщение с сайта';

    public $fromName = 'TestMessage';
    public $fromLocal = 'no-reply';

    /** @var boolean */
    static $is_json;

    /** @var string */
    static $protocol;

    /** @var array */
    public $errors = array();

    public $status = 'success';
    public $message = 'Заявка успешно отправлена.';

    private $fields = array(
        'name'  => '',
        'email' => array(__CLASS__, 'sanitize_email'),
        'phone' => array(__CLASS__, 'sanitize_phone'),
        'text'  => '',
    );

    function __construct()
    {
        static::$is_json = isset($_REQUEST["is_ajax"]) ? 'false' !== $_REQUEST["is_ajax"] : false;
        static::$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

        parent::__construct( $exceptions = true );
        // $this->addCC('trashmailsizh@ya.ru');
        // $this->isHTML(true);
    }

    public function addField( $field, $cb = '' )
    {
        if( $cb && !is_callable($cb) ) {
            $this->addError( 'Объявлен не существующий метод проверки полей' );
            $cb = '';
        }

        $this->fields[ $field ] = $cb;
    }

    public function setFields( $fields )
    {
        foreach ( (array) $fields as $field => $cb)
        {
            $this->addField( $field, $cb );
        }
    }

    public function getFields()
    {
        foreach ($this->fields as $field => $sanitizeCallback)
        {
            if( $sanitizeCallback && isset($_POST[ $field ]) ) {
                $this->fields[ $field ] = $_POST[ $field ];

                if( $error = $sanitizeCallback( $this->fields[ $field ] ) ) {
                    $this->addError( $error );
                }
            }
        }

        return $this->fields;
    }

    /**
     * getter
     */
    public function getErrors( $html = false )
    {
        if( !sizeof( $this->errors ) ) return false;

        if( $html ) {
            return array_map(array(__CLASS__, 'autop'), $this->errors);
        }

        return $this->errors;
    }

    /**
     * Inserter?
     */
    public function addError( $msg )
    {
        $this->errors[] = $msg;
        $this->status = 'failure';
        $this->message = 'Ошибка, заявка не отправлена!';
    }

    public function sendMail()
    {
        try {
            if( !$this->getErrors() ) {
                $this->setFrom( $this->fromName, $this->fromLocal . str_replace('www.', '', $_SERVER['SERVER_NAME']) );
                $this->send();
            }
        }
        catch (Exception $e) {
            $this->addError( "Mailer Error: {$mail->ErrorInfo}", true );
        }
    }

    public function showResult()
    {
        $result = static::autop( $this->message );

        if( $this->getErrors() ) {
            $result .= '<br>' . array_map(array(__CLASS__, 'autop'), $this->errors);
        }

        $result = static::autop( $result, $this->status );

        if( static::$is_json ) {
            echo json_encode(
                array(
                    /**
                     * @todo error fields data
                     * 'errors' => array(),
                     */
                    'status' => $this->status,
                    'message' => $result,
                )
            );

            die();
        }
        else {
            echo $result;
        }
    }

    static function autop( $str, $class = '' )
    {
        if( $class ) {
            $attributes = ' class="'. $class .'"';
        }

        return "<p$attributes>$str</p>";
    }

    /**
     * @param  string  $key        key in global post array
     * @param  boolean $strip_tags do you want clear tags?
     * @return string  $result     sanitized value || ''
     */
    static function sanitize_post_data( $key, $strip_tags = true )
    {
        if( $result = !empty($_POST[ $key ]) ? (string) $_POST[ $key ] : '' ) {
            if( $strip_tags ) {
                $result = strip_tags( $result );
            }

            $result = htmlspecialchars( $result );
            $result = mysql_escape_string( $result );
        }

        return $result;
    }

    /**
     * @param  string &$email    Saitize field
     * @param  string $fieldname
     * @return string $error     Error message if exists
     */
    static function sanitize_email( &$email, $fieldname = 'e-mail' )
    {
        $email = static::sanitize_post_data( $email );

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

    static function sanitize_phone( &$phone, $fieldname = 'номер телефона' )
    {
        $phone = static::sanitize_post_data( $phone );
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
}
