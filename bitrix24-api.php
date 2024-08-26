<?php
/** SALERMAN .Agency
 * @tel (391) 205-13-30
 * @email info@salerman.ru
 */

if (!function_exists('d')) {
    function d ($data) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}

if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber ($number) {
        $PREFIX = '+7';

        $firstDigit = substr($number, 0, 2);

        if ($firstDigit == $PREFIX && strlen($number) == 12) {
            return $number;
        }

        if ($firstDigit == '7' && strlen($number) == 11) {
            return $PREFIX . substr($number, 1, 10);
        }

        if ($firstDigit == '8' && strlen($number) == 11) {
            return $PREFIX . substr($number, 1, 10);
        }

        if (strlen($number) == 10) {
            return $PREFIX . $number;
        }

        return false;
    }
}

if (!function_exists('clearPhone')) {
    function clearPhone($phone)
    {
        $phone = trim($phone);
        return str_replace(Array(
            '(', ')', '-', '+', '/', ' ', '  ', '   ', '    '
        ), "", $phone);
    }
}

class Bitrix24Rest
{
    private static $debug = false;
    private static $logFile = './bitrix24-log.txt';

    public static function get_url($url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, 'REST-API-client/1.0');
        curl_setopt($curl, CURLOPT_NOPROGRESS, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
        curl_close($curl);
        if (isset($error_msg)) {
            self::log($error_msg, 'get_url→error');
        }
        return $res;
    }

    public static function b24query($method, $params)
    {
        $getUrl = WEB_HOOK_URL . $method . '.json?' . http_build_query($params);
        self::log($getUrl, 'b24query→getUrl');
        self::log($params, 'b24query→params');

        $restResult = self::get_url($getUrl);
        self::log($restResult, 'b24query→rawResult');

        $arsResult = json_decode($restResult, true);
        self::log($arsResult, 'b24query→jsonResult');

        if (!empty($arsResult['result'])) {
            return $arsResult['result'];
        }

        if (!empty($arsResult['error'])) {
            self::log($arsResult['error'] . ": " . $arsResult['error_description'], 'b24query→error');
            throw new Exception($arsResult['error'] . ": " . $arsResult['error_description']);
        }

        return null;
    }

    public static function searchContactByPhone($phone)
    {
        $contacts = [];

        self::log($phone, 'searchContactByPhone→rawPhone');
        $phone = clearPhone($phone);
        self::log($phone, 'searchContactByPhone→clearPhone');

        $phone_ = $phone;
        $cutPhone = substr($phone, strlen($phone)-10, 10);

        $phones = [
            '+7' . $cutPhone,
            '7' . $cutPhone,
            '8' . $cutPhone,
        ];
        self::log($phones, 'searchContactByPhone→phones');

        foreach ($phones as $phone) {
            if (!empty($contacts)) continue;

            $params = [
                'order' => ['ID' => 'DESC'],
                'filter' => ['PHONE' => $phone],
                'select' => ['ID', 'PHONE', "COMPANY_ID"]
            ];

            $contacts = self::b24query('crm.contact.list', $params);
        }
        self::log($contacts, 'searchContactByPhone→contacts');
        return $contacts;
    }

    public static function searchContactByEmail($email)
    {
        $contacts = [];

        self::log($email, 'searchContactByEmail→email');
        $params = [
            'order' => ['ID' => 'DESC'],
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'EMAIL', "COMPANY_ID"]
        ];

        $contacts = self::b24query('crm.contact.list', $params);
        self::log($contacts, 'searchContactByEmail→contacts');

        return $contacts;
    }

    public static function enableDebug ()
    {
        self::$debug = true;
    }

    public static function setLogFile ($filePath)
    {
        self::$logFile = $filePath;
    }

    public static function log ($data, $label = "")
    {
        if (!self::$debug) return;

        $msg = '[' . date('d.m.Y H:i:s') . '] ';
        $msg .= (empty($label)) ? "" : $label . ': ';

        if (is_array($data) || is_object($data)) {
            $msg .= print_r($data, true);
        } else {
            $msg .= $data;
        }

        $msg .= "\n";

        \file_put_contents(self::$logFile, $msg, FILE_APPEND);
    }
}