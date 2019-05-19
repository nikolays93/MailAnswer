<?php

namespace NikolayS93;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

global $i18n;

$i18n = array(
    'RU' => array(
        'MAILIO_MEET_DEV' => 'Обратитесь к администратору сайта.',
        'MAILIO_VARIABLE_NOT_CALLABLE' => 'Объявлен не существующий метод проверки полей. Обратитесь к администратору сайта.',
        'MAILIO_REQ_FIELDS_NOT_EXISTS' => 'Не верно заданы обязательные поля. Обратитесь к администратору сайта.',
        'MAILIO_FIELD_REQUIRED' => 'Поле %s обязательно к заполнению.',
        'MAILIO_NOT_SENT' => 'Ошибка, заявка не отправлена!',
        'MAILIO_BODY_IS_EMPTY' => 'Попытка отправки пустого сообщения. Обратитесь к администратору сайта.',
        'MAILIO_ERR_FIELD' => 'Не верно указано поле %s',
        'MAILIO_ERR_LESS_FIELD' => 'Короткий %s, он должен содержать не менее 6 символов.',
        'MAILIO_ERR_MORE_FIELD' => 'Длинный %s, он должен содержать не более 12 символов.',
        'MAILIO_ERR_MAIL_LOCAL' => 'Не верное имя поля %s',
        'MAILIO_ERR_MAIL_DOMAIN' => 'Не верно указан домен поля %s',
        'MAILIO_ERR_MAIL_BIGDOMAIN' => 'Не верно указана зона домена поля %s',
    ),
);

class PHPMailInterface extends PHPMailer
{
    /** @var boolean set true if is_ajax request is not empty */
    static $is_json;

    /** @var string define protocol from $_SERVER */
    static $protocol;

    /** @var string set CharSet for cirilic letters */
    public $CharSet = 'utf-8';

    /** @var string Mail subject */
    public $Subject = 'Сообщение с сайта';

    /** @var string User Name who sent message: TestMessage <no-reply@domain.ltd> */
    public $fromName = 'TestMessage';

    /** @var string Email name, text before @ */
    public $fromLocal = 'no-reply';

    /** @var sent status: failure | success */
    public $status = 'success';

    /** @var string */
    public $message = 'Заявка успешно отправлена.';

    /** @var array $key => $SanitizeCallback */
    private $fields = array();

    /** @var array $key => $FieldName (label) */
    private $fieldNames = array();

    /** @var array list of required filled value by $key */
    private $requiredFields = array();

    /** @var array */
    public $errors = array();

    function __construct( $ExcludeDefaultFields = false )
    {
        static::$is_json = isset($_REQUEST["is_ajax"]) ? 'false' !== $_REQUEST["is_ajax"] : false;
        static::$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

        if( !$ExcludeDefaultFields ) {
            $this->addField('name',  'Имя');
            $this->addField('email', 'Электронный адрес', array($this, 'sanitize_email'));
            $this->addField('phone', 'Номер телефона', array($this, 'sanitize_phone'));
            $this->addField('text',  'Текст');
        }

        parent::__construct( $exceptions = true );
    }

    public function addField( $field, $fieldname = '', $cb = '' )
    {
        global $i18n;

        if( $cb && !is_callable($cb) ) {
            $this->addError( $i18n['RU']['MAILIO_VARIABLE_NOT_CALLABLE'] );
            $cb = '';
        }

        $this->fields[ $field ]     = $cb;
        $this->fieldNames[ $field ] = $fieldname;
    }

    public function setRequired( $required )
    {
        if( is_string($required) ) $required = array($required);

        $this->requiredFields = (array) $required;
    }

    public function getFields()
    {
        global $i18n;

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
            if( empty($this->fields[ $field ]) ) $this->addError( $i18n['RU']['MAILIO_REQ_FIELDS_NOT_EXISTS'] );
            if( empty($_POST[ $field ]) ) {
                $this->addError( sprintf($i18n['RU']['MAILIO_FIELD_REQUIRED'], $this->fieldNames[ $field ]) );
            }
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
        global $i18n;

        $this->errors[] = $msg;
        $this->status = 'failure';
        $this->message = $i18n['RU']['MAILIO_NOT_SENT'];
    }

    public function sendMail()
    {
        global $i18n;

        if( !$this->getErrors() && empty($this->Body) ) {
            $this->addError( $i18n['RU']['MAILIO_BODY_IS_EMPTY'] );
        }

        try {
            if( !$this->getErrors() ) {
                $this->setFrom( $this->fromLocal . '@' . str_replace('www.', '', $_SERVER['SERVER_NAME']), $this->fromName );
                $this->send();
            }
        }
        catch (Exception $e) {
            $this->addError( "Ошибка сервера: <pre>{$this->ErrorInfo}</pre>." . $i18n['RU']['MAILIO_MEET_DEV'] );
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
        global $i18n;

        $email = static::sanitize_post_data( $email );

        // Test for the minimum length the email can be
        if ( strlen( $email ) < 6 ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_LESS_FIELD'], $fieldname) );
        }

        // Test for an @ character after the first position
        if ( strpos( $email, '@', 1 ) === false ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_FIELD'], $fieldname) );
        }

        // Split out the local and domain parts
        list( $local, $domain ) = explode( '@', $email, 2 );

        // LOCAL PART
        // Test for invalid characters
        $local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
        if ( '' === $local ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_MAIL_LOCAL'], $fieldname) );
        }

        // DOMAIN PART
        // Test for sequences of periods
        $domain = preg_replace( '/\.{2,}/', '', $domain );
        if ( '' === $domain ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_MAIL_DOMAIN'], $fieldname) );
        }

        // Test for leading and trailing periods and whitespace
        $domain = trim( $domain, " \t\n\r\0\x0B." );
        if ( '' === $domain ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_MAIL_DOMAIN'], $fieldname) );
        }

        // Split the domain into subs
        $subs = explode( '.', $domain );

        // Assume the domain will have at least two subs
        if ( 2 > count( $subs ) ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_MAIL_BIGDOMAIN'], $fieldname) );
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
            $this->addError( $i18n['RU']['MAILIO_ERR_MAIL_DOMAIN'] );
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
        global $i18n;

        $phone = static::sanitize_post_data( $phone );
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if( strlen( $phone ) < 6 ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_LESS_FIELD'], $fieldname) );
        }

        if( strlen( $phone ) > 12 ) {
            $this->addError( sprintf($i18n['RU']['MAILIO_ERR_MORE_FIELD'], $fieldname) );
        }

        if( 0 === strpos($phone, '7') ) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
