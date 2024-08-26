<?php
/**
 * @author SALERMAN .Agency
 * @email crm@salerman.ru
 * @phone (391) 205-13-30
 * @documentation: https://help-ru.tilda.cc/forms/webhook
 */

header('Access-Control-Allow-Origin: *');

define('WEB_HOOK_URL', 'https://XXXXXXX.bitrix24.ru/rest/XX/xyzxyzxyzxyz/');

require_once "bitrix24-api.php";

/**
 * Отключить отладку после успешной проверки, что все данные приходят в CRM
 */
$debug = true; // false
Bitrix24Rest::enableDebug($debug);
Bitrix24Rest::log('------- ' . date("d.m.Y H:i:s") . ' -------');

/**
 * Данные из Tilda
 */

/**
 * Настройки формы
 */
$form_name = "Оставить заявку";
$title = "Заявка с сайта site.ru / {$form_name}";
$user_id = 1; // Пользователь, ответственный за лид. Берется из ТЗ
$source_id = 'UC_5XXXD'; // Источник (Сайт site.ru)

/**
 * Кастомные поля форм
 */
$arUF = [
    "UF_CRM_1234567890" => 123, // берутся из ТЗ
];

/**
 * Контактные данные
 */
$name = htmlspecialchars($_POST['name']); // Имя
$phone = htmlspecialchars($_POST['phone']); // Телефон
$email = htmlspecialchars($_POST['email']); // Email

/**
 * Составной комментарий к лиду
 */
$comment = '<b>Комментарий:</b> '; // Комментарий — тут лучше перечислить все значения полей формы
foreach ($_POST as $key => $value) {
    $comment .= "<br>" . "<b>" . htmlspecialchars($key). ":</b> " . htmlspecialchars($value);
}
$comment .= "<br>" . "<b>Ссылка на страницу:</b> <a href='" . $_SERVER["HTTP_REFERER"] ."'>Форма</a>";

$utm_source = htmlspecialchars($_POST['utm_source']); // Источник кампании
$utm_medium = htmlspecialchars($_POST['utm_medium']); // Тип трафика
$utm_campaign = htmlspecialchars($_POST['utm_campaign']); // Название кампании
$utm_content = htmlspecialchars($_POST['utm_content']); // Идентификатор объявления
$utm_term = htmlspecialchars($_POST['utm_term']); // Ключевое слово

/**
 * @example Получение TRACE с помощью JS на сайте, который при submit формы прячется в hidden-поле и в итоге передается через РНР-код
 * @doc https://dev.1c-bitrix.ru/rest_help/crm/cases/analitics/use_analitics_for_add_lead.php
    <script>
    window.onload = function(e) {
        var traceInput = document.getElementById('FORM_TRACE');
        if (traceInput) {
            traceInput.value = b24Tracker.guest.getTrace();
        }
    }
    </script>
 */
$trace = htmlspecialchars($_POST['trace']);

/**
 * Заполнение полей лида
 */
$arFields = Array(
    "TITLE" => $title,
    "STATUS_ID" => "NEW",
    "OPENED" => "Y",
    "ASSIGNED_BY_ID" => $user_id,

    /** ДАННЫЕ КОНТАКТА */
    "NAME" => $name,
    "PHONE" => Array(
        Array(
            "VALUE" => $phone,
            "VALUE_TYPE" => "WORK"
        )
    ),

    /** ДАННЫЕ ЗАЯВКИ */
    "COMMENTS" => $comment,

    /** ПОЛЯ */

    /** ИСТОЧНИК РЕКЛАМЫ */
    "SOURCE_ID" => $source_id,
    "UTM_SOURCE" => $utm_source,
    "UTM_MEDIUM" => $utm_medium,
    "UTM_CAMPAIGN" => $utm_campaign,
    "UTM_CONTENT" => $utm_content,
    "UTM_TERM" => $utm_term,
    "TRACE" => $trace, // это должен быть валидный JSON
);

if (!empty($arUf)) {
    $arFields = array_merge($arFields, $arUf);
}

if (!empty($email)) {
    $arFields['EMAIL'] = Array(
        Array(
            "VALUE" => $email,
            "VALUE_TYPE" => "WORK"
        )
    );
}

try {

    /**
     * Проверяем, есть ли уже контакт с данным телефоном или email
     */

    $contactId = false;

    // Ищем контакт с таким номером телефона
    if (!empty($arFields['PHONE'][0]['VALUE'])) {
        $contactIds = Bitrix24Rest::searchContactByPhone($arFields['PHONE'][0]['VALUE']);
    }

    // Ищем контакт с таким email
    if (empty($contactIds) && !empty($arFields['EMAIL'][0]['VALUE'])) {
        $contactIds = Bitrix24Rest::searchContactByEmail($arFields['EMAIL'][0]['VALUE']);
    }

    if (!empty($contactIds)) {
        $contactId = current($contactIds)['ID'];
        $companyId = current($contactIds)['COMPANY_ID'];
    }

    if ($contactId) {
        $arFields['CONTACT_ID'] = $contactId;
        if (!empty($companyId)) {
            $arFields['COMPANY_ID'] = $companyId;
        }
        unset($arFields['NAME']);
        unset($arFields['PHONE']);
    }

    Bitrix24Rest::log($arFields, 'arFields');

    /**
     * Создаём лид
     */
    $lead = Bitrix24Rest::b24query('crm.lead.add', Array('fields' => $arFields));

    Bitrix24Rest::log($lead, 'b24query→crm.lead.add');

    if ($lead) {
        /**
         * Добавляем товары к лиду
         */
        $products = Bitrix24Rest::b24query("crm.lead.productrows.set", ['id' => $lead,'rows' => [
            [ "PRODUCT_NAME" => "G-Shock Premium GWF-A1000C-1AER", "PRICE" => 103990.00, "QUANTITY" => 1, "TAX_INCLUDED" => "Y", "TAX_RATE" => 20 ], // товар в свободной форме с НДС 20%
            [ "PRODUCT_NAME" => "G-Shock Premium GWF-A1000C-1AER", "PRICE" => 103990.00, "QUANTITY" => 1 ], // товар в свободной форме без НДС
            [ "PRODUCT_ID" => 1234, "PRICE" => 0, "QUANTITY" => 1], // марка товара по ID
            [ "PRODUCT_NAME" => "Доставка курьером", "PRICE" => 150.00, "QUANTITY" => 1 ], // доставка курьером
            //        [ "PRODUCT_NAME" => "СДЭК", "PRICE" => 150.00, "QUANTITY" => 1 ], // доставка СДЭК
            //        [ "PRODUCT_NAME" => "Доставка Яндекс", "PRICE" => 150.00, "QUANTITY" => 1 ], // доставка Яндекс.Маркет DBS
        ]]);
        Bitrix24Rest::log($products, 'b24query→crm.lead.productrows.set');

        /**
         * Добавляем комментарий к лиду
         */
        $msgParams = Array(
            "fields" => Array(
                "COMMENT" => str_replace('<br>',"\n",$arFields['COMMENTS']),
                "ENTITY_TYPE" => 'lead',
                "ENTITY_ID" => $lead,
            )
        );
        $arMsg = Bitrix24Rest::b24query('crm.timeline.comment.add', $msgParams);
        Bitrix24Rest::log($arMsg, 'b24query→crm.timeline.comment.add');
    }

} catch (Exception $e) {
    echo "Error! " . $e->getMessage();
    Bitrix24Rest::log(['code' => $e->getCode(), 'msg' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 'Exception');
}