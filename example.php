<?php
/**
 * @author SALERMAN .Agency
 * @email crm@salerman.ru
 * @phone (391) 205-13-30
 * @documentation: Полная документация по REST API Битрикс24: https://dev.1c-bitrix.ru/rest_help/
 */

define('WEB_HOOK_URL', 'https://XXXXXXX.bitrix24.ru/rest/XX/xyzxyzxyzxyz/');

require_once "bitrix24-api.php";

/**
 * Отключить отладку после успешной проверки, что все данные приходят в CRM
 */
$debug = true; // false
Bitrix24Rest::enableDebug($debug);
Bitrix24Rest::log('------- ' . date("d.m.Y H:i:s") . ' -------');


/**
 * Тестовые данные
 */
$name = 'Евсергийъ'; // Имя
$phone = '+79000000000'; // Телефон
$email = 'test@site.ru'; // Email
$comment = '<b>Комментарий:</b> '; // Комментарий — тут лучше перечислить все значения полей формы

$comment .= "<br>" . "<b>Сообщение менеджеру:</b> тест";
$comment .= "<br>" . "<b>Ссылка на страницу:</b> <a href='https://site.ru/services/page24/'>Форма</a>";

$form_name = "Оставить заявку";

$source_id = 'UC_5XXXD'; // Источник (Сайт site.ru)

$utm_source = "yandex"; // Источник кампании
$utm_medium = "cpc"; // Тип трафика
$utm_campaign = "testovaya_kampaniya"; // Название кампании
$utm_content = ""; // Идентификатор объявления
$utm_term = ""; // Ключевое слово
$trace = "{\"url\":\"https://site.ru/\",\"ref\":\"https://yandex.ru/\",\"device\":{\"isMobile\":false},\"tags\":{\"ts\":1637210050,\"list\":{},\"gclid\":null},\"client\":{\"gaId\":\"1234213.1234567890\",\"yaId\":\"12345678901234567890\"},\"pages\":{\"list\":[[\"https://site.ru/catalog/page/\",1637210051,\"Бизнес подарки \"],[\"https://site.ru/catalog/good/002349_bloknot/\",1637640793,\"234567 Блокнот в Красноярске\"]]},\"gid\":null,\"previous\":{\"list\":[]}}" ;
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


$user_id = 1; // Пользователь, ответственный за лид. Берется из ТЗ

/**
 * Заполнение полей лида
 */
$arFields = Array(
    "TITLE" => "Заявка с сайта site.ru / {$form_name}",
    "STATUS_ID" => "NEW",
    "OPENED" => "Y",
    "ASSIGNED_BY_ID" => $user_id,

    /** ДАННЫЕ ЗАЯВКИ */
    "COMMENTS" => $comment,

    /** ДАННЫЕ КОНТАКТА */
    "NAME" => $name,
    "PHONE" => Array(
        Array(
            "VALUE" => $phone,
            "VALUE_TYPE" => "MOBILE"
        )
    ),
    "EMAIL" => Array(
        Array(
            "VALUE" => $email,
            "VALUE_TYPE" => "HOME"
        )
    ),

    /** ПОЛЯ */
    "UF_CRM_1234567890" => 123, // берутся из ТЗ

    /** ИСТОЧНИК РЕКЛАМЫ */
    "SOURCE_ID" => $source_id,
    "UTM_SOURCE" => $utm_source,
    "UTM_MEDIUM" => $utm_medium,
    "UTM_CAMPAIGN" => $utm_campaign,
    "UTM_CONTENT" => $utm_content,
    "UTM_TERM" => $utm_term,
    "TRACE" => $trace, // это должен быть валидный JSON
);

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