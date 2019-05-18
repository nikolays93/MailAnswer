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

    private $fields = array();

    private $fieldNames = array();

    private $requiredFields = array();

    function __construct( $ExcludeDefaultFields = false )
    {
        static::$is_json = isset($_REQUEST["is_ajax"]) ? 'false' !== $_REQUEST["is_ajax"] : false;
        static::$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

        if( !$ExcludeDefaultFields ) {
            $this->addField('name', 'Имя');
            $this->addField('email', 'Электронный адрес', array($this, 'sanitize_email'));
            $this->addField('phone', 'Номер телефона', array($this, 'sanitize_phone'));
            $this->addField('text', 'Текст');
        }

        parent::__construct( $exceptions = true );
        // $this->addCC('trashmailsizh@ya.ru');
        // $this->isHTML(true);
    }

    public function addField( $field, $fieldname = '', $cb = '' )
    {
        if( $cb && !is_callable($cb) ) {
            $this->addError( 'Объявлен не существующий метод проверки полей. Обратитесь к администратору сайта.' );
            $cb = '';
        }

        $this->fields[ $field ] = $cb;
        $this->fieldNames[ $field ] = $fieldname;
    }

    public function setRequired( $required )
    {
        if( is_string($required) ) $required = array($required);

        $this->requiredFields = (array) $required;
    }

    public function getFields()
    {
        $values = array();

        foreach ($this->fields as $field => $sanitizeCallback)
        {
            $value = '';
            if( !empty($_POST[ $field ]) ) {
                if( $sanitizeCallback ) {
                    $value = call_user_func_array($sanitizeCallback, array($field, $this->fieldNames[ $field ]));
                }
                else {
                    $value = static::sanitize_post_data($field);
                }
            }

            $values[ $field ] = $value;
        }

        foreach ($this->requiredFields as $field)
        {
            if( empty($this->fields[ $field ]) ) $this->addError( 'Не верно заданы обязательные поля. Обратитесь к администратору сайта.' );
            if( empty($_POST[ $field ]) ) $this->addError( 'Поле ' . $this->fieldNames[ $field ] . ' обязательно к заполнению.' );
        }

        return $values;
    }

    public function getFieldNames()
    {
        return $this->fieldNames;
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
        if( !$this->getErrors() && empty($this->Body) ) {
            $this->addError( 'Попытка отправки пустого сообщения, обратитесь к администратору.' );
        }

        try {
            if( !$this->getErrors() ) {
                $this->setFrom( $this->fromLocal . '@' . str_replace('www.', '', $_SERVER['SERVER_NAME']), $this->fromName );
                $this->send();
            }
        }
        catch (Exception $e) {
            $this->addError( "Ошибка сервера: {$this->ErrorInfo}, обратитесь к администратору.", true );
        }
    }

    public function showResult()
    {
        $result = '<div class="summary">' . static::autop( $this->message ) . '</div>';

        if( $this->getErrors() ) {
            $result .= '<div class="messages">' . implode('', array_map(array(__CLASS__, 'autop'), $this->errors)) . '</div>';
        }

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
        $attributes = '';
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
            $result = trim($result);

            if( $strip_tags ) {
                $result = \strip_tags( $result );
            }

            $result = \htmlspecialchars( $result );
            /**
             * @todo think about it
             */
            // $result = \mysql_escape_string( $result );
        }

        return $result;
    }

    /**
     * @param  string &$email    Saitize field
     * @param  string $fieldname
     * @return string $error     Error message if exists
     */
    function sanitize_email( $email, $fieldname = 'e-mail' )
    {
        $email = static::sanitize_post_data( $email );

        // Test for the minimum length the email can be
        if ( strlen( $email ) < 6 ) {
            $this->addError( "Короткий $fieldname, он должен содержать не менее 6 символов." );
        }

        // Test for an @ character after the first position
        if ( strpos( $email, '@', 1 ) === false ) {
            $this->addError( "Не верно указан $fieldname" );
        }

        // Split out the local and domain parts
        list( $local, $domain ) = explode( '@', $email, 2 );

        // LOCAL PART
        // Test for invalid characters
        $local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
        if ( '' === $local ) {
            $this->addError( "Не верное имя поля $fieldname" );
        }

        // DOMAIN PART
        // Test for sequences of periods
        $domain = preg_replace( '/\.{2,}/', '', $domain );
        if ( '' === $domain ) {
            $this->addError( "Не верно введен домен поля $fieldname" );
        }

        // Test for leading and trailing periods and whitespace
        $domain = trim( $domain, " \t\n\r\0\x0B." );
        if ( '' === $domain ) {
            $this->addError( "Не верно введен домен поля $fieldname" );
        }

        // Split the domain into subs
        $subs = explode( '.', $domain );

        // Assume the domain will have at least two subs
        if ( 2 > count( $subs ) ) {
            $this->addError( "Не верно указана зона домена поля $fieldname" );
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
            $this->addError( "Не верно указан домен поля $fieldname" );
        }

        // Join valid subs into the new domain
        $domain = join( '.', $new_subs );

        // Put the email back together
        $email = $local . '@' . $domain;

        // Congratulations your email made it!
        return $email;
    }

    function sanitize_phone( $phone, $fieldname = 'номер телефона' )
    {
        $phone = static::sanitize_post_data( $phone );
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if( strlen( $phone ) < 6 ) {
            $this->addError( "Короткий $fieldname, он должен содержать не менее 6 символов." );
        }

        if( strlen( $phone ) > 12 ) {
            $this->addError( "Длинный $fieldname, он должен содержать не более 12 символов." );
        }

        if( 0 === strpos($phone, '7') ) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
}
