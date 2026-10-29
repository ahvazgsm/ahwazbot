<?php

require_once 'config.php';

if (!isset($from_id))           { $from_id = 0; }
if (!isset($datain))            { $datain = ''; }
if (!isset($text))              { $text = ''; }
if (!isset($message_id))        { $message_id = 0; }
if (!isset($callback_query_id)) { $callback_query_id = ''; }
if (!isset($username))          { $username = ''; }
if (!isset($first_name))        { $first_name = ''; }

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('rx_log_event')) {
        rx_log_event('DB_UNAVAILABLE', 'Keyboard layouts loaded with no PDO; skipping DB-dependent setup.', [
            'where' => 'layouts_1',
        ]);
    }
    return;
}

$setting = select("setting", "*", null, null,"select");
$textbotlang = languagechange(REFACTORED_LEGACY_ROOT.'/text.json');
if (!is_array($textbotlang)) {
    $textbotlang = [];
}
if (!isset($textbotlang['Admin']) || !is_array($textbotlang['Admin'])) {
    $textbotlang['Admin'] = [];
}
$textbotlang['Admin']['backadmin'] = $textbotlang['Admin']['backadmin'] ?? "🏠 بازگشت به منوی مدیریت";
$textbotlang['Admin']['backmenu'] = $textbotlang['Admin']['backmenu'] ?? "▶️ بازگشت به منوی قبل";
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}

if (!function_exists('rx_inlineButtonMapFile')) {
    function rx_inlineButtonMapFile() {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/').'/rx_inline_button_map.json';
    }
}
if (!function_exists('rx_storeInlineButtonText')) {
    function rx_storeInlineButtonText($text) {
        $code = 'rxb_' . substr(hash('sha256', (string)$text), 0, 24);
        $file = rx_inlineButtonMapFile();
        $map = [];
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            if (is_array($decoded)) $map = $decoded;
        }
        $map[$code] = (string)$text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveInlineButtonText')) {
    function rx_resolveInlineButtonText($code) {
        if (!is_string($code) || strpos($code, 'rxb_') !== 0) return null;
        $file = rx_inlineButtonMapFile();
        if (!is_file($file)) return null;
        $map = json_decode((string)@file_get_contents($file), true);
        return is_array($map) && isset($map[$code]) ? (string)$map[$code] : null;
    }
}
if (!function_exists('rx_safeCallbackData')) {
    function rx_safeCallbackData($callbackData, $buttonText = '') {
        $callbackData = (string)$callbackData;
        if ($callbackData === '') $callbackData = (string)$buttonText;
        return strlen($callbackData) <= 64 ? $callbackData : rx_storeInlineButtonText($buttonText !== '' ? $buttonText : $callbackData);
    }
}

if (!function_exists('rx_keyboardToInline')) {
    function rx_keyboardToInline($keyboardArray) {
        if (!is_array($keyboardArray)) return json_encode($keyboardArray);
        if (isset($keyboardArray['inline_keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!isset($keyboardArray['keyboard'])) return json_encode($keyboardArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $inline = ['inline_keyboard' => []];
        foreach ($keyboardArray['keyboard'] as $row) {
            $newRow = [];
            foreach ($row as $btn) {
                if (isset($btn['text'])) {
                    $buttonText = (string) $btn['text'];
                    $callbackData = isset($btn['callback_data']) ? (string)$btn['callback_data'] : $buttonText;
                    if ($buttonText === ($GLOBALS['textbotlang']['users']['backbtn'] ?? '')) {
                        $callbackData = 'backuser';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backadmin'] ?? '')) {
                        $callbackData = 'admin';
                    } elseif ($buttonText === ($GLOBALS['textbotlang']['Admin']['backmenu'] ?? '')) {
                        $callbackData = 'backmenu';
                    }
                    $newRow[] = ['text' => $buttonText, 'callback_data' => rx_safeCallbackData($callbackData, $buttonText)];
                }
            }
            if (!empty($newRow)) $inline['inline_keyboard'][] = $newRow;
        }
        return json_encode($inline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'textbot'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'accountwallet' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',
    'text_affiliates' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'tonpay' => '',
    'cubepay' => '',
    'blupal' => '',
    'abangateway' => '',
    'atlaspay' => '',
    'zarinpal' => '',
    'text_fq' => '',
    'textpaymentnotverify' =>"",
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_star_telegram' => "",
    'text_extend' => '',
    'textsnowpayment' => ''

);
if ($table_exists) {
    $textdatabot =  select("textbot", "*", null, null,"fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
$adminrulecheck = select("admin", "*", "id_admin", $from_id,"select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
$users = select("user", "*", "id", $from_id,"select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
        'agent' => '',
        'limit_usertest' => '',
        'Processing_value' => '',
        'Processing_value_four' => '',
        'cardpayment' => ""
    );
}
$replacements = [
    'text_usertest' => $datatextbot['text_usertest'],
    'text_Purchased_services' => $datatextbot['text_Purchased_services'],
    'text_support' => $datatextbot['text_support'],
    'text_help' => $datatextbot['text_help'],
    'accountwallet' => $datatextbot['accountwallet'],
    'text_sell' => $datatextbot['text_sell'],
    'text_Tariff_list' => $datatextbot['text_Tariff_list'],
    'text_affiliates' => $datatextbot['text_affiliates'],
    'text_wheel_luck' => $datatextbot['text_wheel_luck'],
    'text_extend' => $datatextbot['text_extend']
];
$admin_idss = select("admin", "*", "id_admin", $from_id,"count");
$temp_addtional_key = [];
$keyboardLayout = json_decode($setting['keyboardmain'], true);
$keyboardRows = [];
if (is_array($keyboardLayout) && isset($keyboardLayout['keyboard']) && is_array($keyboardLayout['keyboard'])) {
    $keyboardRows = $keyboardLayout['keyboard'];
}

$rxMiniAppDomain = (string)($domainhosts ?? ($GLOBALS['domainhosts'] ?? ''));
$rxMiniAppApply = function (array $rows) use ($datatextbot, $rxMiniAppDomain) {
    $rxMiniAppUrl = '';
    $rxMiniAppHost = rtrim((string)preg_replace('#^https?://#i', '', trim($rxMiniAppDomain)), '/');
    if ($rxMiniAppHost !== '') {
        $rxMiniAppCandidate = 'https://' . $rxMiniAppHost . '/app/';
        if (filter_var($rxMiniAppCandidate, FILTER_VALIDATE_URL) !== false
            && strtolower((string)parse_url($rxMiniAppCandidate, PHP_URL_SCHEME)) === 'https'
            && (string)parse_url($rxMiniAppCandidate, PHP_URL_HOST) !== '') {
            $rxMiniAppUrl = $rxMiniAppCandidate;
        }
    }
    $rxMiniAppLabel = (string)($datatextbot['text_miniapp_button'] ?? '');
    if (trim($rxMiniAppLabel) === '') $rxMiniAppLabel = '🚀 Open Mini App';
    $rxMiniAppFound = false;
    $rxMiniAppRows = [];
    $rxMiniAppKnownLabels = [
        '🚀 Open Mini App', 'Open Mini App',
        '🚀 باز کردن مینی‌اپ', 'باز کردن مینی‌اپ', '🚀 بازکردن مینی‌اپ',
    ];
    foreach ($rows as $rxMiniAppRow) {
        if (!is_array($rxMiniAppRow)) {
            $rxMiniAppRows[] = $rxMiniAppRow;
            continue;
        }
        $rxMiniAppNewRow = [];
        foreach ($rxMiniAppRow as $rxMiniAppBtn) {
            // Some saved keyboard layouts store buttons as strings instead of ['text' => ...].
            if (is_string($rxMiniAppBtn)) {
                $rxMiniAppBtn = ['text' => $rxMiniAppBtn];
            }
            if (!is_array($rxMiniAppBtn) || !isset($rxMiniAppBtn['text'])) {
                $rxMiniAppNewRow[] = $rxMiniAppBtn;
                continue;
            }
            $rxMiniAppButtonText = trim((string)$rxMiniAppBtn['text']);
            $rxMiniAppIsButton = ($rxMiniAppButtonText === 'text_miniapp_button')
                || ($rxMiniAppButtonText !== '' && $rxMiniAppButtonText === trim($rxMiniAppLabel))
                || in_array($rxMiniAppButtonText, $rxMiniAppKnownLabels, true);
            if ($rxMiniAppIsButton) {
                $rxMiniAppFound = true;
                // Never leave the internal token as a normal Telegram text button.
                if ($rxMiniAppUrl === '') {
                    continue;
                }
                unset(
                    $rxMiniAppBtn['callback_data'], $rxMiniAppBtn['_rxsk'], $rxMiniAppBtn['url'],
                    $rxMiniAppBtn['login_url'], $rxMiniAppBtn['switch_inline_query'],
                    $rxMiniAppBtn['switch_inline_query_current_chat'], $rxMiniAppBtn['callback_game'],
                    $rxMiniAppBtn['pay'], $rxMiniAppBtn['copy_text']
                );
                $rxMiniAppBtn['text'] = $rxMiniAppLabel;
                $rxMiniAppBtn['web_app'] = ['url' => $rxMiniAppUrl];
            }
            $rxMiniAppNewRow[] = $rxMiniAppBtn;
        }
        if (!empty($rxMiniAppNewRow) || empty($rxMiniAppRow)) {
            $rxMiniAppRows[] = $rxMiniAppNewRow;
        }
    }
    if ($rxMiniAppFound && $rxMiniAppUrl === '' && function_exists('rx_log_event')) {
        rx_log_event('MINIAPP_BUTTON_SKIPPED', 'Main keyboard Mini App button skipped: invalid or empty domainhosts.', [
            'where' => 'layouts_1',
        ]);
    }
    return $rxMiniAppRows;
};

if (($setting['inlinebtnmain'] == "oninline" || (function_exists('rx_richSectionActive') && rx_richSectionActive('main_menu'))) && !empty($keyboardRows)) {
    $trace_keyboard = $keyboardRows;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == "text_sell") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_buy";
            }
            if ($keyboard['text'] == "accountwallet") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "accountbtn";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_account";
            }
            if ($keyboard['text'] == "text_Tariff_list") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_tariff";
            }
            if ($keyboard['text'] == "text_wheel_luck") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
            }
            if ($keyboard['text'] == "text_affiliates") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
            }
            if ($keyboard['text'] == "text_extend") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
            }
            if ($keyboard['text'] == "text_support") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
            }
            if ($keyboard['text'] == "text_Purchased_services") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "myservicesbtn";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_myservices";
            }
            if ($keyboard['text'] == "text_help") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpmenubtn";
                $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "helpbtns";
            }
            if ($keyboard['text'] == "text_usertest") {
                $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
            }
        }
    }
    if ($admin_idss != 0) {
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin, 'callback_data' => "admin"];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent, 'callback_data' => "agentpanel"];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req, 'callback_data' => "requestagent"];
        }
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboardcustom = $trace_keyboard;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (is_array($keyboardcustom)) $keyboardcustom = $rxMiniAppApply($keyboardcustom);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['inline_keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard), false)
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
    if (function_exists('rx_ui_mark_keyboard')) { $keyboard = rx_ui_mark_keyboard($keyboard, 'main_menu'); }
} else {
    if ($admin_idss != 0) {
        $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
        if (trim($rx_lbl_admin) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_admin];
        }
    }
    if ($users['agent'] != "f") {
        $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
        if (trim($rx_lbl_agent) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_agent];
        }
    }
    if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
        $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
        if (trim($rx_lbl_req) !== '') {
            $temp_addtional_key[] = ['text' => $rx_lbl_req];
        }
    }
    $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
    $keyboardcustom = $keyboardRows;
    $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
    if (is_array($keyboardcustom)) $keyboardcustom = $rxMiniAppApply($keyboardcustom);
    if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
    $keyboard['keyboard'] = $keyboardcustom;
    $keyboard = function_exists('rx_finalizeInlineAdminKb')
        ? rx_finalizeInlineAdminKb(json_encode($keyboard), false)
        : json_encode($keyboard);
    $keyboard = function_exists('rx_sanitizeKeyboardButtons') ? rx_sanitizeKeyboardButtons($keyboard) : $keyboard;
    if (function_exists('rx_ui_mark_keyboard')) { $keyboard = rx_ui_mark_keyboard($keyboard, 'main_menu'); }
}
global $_rx_acc_styles, $_rx_tx_styles, $_rx_pay_styles, $_rx_payrcpt_styles, $_rx_adm_styles, $_rx_set_styles, $_rx_shp_styles, $_rx_nav_styles, $_rx_rol_styles, $_rx_gw_styles, $_rx_svc_styles, $_rx_feat_styles, $_rx_chan_styles, $_rx_helpa_styles, $_rx_cat_styles, $_rx_prod_styles, $_rx_pedit_styles;

$_rx_acc_styles  = [];
$_rx_tx_styles   = [];
$_rx_pay_styles  = [];
$_rx_payrcpt_styles = [];
$_rx_adm_styles  = [];
$_rx_set_styles  = [];
$_rx_shp_styles  = [];
$_rx_nav_styles  = [];
$_rx_rol_styles  = [];
$_rx_gw_styles   = [];
$_rx_svc_styles  = [];
$_rx_feat_styles  = [];
$_rx_chan_styles  = [];
$_rx_helpa_styles = [];
$_rx_cat_styles   = [];
$_rx_prod_styles  = [];
$_rx_pedit_styles = [];
if (!empty($setting['keyboard_styles_all'])) {
    $_rx_all_kbs = json_decode($setting['keyboard_styles_all'], true);
    if (is_array($_rx_all_kbs)) {
        if (!empty($_rx_all_kbs['account']))        $_rx_acc_styles = $_rx_all_kbs['account'];
        if (!empty($_rx_all_kbs['tx_stats']))        $_rx_tx_styles  = $_rx_all_kbs['tx_stats'];
        if (!empty($_rx_all_kbs['payment']))        $_rx_pay_styles = $_rx_all_kbs['payment'];
        if (!empty($_rx_all_kbs['pay_receipt']))    $_rx_payrcpt_styles = $_rx_all_kbs['pay_receipt'];
        if (!empty($_rx_all_kbs['admin_main']))     $_rx_adm_styles = $_rx_all_kbs['admin_main'];
        if (!empty($_rx_all_kbs['admin_settings'])) $_rx_set_styles = $_rx_all_kbs['admin_settings'];
        if (!empty($_rx_all_kbs['admin_shop']))     $_rx_shp_styles = $_rx_all_kbs['admin_shop'];
        if (!empty($_rx_all_kbs['user_nav']))       $_rx_nav_styles = $_rx_all_kbs['user_nav'];
        if (!empty($_rx_all_kbs['admin_roles']))    $_rx_rol_styles = $_rx_all_kbs['admin_roles'];
        if (!empty($_rx_all_kbs['admin_gateways'])) $_rx_gw_styles  = $_rx_all_kbs['admin_gateways'];
        if (!empty($_rx_all_kbs['service']))        $_rx_svc_styles = $_rx_all_kbs['service'];
        if (!empty($_rx_all_kbs['admin_features']))   $_rx_feat_styles  = $_rx_all_kbs['admin_features'];
        if (!empty($_rx_all_kbs['admin_channel']))    $_rx_chan_styles  = $_rx_all_kbs['admin_channel'];
        if (!empty($_rx_all_kbs['admin_help']))       $_rx_helpa_styles = $_rx_all_kbs['admin_help'];
        if (!empty($_rx_all_kbs['admin_category']))   $_rx_cat_styles   = $_rx_all_kbs['admin_category'];
        if (!empty($_rx_all_kbs['admin_products']))   $_rx_prod_styles  = $_rx_all_kbs['admin_products'];
        if (!empty($_rx_all_kbs['admin_product_edit'])) $_rx_pedit_styles = $_rx_all_kbs['admin_product_edit'];
    }
}

if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
    $_rx_acc_styles   = $_rx_acc_styles   + rx_getKeyboardDefaultStyles('account');
    $_rx_tx_styles    = $_rx_tx_styles    + rx_getKeyboardDefaultStyles('tx_stats');
    $_rx_pay_styles   = $_rx_pay_styles   + rx_getKeyboardDefaultStyles('payment');
    $_rx_nav_styles   = $_rx_nav_styles   + rx_getKeyboardDefaultStyles('user_nav');
}
$_rx_adm_styles = $_rx_set_styles = $_rx_shp_styles = $_rx_rol_styles = $_rx_gw_styles = $_rx_feat_styles = $_rx_chan_styles = $_rx_helpa_styles = $_rx_cat_styles = $_rx_prod_styles = $_rx_pedit_styles = ['__rxplain__' => 1];
$_rx_svc_styles['__rxkeep__'] = $_rx_acc_styles['__rxkeep__'] = $_rx_pay_styles['__rxkeep__'] = $_rx_payrcpt_styles['__rxkeep__'] = $_rx_nav_styles['__rxkeep__'] = $_rx_tx_styles['__rxkeep__'] = 1;
$GLOBALS['_rx_acc_styles'] = $_rx_acc_styles;
$GLOBALS['_rx_tx_styles'] = $_rx_tx_styles;
$GLOBALS['_rx_pay_styles'] = $_rx_pay_styles;
$GLOBALS['_rx_payrcpt_styles'] = $_rx_payrcpt_styles;
$GLOBALS['_rx_adm_styles'] = $_rx_adm_styles;
$GLOBALS['_rx_set_styles'] = $_rx_set_styles;
$GLOBALS['_rx_shp_styles'] = $_rx_shp_styles;
$GLOBALS['_rx_nav_styles'] = $_rx_nav_styles;
$GLOBALS['_rx_rol_styles'] = $_rx_rol_styles;
$GLOBALS['_rx_gw_styles'] = $_rx_gw_styles;
$GLOBALS['_rx_svc_styles'] = $_rx_svc_styles;
$GLOBALS['_rx_feat_styles'] = $_rx_feat_styles;
$GLOBALS['_rx_chan_styles'] = $_rx_chan_styles;
$GLOBALS['_rx_helpa_styles'] = $_rx_helpa_styles;
$GLOBALS['_rx_cat_styles'] = $_rx_cat_styles;
$GLOBALS['_rx_prod_styles'] = $_rx_prod_styles;
$GLOBALS['_rx_pedit_styles'] = $_rx_pedit_styles;
if (!function_exists('rx_kb_style')) {
    function rx_kb_style(array $btn, string $key, ?array $styles = []): array {
        $styles = is_array($styles) ? $styles : [];
        if (!empty($styles['__rxplain__' ])) { $btn['_rxap'] = 1; return $btn; }
        if (!empty($styles['__rxkeep__'])) { $btn['_rxkeep'] = 1; }
        if (!empty($styles[$key]) && $styles[$key] !== 'default') {
            $btn['style'] = $styles[$key];
            return $btn;
        }

        if (!isset($btn['style']) && function_exists('rx_kb_guess_style_from_text') && isset($btn['text'])
            && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $rxGuessed = rx_kb_guess_style_from_text((string)$btn['text']);
            if ($rxGuessed !== null) {
                $btn['style'] = $rxGuessed;
            } else {
                $btn['style'] = 'primary';
            }
        }
        return $btn;
    }
}
if (!function_exists('rx_kb_encode')) {
    function rx_kb_encode(array $rows, $forceInline = null): string {
        global $setting, $textbotlang;
        $useInline = ($forceInline !== null)
            ? (bool)$forceInline
            : (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] === 'oninline');
        if ($useInline) {
            $sanitized = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $newRow = [];
                foreach ($row as $btn) {
                    if (is_string($btn)) {
                        $btn = ['text' => $btn];
                    }
                    if (!is_array($btn) || !isset($btn['text'])) {
                        continue;
                    }
                    $bText = (string)$btn['text'];
                    $hasAction = isset($btn['callback_data']) || isset($btn['url']) || isset($btn['web_app'])
                        || isset($btn['login_url']) || isset($btn['switch_inline_query'])
                        || isset($btn['switch_inline_query_current_chat']) || isset($btn['callback_game'])
                        || isset($btn['pay']);
                    if (!$hasAction) {
                        $cb = $bText;
                        if (isset($textbotlang['Admin']['backadmin']) && $bText === $textbotlang['Admin']['backadmin']) {
                            $cb = 'adm_hub_main';
                        } elseif (isset($textbotlang['Admin']['backmenu']) && $bText === $textbotlang['Admin']['backmenu']) {
                            $cb = 'adm_backmenu';
                        } elseif (isset($textbotlang['users']['backbtn']) && $bText === $textbotlang['users']['backbtn']) {
                            $cb = 'backuser';
                        }
                        $btn['callback_data'] = function_exists('rx_safeCallbackData')
                            ? rx_safeCallbackData($cb, $bText)
                            : (strlen($cb) <= 64 ? $cb : substr($cb, 0, 64));
                    }
                    $newRow[] = $btn;
                }
                if (!empty($newRow)) {
                    $sanitized[] = $newRow;
                }
            }
            return json_encode(['inline_keyboard' => $sanitized], JSON_UNESCAPED_UNICODE);
        }

        $replyRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $newRow = [];
            foreach ($row as $btn) {
                if (is_string($btn)) {
                    $newRow[] = ['text' => $btn];
                } elseif (is_array($btn) && isset($btn['text'])) {
                    $newRow[] = ['text' => (string)$btn['text']];
                }
            }
            if (!empty($newRow)) {
                $replyRows[] = $newRow;
            }
        }
        return json_encode(['keyboard' => $replyRows, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    }
}
if($adminrulecheck['rule'] == "administrator"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => "🚦 آمادگی فروش", 'callback_data' => 'admin_readiness'], 'admin_readiness', $_rx_adm_styles)],
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'admin_status'], 'admin_status', $_rx_adm_styles)],
        [rx_kb_style(['text' => "📁 مدیریت پنل‌ها و سرورها", 'callback_data' => 'admin_panels'], 'admin_panels', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🏬 تنظیمات فروشگاه", 'callback_data' => 'admin_shop'], 'admin_shop', $_rx_adm_styles),
            rx_kb_style(['text' => "💎 مالی و گزارشات", 'callback_data' => 'admin_finance'], 'admin_finance', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "👥 مدیریت کاربران", 'callback_data' => 'admin_usershub'], 'admin_usershub', $_rx_adm_styles)],
        [
            rx_kb_style(['text' => "🤙 بخش پشتیبانی", 'callback_data' => 'admin_support'], 'admin_support', $_rx_adm_styles),
            rx_kb_style(['text' => "📚 بخش آموزش", 'callback_data' => 'admin_help'], 'admin_help', $_rx_adm_styles)
        ],
        [rx_kb_style(['text' => "📢 کانال و اطلاع‌رسانی", 'callback_data' => 'admin_channelhub'], 'admin_channelhub', $_rx_adm_styles)],
        [rx_kb_style(['text' => "⚙️ تنظیمات فنی و ربات", 'callback_data' => 'admin_settings'], 'admin_settings', $_rx_adm_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'admin_back'], 'admin_back', $_rx_adm_styles)]
    ]);
}
if($adminrulecheck['rule'] == "Seller"){
$keyboardadmin = rx_kb_encode([
        [rx_kb_style(['text' => $textbotlang['Admin']['Status']['btn'], 'callback_data' => 'seller_status'], 'seller_status', $_rx_rol_styles)],
        [rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'seller_users'], 'seller_users', $_rx_rol_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'seller_back'], 'seller_back', $_rx_rol_styles)]
    ]);
}
if($adminrulecheck['rule'] == "support"){
$keyboardadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "👤 مدیریت کاربر", 'callback_data' => 'support_users'], 'support_users', $_rx_rol_styles),
            rx_kb_style(['text' => "👁‍🗨 جستجو کاربر", 'callback_data' => 'support_search'], 'support_search', $_rx_rol_styles)
        ],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'support_back'], 'support_back', $_rx_rol_styles)]
    ]);
}
$CartManage = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کارت به کارت", 'callback_data' => 'cart_title'], 'cart_title', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💳 مدیریت شماره کارت", 'callback_data' => 'cart_manage_inline'], 'cart_manage_inline', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "👤 آیدی پشتیبانی", 'callback_data' => 'cart_support'], 'cart_support', $_rx_gw_styles),
            rx_kb_style(['text' => "💳 آفلاین در پیوی", 'callback_data' => 'cart_pvmode'], 'cart_pvmode', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "💰 کش‌بک کارت", 'callback_data' => 'cart_cashback'], 'cart_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔒 کارت پس از اولین پرداخت", 'callback_data' => 'cart_firstpay'], 'cart_firstpay', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف کارت به کارت", 'callback_data' => 'cart_min'], 'cart_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف کارت به کارت", 'callback_data' => 'cart_max'], 'cart_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش کارت به کارت", 'callback_data' => 'cart_edu'], 'cart_edu', $_rx_gw_styles)],
        [['text' => "🔑 حداقل مبلغ احراز کارت", 'callback_data' => 'cart_cvmin']],
        [['text' => "🤖 تایید رسید بدون بررسی", 'callback_data' => 'cart_autocheck']],
        [['text' => "🔐 تأیید رسید با بررسی", 'callback_data' => 'cart_smsverify']],
        [['text' => "⚙️ تنظیمات تایید خودکار", 'callback_data' => 'cart_except_user']],
        [['text' => "⏳ زمان تایید خودکار", 'callback_data' => 'cart_autotime']],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'], 'cart_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cart_backmenu']
        ]
    ]);
$trnado = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه ترونادو", 'callback_data' => 'trnado_name'], 'trnado_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key ترونادو", 'callback_data' => 'trnado_apikey'], 'trnado_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔏 ثبت کلید امضای IPN ترونادو", 'callback_data' => 'trnado_signingkey'], 'trnado_signingkey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💼 آدرس کیف پول ترونادو", 'callback_data' => 'trnado_wallet'], 'trnado_wallet', $_rx_gw_styles)],
        [rx_kb_style(['text' => "⚖️ درصد کارمزد کسب‌وکار", 'callback_data' => 'trnado_wage'], 'trnado_wage', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک ترونادو", 'callback_data' => 'trnado_cashback'], 'trnado_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف ترونادو", 'callback_data' => 'trnado_min'], 'trnado_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف ترونادو", 'callback_data' => 'trnado_max'], 'trnado_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش ترونادو", 'callback_data' => 'trnado_edu'], 'trnado_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'], 'trnado_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'trnado_backmenu']
        ]
    ]);
$tonpay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه تون‌پی", 'callback_data' => 'tonpay_name'], 'tonpay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key تون‌پی", 'callback_data' => 'tonpay_apikey'], 'tonpay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔐 ثبت API Key کاستوم تون‌پی", 'callback_data' => 'tonpay_customkey'], 'tonpay_customkey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔀 مقصد پرداخت تون‌پی", 'callback_data' => 'tonpay_paymode'], 'tonpay_paymode', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک تون‌پی", 'callback_data' => 'tonpay_cashback'], 'tonpay_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف تون‌پی", 'callback_data' => 'tonpay_min'], 'tonpay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف تون‌پی", 'callback_data' => 'tonpay_max'], 'tonpay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش تون‌پی", 'callback_data' => 'tonpay_edu'], 'tonpay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'tonpay_back'], 'tonpay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'tonpay_backmenu']
        ]
    ]);
$cubepay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه کیوب‌پی", 'callback_data' => 'cubepay_name'], 'cubepay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت توکن API کیوب‌پی", 'callback_data' => 'cubepay_apikey'], 'cubepay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک کیوب‌پی", 'callback_data' => 'cubepay_cashback'], 'cubepay_cashback', $_rx_gw_styles)],
        [rx_kb_style(['text' => "⚖️ کارمزد کیوب‌پی", 'callback_data' => 'cubepay_fee'], 'cubepay_fee', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف کیوب‌پی", 'callback_data' => 'cubepay_min'], 'cubepay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف کیوب‌پی", 'callback_data' => 'cubepay_max'], 'cubepay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش کیوب‌پی", 'callback_data' => 'cubepay_edu'], 'cubepay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cubepay_back'], 'cubepay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'cubepay_backmenu']
        ]
    ]);
$blupal = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه بلوپال", 'callback_data' => 'blupal_name'], 'blupal_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key بلوپال", 'callback_data' => 'blupal_apikey'], 'blupal_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک بلوپال", 'callback_data' => 'blupal_cashback'], 'blupal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف بلوپال", 'callback_data' => 'blupal_min'], 'blupal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف بلوپال", 'callback_data' => 'blupal_max'], 'blupal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش بلوپال", 'callback_data' => 'blupal_edu'], 'blupal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'blupal_back'], 'blupal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'blupal_backmenu']
        ]
    ]);
$variza = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه واریزا", 'callback_data' => 'variza_name'], 'variza_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت توکن API واریزا", 'callback_data' => 'variza_apikey'], 'variza_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔐 ثبت کلید وب‌هوک واریزا", 'callback_data' => 'variza_webhooksecret'], 'variza_webhooksecret', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک واریزا", 'callback_data' => 'variza_cashback'], 'variza_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف واریزا", 'callback_data' => 'variza_min'], 'variza_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف واریزا", 'callback_data' => 'variza_max'], 'variza_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش واریزا", 'callback_data' => 'variza_edu'], 'variza_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'variza_back'], 'variza_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'variza_backmenu']
        ]
    ]);
$abangateway = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه آبان گیت وی", 'callback_data' => 'abangateway_name'], 'abangateway_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔗 ثبت آدرس درگاه آبان گیت وی", 'callback_data' => 'abangateway_url'], 'abangateway_url', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت کلید اتصال آبان گیت وی", 'callback_data' => 'abangateway_apikey'], 'abangateway_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک آبان گیت وی", 'callback_data' => 'abangateway_cashback'], 'abangateway_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف آبان گیت وی", 'callback_data' => 'abangateway_min'], 'abangateway_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف آبان گیت وی", 'callback_data' => 'abangateway_max'], 'abangateway_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش آبان گیت وی", 'callback_data' => 'abangateway_edu'], 'abangateway_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'abangateway_back'], 'abangateway_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'abangateway_backmenu']
        ]
    ]);
$atlaspay = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه اطلس‌پی", 'callback_data' => 'atlaspay_name'], 'atlaspay_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔑 ثبت API Key اطلس‌پی", 'callback_data' => 'atlaspay_apikey'], 'atlaspay_apikey', $_rx_gw_styles)],
        [rx_kb_style(['text' => "🔀 مقصد پرداخت اطلس‌پی", 'callback_data' => 'atlaspay_paymode'], 'atlaspay_paymode', $_rx_gw_styles)],
        [rx_kb_style(['text' => "📊 موجودی و اطلاعات حساب", 'callback_data' => 'atlaspay_account'], 'atlaspay_account', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک اطلس‌پی", 'callback_data' => 'atlaspay_cashback'], 'atlaspay_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف اطلس‌پی", 'callback_data' => 'atlaspay_min'], 'atlaspay_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف اطلس‌پی", 'callback_data' => 'atlaspay_max'], 'atlaspay_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش اطلس‌پی", 'callback_data' => 'atlaspay_edu'], 'atlaspay_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'atlaspay_back'], 'atlaspay_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'atlaspay_backmenu']
        ]
    ]);
$keyboardzarinpal = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه زرین پال", 'callback_data' => 'zpal_name'], 'zpal_name', $_rx_gw_styles)],
        [rx_kb_style(['text' => "مرچنت زرین پال", 'callback_data' => 'zpal_merchant'], 'zpal_merchant', $_rx_gw_styles)],
        [rx_kb_style(['text' => "💰 کش بک زرین پال", 'callback_data' => 'zpal_cashback'], 'zpal_cashback', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "⬇️ کف زرین پال", 'callback_data' => 'zpal_min'], 'zpal_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف زرین پال", 'callback_data' => 'zpal_max'], 'zpal_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش زرین پال", 'callback_data' => 'zpal_edu'], 'zpal_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'zpal_back'], 'zpal_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'zpal_backmenu']
        ]
    ]);
$NowPaymentsManage = rx_kb_encode([
        [rx_kb_style(['text' => "🏷️ نام نمایشی درگاه plisio", 'callback_data' => 'plisio_name'], 'plisio_name', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => "🧩 api plisio", 'callback_data' => 'plisio_api'], 'plisio_api', $_rx_gw_styles),
            rx_kb_style(['text' => "💰 کش بک plisio", 'callback_data' => 'plisio_cashback'], 'plisio_cashback', $_rx_gw_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ کف plisio", 'callback_data' => 'plisio_min'], 'plisio_min', $_rx_gw_styles),
            rx_kb_style(['text' => "⬆️ سقف plisio", 'callback_data' => 'plisio_max'], 'plisio_max', $_rx_gw_styles)
        ],
        [rx_kb_style(['text' => "📚 آموزش plisio", 'callback_data' => 'plisio_edu'], 'plisio_edu', $_rx_gw_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'plisio_back'], 'plisio_back', $_rx_gw_styles),
            ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'plisio_backmenu']
        ]
    ]);
$mainAdminId = isset($adminnumber) ? trim((string) $adminnumber) : '';
$currentUserId = isset($from_id) ? trim((string) $from_id) : '';

$settingPanelRows = [
    [rx_kb_style(['text' => "⚙️ وضعیت قابلیت ها", 'callback_data' => 'set_features'], 'set_features', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "🗑 بهینه سازی ربات", 'callback_data' => 'set_optimize'], 'set_optimize', $_rx_set_styles),
        rx_kb_style(['text' => "✅ پنل تحت وب", 'callback_data' => 'set_webpanel'], 'set_webpanel', $_rx_set_styles)
    ],
    [rx_kb_style(['text' => "📝 تنظیم متن ربات", 'callback_data' => 'set_text'], 'set_text', $_rx_set_styles)],
    [rx_kb_style(['text' => "📷 تنظیمات کیو آر کد", 'callback_data' => 'set_qrsettings'], 'set_qrsettings', $_rx_set_styles)],
    [rx_kb_style(['text' => "🔗 وبهوک ربات‌های نماینده", 'callback_data' => 'set_webhook'], 'set_webhook', $_rx_set_styles)],
    [
        rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'set_backadmin'], 'set_backadmin', $_rx_set_styles)
    ],
];

$setting_panel = rx_kb_encode($settingPanelRows);

$adminPanelsMenu = rx_kb_encode([
    [
        rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'], 'callback_data' => 'admin_managepanel'], 'admin_managepanel', $_rx_adm_styles),
        rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['addpanel'], 'callback_data' => 'admin_addpanel'], 'admin_addpanel', $_rx_adm_styles)
    ],
    [
        rx_kb_style(['text' => "⏳ قیمت سریع زمان", 'callback_data' => 'admin_timeprice'], 'admin_timeprice', $_rx_adm_styles),
        rx_kb_style(['text' => "🔋 قیمت سریع حجم", 'callback_data' => 'admin_volprice'], 'admin_volprice', $_rx_adm_styles)
    ],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'panelshub_backmenu'], 'panelshub_backmenu', $_rx_adm_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'panelshub_backadmin', $_rx_adm_styles)
    ],
]);

$adminChannelMenu = rx_kb_encode([
    [rx_kb_style(['text' => "📯 تنظیمات کانال", 'callback_data' => 'set_channel'], 'set_channel', $_rx_set_styles)],
    [rx_kb_style(['text' => "📣 گزارشات ربات", 'callback_data' => 'set_reports'], 'set_reports', $_rx_set_styles)],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'channelhub_backmenu'], 'channelhub_backmenu', $_rx_set_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'channelhub_backadmin', $_rx_set_styles)
    ],
]);

$adminUsersMenu = rx_kb_encode([
    [rx_kb_style(['text' => $textbotlang['Admin']['btnkeyboardadmin']['managruser'], 'callback_data' => 'admin_users'], 'admin_users', $_rx_adm_styles)],
    [rx_kb_style(['text' => "👨‍🔧 بخش ادمین", 'callback_data' => 'set_adminmgr'], 'set_adminmgr', $_rx_set_styles)],
    [rx_kb_style(['text' => "💵 رسید های تایید نشده", 'callback_data' => 'admin_invoices'], 'admin_invoices', $_rx_adm_styles)],
    [
        rx_kb_style(['text' => "🔙 بازگشت به منوی قبل", 'callback_data' => 'usershub_backmenu'], 'usershub_backmenu', $_rx_adm_styles),
        rx_kb_style(['text' => "🏠 منوی مدیریت", 'callback_data' => 'adm_hub_main'], 'usershub_backadmin', $_rx_adm_styles)
    ],
]);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingpv = getPaySettingValue("Cartstatuspv");
$usernamecart = getPaySettingValue("CartDirect");
$trnadoo = getPaySettingValue("statustarnado");
$tonpayStatus = getPaySettingValue("statustonpay");
$cubepayStatus = getPaySettingValue("statuscubepay");
$blupalStatus = getPaySettingValue("statusblupal");
$varizaStatus = getPaySettingValue("statusvariza");
$abangatewayStatus = getPaySettingValue("statusabangateway");
$abangatewayUrl = trim((string) getPaySettingValue("urlabangateway"));
$abangatewayKey = trim((string) getPaySettingValue("apiabangateway"));
$atlaspayStatus = getPaySettingValue("statusatlaspay");
$paymentverify = getPaySettingValue("checkpaycartfirst");
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$affilnecurrency = getPaySettingValue("digistatus");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
    ];
   if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
        if($PaySettingpv == "oncardpv"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'url' => "https://t.me/$usernamecart"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }else{
            $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'callback_data' => "cart_to_offline"], 'cart_to_offline', $_rx_pay_styles),
    ];
        }
    }
    if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
   if($PaySettingnow == "onnowpayment"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textnowpayment'] ?: '💵 پرداخت ارزی (Plisio)', 'callback_data' => "plisio"], 'plisio', $_rx_pay_styles)
    ];
    }
    if($payment_status_nowpayment == "1"){
        $step_payment['inline_keyboard'][] = [
    rx_kb_style(['text' => $datatextbot['textsnowpayment'] ?: 'NowPayments', 'callback_data' => "nowpayment"], 'nowpayment', $_rx_pay_styles)
    ];
    }
   if($affilnecurrency == "ondigi"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textnowpaymenttron'] ?: '💵 واریز رمزارز ترون', 'callback_data' => "digitaltron"], 'digitaltron', $_rx_pay_styles)
    ];
    }
   if($trnadoo == "onternado"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['iranpay3'] ?: 'ترونادو', 'callback_data' => "iranpay2"], 'iranpay2', $_rx_pay_styles)
    ];
    }
   if($tonpayStatus == "ontonpay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['tonpay'] ?: '💠 تون‌پی', 'callback_data' => "tonpay"], 'tonpay', $_rx_pay_styles)
    ];
    }
   if($cubepayStatus == "oncubepay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['cubepay'] ?: '🟦 کیوب‌پی', 'callback_data' => "cubepay"], 'cubepay', $_rx_pay_styles)
    ];
    }
   if($blupalStatus == "onblupal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['blupal'] ?: '💙 بلوپال', 'callback_data' => "blupal"], 'blupal', $_rx_pay_styles)
    ];
    }
   if($varizaStatus == "onvariza"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['variza'] ?: '💳 واریزا', 'callback_data' => "variza"], 'variza', $_rx_pay_styles)
    ];
    }
   // Offered only when it can take money: on, with an https address and a key.
   if($abangatewayStatus == "onabangateway" && stripos($abangatewayUrl, 'https://') === 0 && $abangatewayKey !== '' && $abangatewayKey !== '0'){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['abangateway'] ?: '💳 آبان گیت وی', 'callback_data' => "abangateway"], 'abangateway', $_rx_pay_styles)
    ];
    }
   if($atlaspayStatus == "onatlaspay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['atlaspay'] ?: '🌐 اطلس‌پی', 'callback_data' => "atlaspay"], 'atlaspay', $_rx_pay_styles)
    ];
    }
    if($zarinpal == "onzarinpal"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['zarinpal'] ?: '🟡 زرین پال', 'callback_data' => "zarinpal"], 'zarinpal', $_rx_pay_styles)
    ];
    }
    if($paymentstatussnotverify == "onverifypay"){
        $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['textpaymentnotverify'] ?: 'درگاه ریالی', 'callback_data' => "paymentnotverify"], 'paymentnotverify', $_rx_pay_styles)
    ];
    }
    if(intval($paymentsstartelegram) == 1){
     $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => $datatextbot['text_star_telegram'] ?: '💫 Star Telegram', 'callback_data' => "startelegrams"], 'startelegrams', $_rx_pay_styles)
    ];
    }
    $step_payment['inline_keyboard'][] = [
            rx_kb_style(['text' => "❌ بستن لیست", 'callback_data' => "colselist"], 'colselist', $_rx_pay_styles)
    ];
    $step_payment = json_encode($step_payment);
$keyboardhelpadmin = rx_kb_encode([
        [
            rx_kb_style(['text' => "📚 افزودن آموزش", 'callback_data' => 'help_add'], 'help_add', $_rx_helpa_styles),
            rx_kb_style(['text' => "❌ حذف آموزش", 'callback_data' => 'help_del'], 'help_del', $_rx_helpa_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش آموزش", 'callback_data' => 'help_edit'], 'help_edit', $_rx_helpa_styles),
         rx_kb_style(['text' => "📥 دریافت پیش‌فرض‌ها", 'callback_data' => 'help_load_default'], 'help_load_default', $_rx_helpa_styles)],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'help_back'], 'help_back', $_rx_helpa_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'help_backmenu'], 'help_backmenu', $_rx_helpa_styles)
        ]
    ]);
$shopkeyboard = rx_kb_encode([
        [
            rx_kb_style(['text' => "🗂 مدیریت دسته‌بندی", 'callback_data' => 'shop_category'], 'shop_category', $_rx_shp_styles),
            rx_kb_style(['text' => "🛍 مدیریت محصولات", 'callback_data' => 'shop_products'], 'shop_products', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد هدیه", 'callback_data' => 'shop_giftadd'], 'shop_giftadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد هدیه", 'callback_data' => 'shop_giftdel'], 'shop_giftdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "🎁 ساخت کد تخفیف", 'callback_data' => 'shop_discountadd'], 'shop_discountadd', $_rx_shp_styles),
            rx_kb_style(['text' => "❌ حذف کد تخفیف", 'callback_data' => 'shop_discountdel'], 'shop_discountdel', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => "⬇️ کف خرید عمده", 'callback_data' => 'shop_minbulk'], 'shop_minbulk', $_rx_shp_styles),
            rx_kb_style(['text' => "🎁 کش بک تمدید", 'callback_data' => 'shop_renewcb'], 'shop_renewcb', $_rx_shp_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'shop_backadmin'], 'shop_backadmin', $_rx_shp_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'shop_backmenu'], 'shop_backmenu', $_rx_shp_styles)
        ]
    ]);
$keyboard_Category_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛒 افزودن دسته‌بندی", 'callback_data' => 'cat_add'], 'cat_add', $_rx_cat_styles),
            rx_kb_style(['text' => "❌ حذف دسته بندی", 'callback_data' => 'cat_del'], 'cat_del', $_rx_cat_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش دسته بندی", 'callback_data' => 'cat_edit'], 'cat_edit', $_rx_cat_styles)],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'cat_back'], 'cat_back', $_rx_cat_styles)]
    ]);
$keyboard_shop_manage = rx_kb_encode([
        [
            rx_kb_style(['text' => "🛍 افزودن محصول", 'callback_data' => 'shopitem_add'], 'shopitem_add', $_rx_prod_styles),
            rx_kb_style(['text' => "❌ حذف محصول", 'callback_data' => 'shopitem_del'], 'shopitem_del', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "✏️ ویرایش محصول", 'callback_data' => 'shopitem_edit'], 'shopitem_edit', $_rx_prod_styles)],
        [
            rx_kb_style(['text' => "⬆️ افزایش قیمت", 'callback_data' => 'shopitem_priceinc'], 'shopitem_priceinc', $_rx_prod_styles),
            rx_kb_style(['text' => "⬇️ کاهش گروهی قیمت", 'callback_data' => 'shopitem_pricedec'], 'shopitem_pricedec', $_rx_prod_styles)
        ],
        [rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'shopitem_back'], 'shopitem_back', $_rx_prod_styles)]
    ]);
$_rx_rules_btn = rx_kb_style(['text' => "✅ قوانین را می پذیرم", 'callback_data' => "acceptrule"], 'rules_accept', $_rx_nav_styles);
$confrimrolls = json_encode(['inline_keyboard' => [[$_rx_rules_btn]]]);
$request_contact = json_encode([
    'keyboard' => [
        [rx_kb_style(['text' => "☎️ ارسال شماره تلفن", 'request_contact' => true], 'contact_phone', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'contact_back'], 'contact_back', $_rx_nav_styles)]
    ],
    'resize_keyboard' => true
]);
$Feature_status = rx_kb_encode([
        [rx_kb_style(['text' => "⚙️ مشاهده اطلاعات اکانت", 'callback_data' => 'feat_info'], 'feat_info', $_rx_feat_styles)],
        [
            rx_kb_style(['text' => "🧪 اکانت تست", 'callback_data' => 'feat_test'], 'feat_test', $_rx_feat_styles),
            rx_kb_style(['text' => "📚 قابلیت آموزش", 'callback_data' => 'feat_help'], 'feat_help', $_rx_feat_styles)
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'feat_back'], 'feat_back', $_rx_feat_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'feat_backmenu'], 'feat_backmenu', $_rx_feat_styles)
        ]
    ]);
if (!function_exists('rx_get_channel_keyboard')) {
    function rx_get_channel_keyboard($forceInline = null)
    {
        global $pdo, $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [];
        $rows[] = [
            rx_kb_style(['text' => "➕ افزودن کانال", 'callback_data' => 'ch_add'], 'ch_add', $styles)
        ];
        if (isset($pdo) && ($pdo instanceof PDO)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM channels ORDER BY id ASC");
                $stmt->execute();
                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($channels)) {
                    foreach ($channels as $chan) {
                        $chanId = isset($chan['id']) && $chan['id'] !== '' ? $chan['id'] : $chan['link'];
                        $btnTitle = !empty($chan['remark']) ? $chan['remark'] : (!empty($chan['link']) ? $chan['link'] : 'کانال ' . $chanId);
                        $rows[] = [
                            rx_kb_style([
                                'text' => "📢 " . $btnTitle,
                                'callback_data' => "ch_manage_" . $chanId
                            ], 'ch_manage_' . $chanId, $styles)
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log('[rx_get_channel_keyboard] ' . $e->getMessage());
            }
        }
        $rows[] = [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'ch_backmenu'], 'ch_backmenu', $styles)
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_manage_keyboard')) {
    function rx_get_channel_manage_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => "✏️ ویرایش عنوان دکمه", 'callback_data' => "ch_editremark_" . $channelId], 'ch_editremark', $styles),
                rx_kb_style(['text' => "🔗 ویرایش لینک عضویت", 'callback_data' => "ch_editjoin_" . $channelId], 'ch_editjoin', $styles)
            ],
            [
                rx_kb_style(['text' => "🆔 ویرایش آیدی کانال", 'callback_data' => "ch_editlink_" . $channelId], 'ch_editlink', $styles),
                rx_kb_style(['text' => "❌ حذف کانال", 'callback_data' => "ch_del_" . $channelId], 'ch_del', $styles)
            ],
            [
                rx_kb_style(['text' => "🔙 بازگشت به لیست کانال‌ها", 'callback_data' => "set_channel"], 'ch_list', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_edit_back_keyboard')) {
    function rx_get_channel_edit_back_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "ch_manage_" . $channelId], 'ch_manage', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_channel_wizard_back_keyboard')) {
    function rx_get_channel_wizard_back_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'set_channel'], 'ch_list', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

if (!function_exists('rx_get_backadmin_keyboard')) {
    function rx_get_backadmin_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
                rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'ch_backmenu', $styles)
            ]
        ];
        return rx_kb_encode($rows, $forceInline);
    }
}

$channelkeyboard = rx_get_channel_keyboard();
$_rx_back_btn = rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles);
$backuser = json_encode(['inline_keyboard' => [[$_rx_back_btn]]]);
$_rx_genrandom_btn = rx_kb_style(['text' => '🎲 ساخت رندوم خودکار', 'callback_data' => 'gen_random_uname'], 'gen_random_uname', $_rx_nav_styles);
$usernamePromptKb = json_encode(['inline_keyboard' => [[$_rx_genrandom_btn], [$_rx_back_btn]]]);
$backadmin = rx_get_backadmin_keyboard();
$getNameCustomKb = json_encode([
    'keyboard' => [
        [['text' => '🎲 ساخت رندوم خودکار']],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' =>"متن دلخواه را وارد کنید یا روی دکمه ساخت رندوم بزنید"
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> '' ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rxPanelName = trim((string)$row['name_panel']);
        if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel IS NOT NULL AND name_panel <> '' ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rxPanelName = trim((string)$row['name_panel']);
        if ($rxPanelName === '' || $rxPanelName[0] === '{' || $rxPanelName[0] === '[') continue;
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>$row['name_panel'],'callback_data' => 'locationedit_'.$row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"همه پنل ها",'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' =>"▶️ بازگشت به منوی قبل",'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_channels_joins = json_encode($list_channels_join);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']
    ];
    $list_card_remove = json_encode($list_card_remove);
}

    $stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
    $stmt->execute();
    $result = $stmt->fetchAll();
    $table_exists = count($result) > 0;
    if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpkey = [];
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $helpkey[] = [$row['name_os']];
        }
        $help_arrke = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];
        foreach ($helpkey as $button) {
            $help_arrke['keyboard'][] = [
                ['text' => $button[0]]
            ];
        }
                $help_arrke['keyboard'][] = [
            ['text' => $textbotlang['Admin']['backmenu']],
        ];
        $json_list_helpkey = json_encode($help_arrke);
}

    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpcwtgory = ['inline_keyboard' => []];
    $datahelp = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(in_array($result['category'],$datahelp))continue;
        if($result['category'] == null)continue;
        $datahelp[] = $result['category'];
            $helpcwtgory['inline_keyboard'][] = [['text' => $result['category'], 'callback_data' => "helpctgoryـ{$result['category']}"]
            ];
        }
$helpcwtgory['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـcategory = json_encode($helpcwtgory);

    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if ($panelcount > 10) {
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) && in_array($from_id, $rx_h)) continue;
            if ($result['type'] == "Manualsale") {
                $manualStmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $manualStmt->bindParam(':codepanel', $result['code_panel']);
                $manualStmt->execute();
                $configexits = $manualStmt->rowCount();
                if (intval($configexits) == 0) continue;
            }
            if ($users['step'] == "getusernameinfo") {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"];
            } else {
                $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"];
            }
            if (count($temp_row) == 2) {
                $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
                $temp_row = [];
            }
        }
        if (!empty($temp_row)) {
            $list_marzban_panel_users['inline_keyboard'][] = $temp_row;
        }
    } else {
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($result['type'] == "Manualsale") {
                $stmts = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $stmts->bindParam(':codepanel', $result['code_panel']);
                $stmts->execute();
                $configexits = $stmts->rowCount();
                if (intval($configexits) == 0) continue;
            }
            $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) && in_array($from_id, $rx_h)) continue;
            if ($users['step'] == "getusernameinfo") {
                $list_marzban_panel_users['inline_keyboard'][] = [
                    ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['code_panel']}"]
                ];
            } else {
                $list_marzban_panel_users['inline_keyboard'][] = [[
                    'text' => $result['name_panel'],
                    'callback_data' => "location_{$result['code_panel']}"
                ]];
            }
        }
    }
$statusnote = false;
if($setting['statusnamecustom'] == 'onnamecustom')$statusnote = true;
if($setting['statusnoteforf'] == "0" && $users['agent'] == "f")$statusnote = false;
    if($statusnote){
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "buyback"],
];
}else{
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
}
$list_marzban_panel_user = json_encode($list_marzban_panel_users);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->bindParam(':agent', $users['agent']);
    $stmt->execute();
    $list_marzban_panel_users_om = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) and in_array($from_id, $rx_h)) continue;
            $list_marzban_panel_users_om['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_users_om['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_panel_userom = json_encode($list_marzban_panel_users_om);


    $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :ag OR agent = 'all') AND name_panel != :exclude ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->execute([':ag' => (string)($users['agent'] ?? ''), ':exclude' => (string)($users['Processing_value_four'] ?? '')]);
    $list_marzban_panel_users_change = ['inline_keyboard' => []];
    $panelcount = select("marzban_panel","*","status","active","count");
    if($panelcount > 10){
        $temp_row = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true))) continue;

            $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if (count($temp_row) == 2) {
            $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
            $temp_row = [];
        }
    }
if (!empty($temp_row)) {
    $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
}
    }else{
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rx_h = json_decode((string)($result['hide_user'] ?? ''), true); if (is_array($rx_h) and in_array($from_id, $rx_h)) continue;
            $list_marzban_panel_users_change['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"]
            ];
    }
    }
$list_marzban_panel_users_change['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backorder"],
];
$list_marzban_panel_userschange = json_encode($list_marzban_panel_users_change);


    $list_marzban_panel_usertest = ['inline_keyboard' => []];
    $rxTestKbUser = is_array($users ?? null) ? $users : [];
    $rxTestKbUser['id'] = $from_id;
    foreach ((function_exists('rx_test_eligible_panels') ? rx_test_eligible_panels($rxTestKbUser) : []) as $result) {
            $list_marzban_panel_usertest['inline_keyboard'][] = [['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
            ];
    }
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


$textbot = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol","*",null,null,"fetchAll");
    $protocol = [];
    foreach($getdataprotocol as $result)
    {
        $protocol[] = [['text'=>$result['NameProtocol']]];
    }
    $protocol[] = [['text'=>$textbotlang['Admin']['backadmin']]];
    $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
 }

$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:text, Location) > 0 or Location = '/all' ");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom of the product
    // picker. Same fix as the gift-code/discount-code delete lists below.
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_product_list_admin = json_encode($list_product);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row goes LAST so it renders at the bottom of the reply keyboard
    // (matches the convention used by $list_Inbound below + every static
    // admin keyboard). Previously this was inserted before the foreach loop
    // which forced the back row to the top of the gift-code delete list.
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin = json_encode($list_Discount);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value']  , PDO::PARAM_STR);
    $stmt->execute();
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $Inboundkeyboard[] = [$row['NameInbound']];
}

}
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
        $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}

$stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    // Back row appended last so it renders at the bottom (same fix as the
    // gift-code list above — the original code prepended this row before
    // the foreach loop, forcing it to the top of the discount-code delete
    // keyboard).
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
$payment = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => "🎁 ثبت کد تخفیف", 'callback_data' => "aptdc"], 'confirm_discount', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles)]
    ]
]);
$paymentom = json_encode([
    'inline_keyboard' => [
        [rx_kb_style(['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetservice"], 'confirm_pay', $_rx_nav_styles)],
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_acc_styles)]
    ]
]);
$change_product = json_encode([
    'keyboard' => [
        [
            rx_kb_style(['text' => "قیمت"], 'قیمت', $_rx_pedit_styles),
            rx_kb_style(['text' => "حجم"], 'حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "زمان"], 'زمان', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نام محصول"], 'نام محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "نوع کاربری"], 'نوع کاربری', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "نوع ریست حجم"], 'نوع ریست حجم', $_rx_pedit_styles),
            rx_kb_style(['text' => "یادداشت"], 'یادداشت', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "موقعیت محصول"], 'موقعیت محصول', $_rx_pedit_styles),
            rx_kb_style(['text' => "دسته بندی"], 'دسته بندی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "🎛 تنظیم اینباند"], '🎛 تنظیم اینباند', $_rx_pedit_styles),
            rx_kb_style(['text' => "نمایش برای خرید اول"], 'نمایش برای خرید اول', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => "مخفی کردن پنل"], 'مخفی کردن پنل', $_rx_pedit_styles),
            rx_kb_style(['text' => "حذف کلی پنل های مخفی"], 'حذف کلی پنل های مخفی', $_rx_pedit_styles),
        ],
        [
            rx_kb_style(['text' => $textbotlang['Admin']['backadmin']], 'backadmin', $_rx_pedit_styles),
            rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'backmenu', $_rx_pedit_styles),
        ]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"],['text' => "vmess"],['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه کاربر + رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);
$MethodUsernameRemna = json_encode([
    'keyboard' => [
        [['text' => "آیدی عددی"]],
        [['text' => "آیدی عددی + حروف و عدد رندوم"]],
        [['text' => "نام کاربری + حروف و عدد رندوم"]],
        [['text' => "نام کاربری دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه کاربر + رندوم"]],
        [['text' => "متن دلخواه + عدد رندوم"]],
        [['text' => "متن دلخواه + عدد ترتیبی"]],
        [['text' => "نام کاربری + عدد به ترتیب"]],
        [['text' => "آیدی عددی+عدد ترتیبی"]],
        [['text' => "متن دلخواه نماینده + عدد ترتیبی"]],
        [['text' => "نام کاربری دلخواه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
    ],
    'resize_keyboard' => true
]);

$rxAdminPanelStylesAll = [];
if (function_exists('select')) {
    $__rxRow = select('setting', 'keyboard_styles_all', null, null, 'select');
    if (is_array($__rxRow) && !empty($__rxRow['keyboard_styles_all'])) {
        $__rxDecoded = json_decode($__rxRow['keyboard_styles_all'], true);
        if (is_array($__rxDecoded)) { $rxAdminPanelStylesAll = $__rxDecoded; }
    }
    unset($__rxRow, $__rxDecoded);
}










if (!function_exists('rx_adminPanelCallbackMapFile')) {
    function rx_adminPanelCallbackMapFile(): string {
        $base = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__;
        return rtrim($base, '/') . '/rx_admin_panel_callback_map.json';
    }
}
if (!function_exists('rx_makeAdminPanelCallback')) {
    function rx_makeAdminPanelCallback(string $text): string {

        $short = 'apn:' . $text;
        if (strlen($short) <= 64) return $short;


        $code = 'apnh:' . substr(hash('sha256', $text), 0, 32);
        $file = rx_adminPanelCallbackMapFile();
        $map = [];
        if (is_file($file)) {
            $dec = json_decode((string) @file_get_contents($file), true);
            if (is_array($dec)) $map = $dec;
        }
        $map[$code] = $text;
        @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $code;
    }
}
if (!function_exists('rx_resolveAdminPanelCallback')) {
    function rx_resolveAdminPanelCallback(string $callbackData): ?string {
        if (strpos($callbackData, 'apn:') === 0) {
            return substr($callbackData, 4);
        }
        if (strpos($callbackData, 'apnh:') === 0) {
            $file = rx_adminPanelCallbackMapFile();
            if (!is_file($file)) return null;
            $map = json_decode((string) @file_get_contents($file), true);
            return (is_array($map) && isset($map[$callbackData])) ? (string)$map[$callbackData] : null;
        }
        return null;
    }
}







if (!function_exists('rx_finalizeInlineAdminKb')) {
    function rx_finalizeInlineAdminKb(string $json, bool $rxMarkPlain = true): string {
        $kb = json_decode($json, true);
        if (!is_array($kb)) return $json;

        $rows = null;
        if (isset($kb['keyboard']) && is_array($kb['keyboard'])) {
            $rows = $kb['keyboard'];
        } elseif (isset($kb['inline_keyboard']) && is_array($kb['inline_keyboard'])) {
            $rows = $kb['inline_keyboard'];
        }
        if ($rows === null) return $json;

        $newRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn) || !isset($btn['text'])) continue;

                if (!isset($btn['callback_data']) && !isset($btn['url'])
                    && !isset($btn['login_url']) && !isset($btn['switch_inline_query'])
                    && !isset($btn['switch_inline_query_current_chat']) && !isset($btn['web_app'])) {
                    $btn['callback_data'] = rx_makeAdminPanelCallback((string)$btn['text']);
                }

                unset($btn['request_contact'], $btn['request_location'], $btn['request_poll'], $btn['request_users'], $btn['request_chat']);
                if ($rxMarkPlain) { $btn['_rxap'] = 1; } else { $btn['_rxkeep'] = 1; }
                $newRow[] = $btn;
            }
            if (!empty($newRow)) $newRows[] = $newRow;
        }
        return json_encode(['inline_keyboard' => $newRows], JSON_UNESCAPED_UNICODE);
    }
}
$rxAdminPanelBtn = function (string $text, string $menuKey, string $default = 'default') use ($rxAdminPanelStylesAll) {
    $btn = [
        'text' => $text,
        'callback_data' => rx_makeAdminPanelCallback($text),
    ];
    return $btn;
};
$rxFxPricingRow = function (string $menuKey) use ($rxAdminPanelBtn) {
    return [$rxAdminPanelBtn(fx_admin_button_label(), $menuKey)];
};

$optionMarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_marzban', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_marzban'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_marzban'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_marzban'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_marzban')],
        $rxFxPricingRow('admin_panel_marzban'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_marzban'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_marzban'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_marzban')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_marzban', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_guard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_guard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_guard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️ تنظیم سرویس ها", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_guard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_guard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_guard')],
        $rxFxPricingRow('admin_panel_guard'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_guard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_guard'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_guard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_guard')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_guard')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_guard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionRebecca = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_rebecca'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_rebecca', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_rebecca'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("⚙️ سرویس پیش‌فرض", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_rebecca'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_rebecca')],
        $rxFxPricingRow('admin_panel_rebecca'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_rebecca'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_rebecca'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_rebecca')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_rebecca', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionPasarGuard = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_pasarguard', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش کلید", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⚙️ پروتکل اینباند", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_pasarguard'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_pasarguard')],
        $rxFxPricingRow('admin_panel_pasarguard'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_pasarguard'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_pasarguard')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_pasarguard', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionwg = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_wg'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_wg', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_wg'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_wg'), $rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_wg'), $rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_wg'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_wg')],
        $rxFxPricingRow('admin_panel_wg'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_wg'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_wg'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_wg')],
        [$rxAdminPanelBtn("⚙️  اینباند اکانت غیرفعال", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_wg')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_wg')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_wg', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionManualsale = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_manualsale'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_manualsale', 'danger')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_manualsale'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_manualsale'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_manualsale')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_manualsale')],
        $rxFxPricingRow('admin_panel_manualsale'),
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionX_ui_single = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔄 تغییر حالت API", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_x_ui_single', 'danger')],
        [$rxAdminPanelBtn("🔐 ویرایش رمز عبور", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("👤 ویرایش نام", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💎 شناسه اینباند", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⁉️ اتصال به پنل", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_x_ui_single'), $rxAdminPanelBtn('🔗 دامنه لینک ساب', 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_x_ui_single')],
        $rxFxPricingRow('admin_panel_x_ui_single'),
        [$rxAdminPanelBtn("🌍 قیمت تغییر مکان", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_x_ui_single'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("🫣 مخفی پنل برای کاربر", 'admin_panel_x_ui_single')],
        [$rxAdminPanelBtn("❌ حذف از لیست مخفی", 'admin_panel_x_ui_single', 'danger')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
if($setting['statussupportpv'] == "onpvsupport"){
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 پیام پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}else{
$supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                ['text' => "🎟 پیام پشتیبانی", 'callback_data' => "support"],
            ],[
                ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
            ],

        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"],['text' => "Seller"],['text' => "support"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$rxAffiliatesSettingsRow = select("affiliates", "*", null, null, "select");
$rxAffiliatesMenuMode = (is_array($rxAffiliatesSettingsRow) && ($rxAffiliatesSettingsRow['menu_keyboard_mode'] ?? '') === 'inline_menu') ? 'inline_menu' : 'reply_menu';

$affiliatesReplyRows = [
    [['text' => "🖼 مشاهده بنر فعال"]],
    [['text' => "🏞 تنظیم عکس بنر"], ['text' => "✏️ ویرایش متن بنر"]],
    [['text' => "🗑 حذف بنر"], ['text' => "♻️ بازگردانی بنر پیش‌فرض"]],
    [['text' => "🧮 تنظیم درصد زیرمجموعه"], ['text' => "🌟 مبلغ هدیه استارت"]],
    [['text' => "🎁 هدیه استارت"], ['text' => "🎁 پورسانت بعد از خرید"]],
    [['text' => "🎉 پورسانت فقط برای خرید اول"]],
    [['text' => "🛡 حفاظت از سواستفاده"]],
    [['text' => "📊 گزارشات زیرمجموعه"]],
    [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
];

$affiliatesInlineRows = [
    [['text' => "🖼 مشاهده بنر فعال", 'callback_data' => "affiliates_view_banner"]],
    [['text' => "🏞 تنظیم عکس بنر", 'callback_data' => "affiliates_edit_banner_image"], ['text' => "✏️ ویرایش متن بنر", 'callback_data' => "affiliates_edit_banner_text"]],
    [['text' => "🗑 حذف بنر", 'callback_data' => "affiliates_delete_banner"], ['text' => "♻️ بازگردانی بنر پیش‌فرض", 'callback_data' => "affiliates_reset_banner"]],
    [['text' => "🧮 تنظیم درصد زیرمجموعه", 'callback_data' => "affiliates_set_percentage"], ['text' => "🌟 مبلغ هدیه استارت", 'callback_data' => "affiliates_set_start_amount"]],
    [['text' => "🎁 هدیه استارت", 'callback_data' => "affiliates_toggle_start_gift"], ['text' => "🎁 پورسانت بعد از خرید", 'callback_data' => "affiliates_toggle_commission"]],
    [['text' => "🎉 پورسانت فقط برای خرید اول", 'callback_data' => "affiliates_toggle_first_buy_only"]],
    [['text' => "🛡 حفاظت از سواستفاده", 'callback_data' => "affiliates_antifraud_menu"]],
    [['text' => "📊 گزارشات زیرمجموعه", 'callback_data' => "affiliates_reports"]],
    [['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "backmenu"]]
];

$affiliates = $rxAffiliatesMenuMode === 'inline_menu'
    ? json_encode(['inline_keyboard' => $affiliatesInlineRows])
    : json_encode(['keyboard' => $affiliatesReplyRows, 'resize_keyboard' => true]);

$affiliatesAntiFraud = json_encode([
    'keyboard' => [
        [['text' => "☎️ الزام تایید شماره"]],
        [['text' => "⏳ حداقل سن اکانت"]],
        [['text' => "📅 سقف روزانه معرفی"], ['text' => "🗓 سقف ماهانه معرفی"]],
        [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata =  json_encode([
    'keyboard' => [
        [['text' => "خروجی کاربران"],['text' => "خروجی سفارشات"]],
        [['text' => "خروجی گرفتن پرداخت ها"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$helpedit =  json_encode([
    'keyboard' => [
        [['text' =>"ویرایش نام آموزش"],['text' =>"ویرایش توضیحات"]],
        [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
        [['text' => "ویرایش لینک برنامه"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => "ریست حجم و زمان"]],
        [['text' => "اضافه شدن زمان و حجم به ماه بعد"]],
        [['text'=> "ریست زمان و اضافه کردن حجم قبلی"]],
        [['text' => "ریست شدن حجم و اضافه شدن زمان"]],
        [['text' => "اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده"]],
        [['text' => "رزرو اشتراک"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"],['text' => "day"],['text' => "week"]],
        [['text' => "month"],['text' => "year"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "Marzban", 'callback_data' => 'typepanel#marzban'],
            ['text' => "Rebecca", 'callback_data' => 'typepanel#rebecca'],
            ['text' => "PasarGuard" , 'callback_data' => "typepanel#pasargard"]
        ],
        [
            ['text' => "Remnawave", 'callback_data' => 'typepanel#remnawave'],
            ['text' => "Guard", 'callback_data' => 'typepanel#guard'],
            ['text' => '3x-ui', 'callback_data' => 'typepanel#x-ui_single']
        ],
        [
            ['text' => "فروش دستی" , 'callback_data' => 'typepanel#Manualsale'],
            ['text' => '📱 اپ اختصاصی AhvazVPN', 'callback_data' => 'typepanel#app']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);
$keyboardswitchtype = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "Marzban", 'callback_data' => 'switchtype#marzban'],
            ['text' => "Rebecca", 'callback_data' => 'switchtype#rebecca'],
            ['text' => "PasarGuard" , 'callback_data' => "switchtype#pasargard"]
        ],
        [
            ['text' => "Remnawave", 'callback_data' => 'switchtype#remnawave'],
            ['text' => "Guard", 'callback_data' => 'switchtype#guard'],
            ['text' => '3x-ui', 'callback_data' => 'switchtype#x-ui_single']
        ],
        [
            ['text' => $textbotlang['Admin']['backadmin'] , 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel","*","MethodUsername","متن دلخواه نماینده + عدد ترتیبی","count");
if($setting['inlinebtnmain'] == "oninline"){
    $keyboardagent = [
    'inline_keyboard' => [
        [
            ['text' => "🗂 خرید انبوه", 'callback_data' => "kharidanbuh"],
            ['text' => "👤 نام دلخواه", 'callback_data' => "selectname"]
        ],
        [
            ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
        ]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['inline_keyboard'][0][1]);
}
}else{
$keyboardagent = [
    'keyboard' => [
        [['text' => "🗂 خرید انبوه"],['text' => "👤 نام دلخواه"]],
        [['text' => $textbotlang['users']['backbtn']]]
    ],
    'resize_keyboard' => true
];
if($panelechekc == 0){
    unset($keyboardagent['keyboard'][0][1]);
}
}
$keyboardagent = json_encode($keyboardagent);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => "🏷️ نام نمایشی درگاه رمز ارز آفلاین"]],
        [['text' => "⬇️ کف رمزارز آفلاین"],['text' => "⬆️ سقف رمزارز آفلاین"]],
        [['text' => "📚 آموزش  ارزی افلاین"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athmarzban'), $rxAdminPanelBtn("🖥 مدیریت نود ها", 'admin_panel_athmarzban')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$optionathx_ui = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athx_ui')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));
$configedit = json_encode([
    'keyboard' => [
        [['text' => "مخشصات کانفیگ"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => "api  درگاه ارزی ریالی"]],
        [['text' => "🗂 نام درگاه ریالی سوم"]],
        [['text' => "⬇️ کف ریالی سوم"],['text' => "⬆️ سقف ریالی سوم"]],
        [['text' => "💰 کش بک ریالی سوم"]],
        [['text' => "📚 آموزش ریالی سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => "👤 تنظیم آیدی پشتیبانی"]],
        [['text' => "🔼 اضافه کردن دپارتمان"],['text' => "🔽 حذف کردن دپارتمان"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);

$stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$departeman = [];

$departemans = [
    'keyboard' => [],
    'resize_keyboard' => true,
];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departeman[] = [$row['name_departman']];
    }
    foreach ($departeman as $button) {
        $departemans['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
}

$departemans['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backadmin']],
    ['text' => $textbotlang['Admin']['backmenu']]
];

$departemanslist = json_encode($departemans);


$list_departman = ['inline_keyboard' => []];

if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_departman['inline_keyboard'][] = [[
            'text' => $result['name_departman'],
            'callback_data' => "departman_{$result['id']}"
        ]];
    }
}

$list_departman['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_departman = json_encode($list_departman);
$active_panell =  json_encode([
    'keyboard' => [
        [['text' => "📣 گزارشات ربات"]],
    ],
    'resize_keyboard' => true
]);
$lottery =  json_encode([
    'keyboard' => [
        [['text' => "1️⃣ تنظیم جایزه نفر اول"],['text' => "2️⃣ تنظیم جایزه نفر دوم"]],
        [['text' => "3️⃣ تنظیم جایزه نفر سوم"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$wheelkeyboard =  json_encode([
    'keyboard' => [
        [['text' => "🎲 مبلغ برنده شدن کاربر"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);$option_remnawave = rx_finalizeInlineAdminKb(json_encode([
    'keyboard' => [
        [$rxAdminPanelBtn("⚙️ وضعیت قابلیت ها پنل", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🔄 تغییر نوع پنل", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🔄 تست اتصال مجدد", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("✍️ نام پنل", 'admin_panel_remnawave'), $rxAdminPanelBtn("❌ حذف پنل", 'admin_panel_remnawave', 'danger')],
        [$rxAdminPanelBtn("🔗 ویرایش آدرس پنل", 'admin_panel_remnawave'), $rxAdminPanelBtn("🔑 تنظیم توکن API", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🛡 تنظیم شناسه Squad", 'admin_panel_remnawave'), $rxAdminPanelBtn("🔋 روش تمدید سرویس", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🖥 داشبورد نودها", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("💡 ساخت نام کاربری", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 تغییر گروه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🚨 محدودیت اکانت", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🧪 تنظیمات تست", 'admin_panel_remnawave')],
        $rxFxPricingRow('admin_panel_remnawave'),
        [$rxAdminPanelBtn("⚙️ قیمت حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("➕ قیمت حجم اضافه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("⏳ قیمت زمان اضافه", 'admin_panel_remnawave'), $rxAdminPanelBtn("⏳ قیمت زمان دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("📍 کف حجم دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف حجم دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("📍 کف زمان دلخواه", 'admin_panel_remnawave'), $rxAdminPanelBtn("📍 سقف زمان دلخواه", 'admin_panel_remnawave')],
        [$rxAdminPanelBtn("🌐 وضعیت نت ملی", 'admin_panel_remnawave')],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]));

if (!function_exists('rxProductLookupTokens')) {
function rxProductLookupTokens($rawToken)
{
    $tokens = [];
    $addToken = static function ($value) use (&$tokens) {
        if ($value === null) return;
        $value = trim((string) $value);
        if ($value === '') {
            $tokens[''] = '';
            return;
        }
        $decodedHtml = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decodedUrl = rawurldecode($decodedHtml);
        foreach ([$value, $decodedHtml, $decodedUrl] as $candidate) {
            $candidate = trim((string) $candidate);
            foreach (['prodcutservice_', 'prodcutservices_', 'prodcutserviceom_', 'prodcutservicesom_'] as $prefix) {
                if (strpos($candidate, $prefix) === 0) {
                    $candidate = substr($candidate, strlen($prefix));
                    break;
                }
            }
            $tokens[$candidate] = $candidate;
        }
    };

    $addToken($rawToken);
    if (function_exists('rx_resolveInlineButtonText')) {
        $resolved = rx_resolveInlineButtonText((string) $rawToken);
        if ($resolved !== null) {
            $addToken($resolved);
        }
    }

    return array_values($tokens);
}
}

if (!function_exists('rxResolveProductForPanel')) {
function rxResolveProductForPanel($productToken, $panelName, $agent = null, $category = null, $serviceTime = null)
{
    global $pdo;

    if (!($pdo instanceof PDO)) return false;
    $panelName = trim((string) $panelName);
    if ($panelName === '') return false;
    $agent = $agent === null ? null : trim((string) $agent);
    $tokens = rxProductLookupTokens($productToken);

    $queryProduct = static function ($extraSql, array $extraParams = []) use ($pdo, $panelName, $agent, $category, $serviceTime) {
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:rx_location_where, Location) > 0 OR Location = '/all')";
        $params = [
            ':rx_location_where' => $panelName,
            ':rx_location_order' => $panelName,
        ];

        if ($agent !== null && $agent !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':rx_agent_where'] = $agent;
            $params[':rx_agent_order'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_category, category) > 0 OR FIND_IN_SET(:rx_category_id, category) > 0)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }

        $sql .= ' ' . $extraSql;
        $sql .= " ORDER BY CASE WHEN FIND_IN_SET(:rx_location_order, Location) > 0 THEN 0 ELSE 1 END";
        if ($agent !== null && $agent !== '') {
            $sql .= ", CASE WHEN agent = :rx_agent_order THEN 0 WHEN agent = 'all' THEN 1 ELSE 2 END";
        }
        $sql .= ", id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + $extraParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    };

    foreach ($tokens as $token) {
        if (preg_match('/^pid:(\d+)$/', (string) $token, $m)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $m[1]]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByCodeForPanel')) {
            $row = nmProductByCodeForPanel($token, $panelName, $agent);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $row = $queryProduct('AND code_product = :rx_code_product', [':rx_code_product' => $token]);
        if ($row) return $row;
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') continue;
        if (ctype_digit($token)) {
            $row = $queryProduct('AND id = :rx_product_id', [':rx_product_id' => $token]);
            if ($row) return $row;
        }
    }

    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || preg_match('/^pid:\d+$/', $token)) continue;
        if (function_exists('nmProductByNameForPanel')) {
            $row = nmProductByNameForPanel($token, $panelName, $agent, $category);
            if (is_array($row) && isset($row['price_product'])) return $row;
        }
        $cleanName = preg_replace('/\s*-\s*[0-9,\.]+\s*تومان\s*$/u', '', $token);
        foreach (array_unique([$token, trim((string) $cleanName)]) as $nameCandidate) {
            if ($nameCandidate === '') continue;
            $row = $queryProduct('AND name_product = :rx_name_product', [':rx_name_product' => $nameCandidate]);
            if ($row) return $row;
        }
    }

    $nonEmptyTokens = array_filter($tokens, static function ($token) { return trim((string) $token) !== ''; });
    if (empty($nonEmptyTokens)) {
        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:rx_location_where, Location) > 0 OR Location = '/all')";
        $params = [':rx_location_where' => $panelName];
        if ($agent !== null && $agent !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_agent_where, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $params[':rx_agent_where'] = $agent;
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND (FIND_IN_SET(:rx_category, category) > 0 OR FIND_IN_SET(:rx_category_id, category) > 0)";
            $params[':rx_category'] = (string) $category;
            $params[':rx_category_id'] = (string) $category;
        }
        if ($serviceTime !== null && $serviceTime !== '') {
            $sql .= " AND Service_time = :rx_service_time";
            $params[':rx_service_time'] = (string) $serviceTime;
        }
        $sql .= " ORDER BY id ASC LIMIT 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) return $rows[0];
    }

    return false;
}
}

function KeyboardProduct($location,$query,$pricediscount,$datakeyboard,$statuscustom = false,$backuser = "backuser", $valuetow = null,$customvolume = "customsellvolume", $agentFilter = null, $params = [], $excludeHiddenPanels = false){
    global $pdo,$textbotlang,$from_id;
    $product = ['inline_keyboard' => []];
    $statusshowprice = panel_feature_enabled($location, 'showprice') ? "onshowprice" : "offshowprice";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    if($valuetow != null){
            $valuetow = "-$valuetow";
    }else{
            $valuetow = "";
        }
    $productRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    usort($productRows, function ($a, $b) {
        $pa = (int)($a['position'] ?? 0);
        $pb = (int)($b['position'] ?? 0);
        $za = $pa === 0 ? 1 : 0;
        $zb = $pb === 0 ? 1 : 0;
        if ($za !== $zb) return $za - $zb;
        if ($pa !== $pb) return $pa - $pb;
        return (int)($a['id'] ?? 0) - (int)($b['id'] ?? 0);
    });
    $fxPanelRow = select("marzban_panel", "*", "name_panel", $location, "select");
    if (!is_array($fxPanelRow)) $fxPanelRow = [];
    $nmPanelRow = null;
    if (function_exists('nmPanelNationalEnabled') && function_exists('nmStockHasAvailableForProduct')) {
        $nmPanelRow = select("marzban_panel", "*", "name_panel", $location, "select");
        if (!is_array($nmPanelRow) || !nmPanelNationalEnabled($nmPanelRow)) $nmPanelRow = null;
    }
    if ($statusshowprice == "onshowprice") {
        $product['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['sell']['grid_header_name'] ?? '📦 نام سرویس', 'callback_data' => 'noop'],
            ['text' => $textbotlang['users']['sell']['grid_header_time'] ?? '⏳ زمان', 'callback_data' => 'noop'],
            ['text' => $textbotlang['users']['sell']['grid_header_price'] ?? '💵 قیمت', 'callback_data' => 'noop'],
        ];
    }
    foreach ($productRows as $result) {
        if ($agentFilter !== null && (!function_exists('rx_product_allows_agent') || !rx_product_allows_agent($result, $agentFilter))) {
            continue;
        }
        if ($nmPanelRow !== null && !nmStockHasAvailableForProduct($nmPanelRow, $result)) {
            continue;
        }
        $hide_panel = json_decode($result['hide_panel'], true);
        if (!is_array($hide_panel)) {
            if ($hide_panel === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log(sprintf('Invalid hide_panel JSON for product #%s: %s', $result['id'] ?? 'unknown', json_last_error_msg()));
            }
            $hide_panel = [];
        }
        if ($excludeHiddenPanels && in_array((string)$location, array_map('strval', $hide_panel), true)) {
            continue;
        }


        $result = fx_apply_to_product($result, $fxPanelRow);
        if(intval($pricediscount) != 0){
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] -$resultper;
        }
        $result['price_product'] = fx_finalize_amount($result['price_product'], $fxPanelRow, 'product');
        $callbackToken = trim((string)($result['code_product'] ?? ''));
        $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        if ($callbackToken === '' || strlen($callbackData) > 64) {
            $callbackToken = 'pid:' . (string)($result['id'] ?? '');
            $callbackData = "{$datakeyboard}{$callbackToken}{$valuetow}";
        }
        if ($statusshowprice == "onshowprice") {
            $days = trim((string)($result['Service_time'] ?? ''));
            $duration = $days !== '' ? "{$days} روزه" : '-';
            $price = number_format((float)$result['price_product']) . ' تومان';
            $product['inline_keyboard'][] = [
                ['text' => $result['name_product'], 'callback_data' => $callbackData],
                ['text' => $duration, 'callback_data' => $callbackData],
                ['text' => $price, 'callback_data' => $callbackData],
            ];
        } else {
            $product['inline_keyboard'][] = [
                ['text' => $result['name_product'], 'callback_data' => $callbackData],
            ];
        }
    }
    if ($statuscustom)$product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume]];
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser],
    ];
    return json_encode($product);
}
function KeyboardCategory($location,$agent,$backuser = "backuser"){
    global $pdo,$textbotlang;
    $list_category = ['inline_keyboard' => []];
    // Use the shared, collation-safe matcher so the list shows exactly the
    // categories that have a sellable product (matching by remark OR id OR
    // name/title, normalised). The old code joined product.category =
    // category.remark directly, which under mixed collations / id-based values
    // produced an empty list (national-net "no categories" bug).
    if (function_exists('nmSellableCategoryRows')) {
        foreach (nmSellableCategoryRows($location, $agent) as $row) {
            $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id']]];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmts = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND FIND_IN_SET(:category, category) > 0 AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $stmts->bindParam(':location', $location, PDO::PARAM_STR);
            $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
            $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
            $stmts->execute();
            if($stmts->rowCount() == 0)continue;
            $list_category['inline_keyboard'][] = [['text' =>$row['remark'],'callback_data' => "categorynames_".$row['id']]];
        }
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ بازگشت به منوی قبل","callback_data" => $backuser],
    ];
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel,$agent,$callback_data = "producttime_",$callback_data_back = "backuser",$statuscustomvolume = false,$statusbtnextend = false){
    global $pdo,$textbotlang;
    $stmt = $pdo->prepare("SELECT Service_time FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $stmt->execute([
        ':location' => $name_panel,
        ':agent' => $agent
    ]);
    $montheproduct = array_flip(array_flip($stmt->fetchAll(PDO::FETCH_COLUMN)));
    $monthkeyboard = ['inline_keyboard' => []];
    if (in_array("1",$montheproduct)){
        $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1day'], 'callback_data' => "{$callback_data}1"]
                ];
            }
    if (in_array("7",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['7day'], 'callback_data' => "{$callback_data}7"]
                ];
            }
    if (in_array("31",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}31"]
                ];
            }
    if (in_array("30",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['1'], 'callback_data' => "{$callback_data}30"]
                ];
            }
    if (in_array("61",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}61"]
                ];
            }
    if (in_array("60",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['2'], 'callback_data' => "{$callback_data}60"]
                ];
            }
    if (in_array("91",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}91"]
                ];
            }
    if (in_array("90",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['3'], 'callback_data' => "{$callback_data}90"]
                ];
            }
    if (in_array("121",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}121"]
                ];
            }
    if (in_array("120",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['4'], 'callback_data' => "{$callback_data}120"]
                ];
            }
    if (in_array("181",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}181"]
                ];
            }
    if (in_array("180",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['6'], 'callback_data' => "{$callback_data}180"]
                ];
            }
    if (in_array("365",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['365'], 'callback_data' => "{$callback_data}365"]
                ];
            }
    if (in_array("0",$montheproduct)){
                $monthkeyboard['inline_keyboard'][] = [
                    ['text' => $textbotlang['Admin']['month']['unlimited'], 'callback_data' => "{$callback_data}0"]
                ];
            }
    if($statusbtnextend)$monthkeyboard['inline_keyboard'][] = [['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]];
    if ($statuscustomvolume == true)$monthkeyboard['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => "customsellvolume"]];
    $monthkeyboard['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $callback_data_back]
            ];
    return json_encode($monthkeyboard);
}
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => "🏷️ نام نمایشی درگاه استار"]],
        [['text' => "💰 کش بک استار"],['text' => "📚 آموزش استار"]],
        [['text' => "⬇️ کف استار"],['text' => "⬆️ سقف استار"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => "🆓 محدودیت رایگان"],['text' => "↙️ محدودیت کلی"]],
        [['text' => "🔄 ریست محدودیت کل کاربران"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin(){
    global $pdo, $textbotlang, $setting;
    $stmt = $pdo->prepare("SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' => $row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];

    $json = json_encode($list_category);
    if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline" && function_exists('rx_keyboardToInline')) {
        return rx_keyboardToInline($list_category);
    }
    return $json;
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => "API NOWPAYMENT"],['text' => "🔐 IPN Secret nowpayment"]],
        [['text' => "🏷️ نام نمایشی درگاه nowpayment"],['text' => "💰 کش بک nowpayment"]],
        [['text' => "📚 آموزش nowpayment"]],
        [['text' => "⬇️ کف nowpayment"],['text' => "⬆️ سقف nowpayment"]],
        [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
    ],
    'resize_keyboard' => true
]);
$autoconfirm_advanced_keyboard = json_encode([
    'keyboard' => [
        [['text' => "🚫 استثناء کاربران"],['text' => "✅ کاربران معتمد"]],
        [['text' => $textbotlang['Admin']['backadmin']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ استثناء کردن کاربر"],['text' => "❌ حذف کاربر از لیست"]],
        [['text' => "👁 نمایش لیست استثناء"]],
        [['text' => "◀️ بازگشت به تنظیمات پیشرفته"]]
    ],
    'resize_keyboard' => true
]);
$Trust_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => "➕ افزودن به معتمدین"],['text' => "❌ حذف از معتمدین"]],
        [['text' => "👁 نمایش لیست معتمدین"]],
        [['text' => "🔁 وضعیت حالت معتمدین"]],
        [['text' => "◀️ بازگشت به تنظیمات پیشرفته"]]
    ],
    'resize_keyboard' => true
]);
function rebeccaKeyboardProtocolLabel($config){
    $schemeMap = [
        'hysteria2' => 'Hysteria2',
        'ss' => 'Shadowsocks',
        'vless' => 'VLESS',
        'vmess' => 'VMess',
        'trojan' => 'Trojan',
        'tg' => 'MTProto',
        'xuifile' => 'WireGuard',
        'xuiawgfile' => 'AmneziaWG',
        'amneziawg' => 'AmneziaWG',
        'awg' => 'AmneziaWG',
    ];

    if(strpos($config, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $config)){
        if(preg_match('/^Protocol:\s*(.+)$/mi', $config, $protoMatch)){
            return trim($protoMatch[1]);
        }
        if(preg_match('/^\s*(Jc|S1|S2|S3|S4|H1|H2|H3|H4)\s*=/mi', $config)){
            return 'AmneziaWG';
        }
        if(preg_match('/^\s*\[Interface\]/mi', $config)){
            return 'WireGuard';
        }
        return null;
    }

    if(preg_match('#^https?://#i', $config)){
        $path = parse_url(strtok($config, '#'), PHP_URL_PATH) ?: '';
        if(preg_match('#/wg/#', $path)){
            return 'WireGuard';
        }
        if(preg_match('#/ov/#', $path)){
            return 'OpenVPN';
        }
        return null;
    }

    $split_config = explode("://",$config,2);
    if(count($split_config) === 2){
        $scheme = strtolower($split_config[0]);
        if(in_array($scheme, ['wireguard', 'wg', 'vpn', 'awg', 'amneziawg'], true) && function_exists('xui_wg_conf_from_link')){
            $wgInfo = xui_wg_conf_from_link($config);
            if(is_array($wgInfo)){
                return $wgInfo['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
            }
        }
        if($scheme === 'wireguard'){
            return 'WireGuard';
        }
        if(isset($schemeMap[$scheme])){
            return $schemeMap[$scheme];
        }
    }

    return null;
}

function keyboard_config($config_split,$id_invoice,$back_active = true,$page = 0,&$rowCount = null){
    global $textbotlang;
    $rxConfigPageSize = 5;

    $rows = [];
    $config_split = is_array($config_split) ? $config_split : [];
    foreach (array_values($config_split) as $i => $config){
        if(!is_string($config) || $config === ''){
            error_log('Invalid configuration entry encountered while building keyboard');
            continue;
        }

        $protocolLabel = rebeccaKeyboardProtocolLabel($config);
        $protocolButton = ['text' => $protocolLabel !== null ? $protocolLabel : '—', 'callback_data' => "none"];

        if(strpos($config, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $config)){
            $firstLine = trim(explode("\n", $config, 2)[0]);
            $rows[] = [
                ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
                $protocolButton,
                ['text' => $firstLine !== '' ? $firstLine : sprintf('Config %d', $i + 1), 'callback_data' => "none"],
            ];
            continue;
        }

        $split_config = explode("://",$config,2);
        if(count($split_config) !== 2 || strpos($split_config[0], '{') !== false){
            $structuredConfig = json_decode($config, true);
            if (is_array($structuredConfig)) {
                $structuredName = $structuredConfig['name'] ?? ($structuredConfig['server'] ?? sprintf('Config %d', $i + 1));
                $rows[] = [
                    ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
                    $protocolButton,
                    ['text' => urldecode((string) $structuredName), 'callback_data' => "none"],
                ];
                continue;
            }
            error_log('Malformed configuration string: missing scheme separator');
            continue;
        }

        $type_prtocol = $split_config[0];
        $payload = $split_config[1];
        if(isBase64($payload)){
            $decoded = base64_decode($payload, true);
            if($decoded === false){
                error_log('Failed to decode base64 configuration payload');
                continue;
            }
            $payload = $decoded;
        }

        $displayName = '';
        if($type_prtocol == "vmess"){
            $configJson = json_decode($payload, true);
            if(is_array($configJson) && isset($configJson['ps'])){
                $displayName = $configJson['ps'];
            }
        }elseif(in_array($type_prtocol, ["anyconnect","l2tp","ikev2","pptp"], true)){
            $parts = explode("?",$payload,2);
            if(count($parts) >= 1 && $parts[0] !== ''){
                $displayName = $parts[0];
            }
        }else{
            $parts = explode("#",$payload,2);
            if(count($parts) === 2){
                $displayName = $parts[1];
            }
        }

        if($displayName === '' || $displayName === null){
            $displayName = sprintf('Config %d', $i + 1);
        }

        $rows[] = [
            ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i"],
            $protocolButton,
            ['text' => urldecode($displayName), 'callback_data' => "none"],
        ];
    }

    $totalRows = count($rows);
    $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $rxConfigPageSize) : 1;
    $page = max(0, min($page, $totalPages - 1));
    $pageRows = array_slice($rows, $page * $rxConfigPageSize, $rxConfigPageSize);

    $keyboard_config = ['inline_keyboard' => []];
    $keyboard_config['inline_keyboard'][] = [
        ['text' => "⚙️ کانفیگ", 'callback_data' => "none"],
        ['text' => "🔌 پروتکل", 'callback_data' => "none"],
        ['text' => "✏️نام کانفیگ", 'callback_data' => "none"],
        ];
    foreach ($pageRows as $row) {
        $keyboard_config['inline_keyboard'][] = $row;
    }

    if ($totalPages > 1) {
        $navRow = [];
        if ($page > 0) {
            $navRow[] = ['text' => "◀️ قبلی", 'callback_data' => "configpage_{$id_invoice}_" . ($page - 1)];
        }
        $navRow[] = ['text' => sprintf('%d/%d', $page + 1, $totalPages), 'callback_data' => "none"];
        if ($page < $totalPages - 1) {
            $navRow[] = ['text' => "بعدی ▶️", 'callback_data' => "configpage_{$id_invoice}_" . ($page + 1)];
        }
        $keyboard_config['inline_keyboard'][] = $navRow;
    }

    $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "configget_$id_invoice"."_1520"]];
    if($back_active){
    $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"]];
    }
    $rowCount = $totalRows;
    $encoded = json_encode($keyboard_config);
    if ($encoded === false) {
        error_log('keyboard_config json_encode failed: ' . json_last_error_msg());
        $encoded = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? json_encode($keyboard_config, JSON_INVALID_UTF8_SUBSTITUTE) : false;
    }
    return $encoded;
}
function keyboard_config_unavailable_text($dataUserOut, $outcomeOverride = null){
    global $datatextbot;
    $diagnostic = is_array($dataUserOut) && is_array($dataUserOut['links_diagnostic'] ?? null) ? $dataUserOut['links_diagnostic'] : [];
    $outcome = $outcomeOverride !== null ? (string) $outcomeOverride : (string) ($diagnostic['outcome'] ?? 'failed');
    $baseError = $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.";
    if ($outcome === 'empty') {
        $message = "ℹ️ در حال حاضر پنل هیچ کانفیگی برای این سرویس ارائه نمی‌دهد.";
    } elseif ($outcome === 'invalid') {
        $message = $baseError . "\nپاسخ پنل شامل کانفیگ قابل استفاده نبود.";
    } else {
        $message = $baseError;
    }
    return $message;
}
$keyboard_buy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🛍خرید اشتراک", 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboard_stat = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "⏱️ آمار کل", 'callback_data' => 'stat_all_bot'],
                ],[
                    ['text' => "⏱️ یک ساعت اخیر", 'callback_data' => 'hoursago_stat'],
                ],
                [
                    ['text' => "⛅️ امروز", 'callback_data' => 'today_stat'],
                    ['text' => "☀️ دیروز", 'callback_data' => 'yesterday_stat'],
                ],
                [
                    ['text' => "☀️ ماه فعلی ", 'callback_data' => 'month_current_stat'],
                    ['text' => "⛅️ ماه قبل", 'callback_data' => 'month_old_stat'],
                ],
                [
                    ['text' => "🗓 مشاهده آمار در تاریخ مشخص", 'callback_data' => 'view_stat_time'],
                ],
                [
                    ['text' => "❌ بستن", 'callback_data' => 'close_stat'],
                ]
            ]
        ]);
if (isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] == "oninline") {



    $all_vars = get_defined_vars();
    foreach ($all_vars as $varName => $varValue) {
        if (is_string($varValue) && strpos($varValue, '"keyboard"') !== false && strpos($varValue, '"inline_keyboard"') === false) {

            $rxDecodedKb = json_decode($varValue, true);
            if (!is_array($rxDecodedKb) || !isset($rxDecodedKb['keyboard']) || !is_array($rxDecodedKb['keyboard'])) {
                continue;
            }
            $rxHasReplyOnly = false;
            foreach ($rxDecodedKb['keyboard'] as $rxRow) {
                if (!is_array($rxRow)) continue;
                foreach ($rxRow as $rxBtn) {
                    if (is_array($rxBtn) && (
                        isset($rxBtn['request_contact']) || isset($rxBtn['request_location']) ||
                        isset($rxBtn['request_poll']) || isset($rxBtn['request_users']) ||
                        isset($rxBtn['request_chat'])
                    )) { $rxHasReplyOnly = true; break 2; }
                }
            }
            if ($rxHasReplyOnly) continue;
            if (function_exists('rx_keyboardToInline')) {
                $$varName = rx_keyboardToInline($rxDecodedKb);
            }
        }
    }
    unset($all_vars, $rxDecodedKb, $rxRow, $rxBtn, $rxHasReplyOnly);
}
