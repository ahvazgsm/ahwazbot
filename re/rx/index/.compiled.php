<?php

$version = file_get_contents('version');
date_default_timezone_set('Asia/Tehran');
$new_marzban = isset($new_marzban) ? $new_marzban : false;
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');
ini_set('memory_limit', '-1');
if (!defined('FAOXIMA_LAZY_MYSQLI')) {
    define('FAOXIMA_LAZY_MYSQLI', true);
}
require_once 'config.php';
require_once REFACTORED_LEGACY_ROOT . '/lib/WebhookAuth.php';
FaoximaWebhookAuth::enforce((string) ($APIKEY ?? ''));
if (!defined('RX_DEFER_UPDATE_DEDUPE')) {
    define('RX_DEFER_UPDATE_DEDUPE', true);
}
require_once 'botapi.php';
require_once 'jdf.php';
require_once 'function.php';
if (isset($update_id) && isDuplicateUpdate($update_id)) {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}
require_once 'keyboard.php';
require_once 'vendor/autoload.php';
require_once 'panels.php';
require_once 'infocard.php';
$setting = select("setting", "*");
$textbotlang = languagechange('text.json');
if ($is_bot)
    return;
if (isset($update['chat_member'])) {
    $status = $update['chat_member']['new_chat_member']['status'];
    $from_id = $update['chat_member']['new_chat_member']['user']['id'];
    $user = select("user", "id", $from_id);
    $keyboard_channel_left = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📌 عضویت مجدد", 'url' => "https://t.me/{$update['chat_member']['chat']['username']}"],
            ],
        ]
    ]);
    if (in_array($status, ['left', 'kicked', 'restricted'])) {
        sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
        return;
    }
}
if (!in_array($Chat_type, ["private", "supergroup"]))
    return;
if ($Chat_type === "supergroup" && isset($update['message'])) {
    $_rxGroupAdminStep = '';
    if (function_exists('rx_isAdminChat') && rx_isAdminChat($from_id)) {
        $_rxGroupAdminRow = select("user", "step", "id", $from_id, "select", ['cache' => false]);
        $_rxGroupAdminStep = is_array($_rxGroupAdminRow) ? (string) ($_rxGroupAdminRow['step'] ?? '') : '';
    }
    if (!in_array($_rxGroupAdminStep, ['reject-dec'], true)) {
        if (function_exists('rx_replyGroupIdCommand')) {
            rx_replyGroupIdCommand($update, $from_id, $Chat_type);
        }
        return;
    }
}
if (isset($chat_member))
    return;
$first_name = sanitizeUserName($first_name);
if (!is_array($setting)) {
    $rxSettingMissingMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_setting_missing.flag';
    if (!is_file($rxSettingMissingMarker) || (time() - (int) @filemtime($rxSettingMissingMarker)) > 3600) {
        error_log('Settings data is unavailable. Ensure the `setting` table exists and contains records.');
        @touch($rxSettingMissingMarker);
    }
    unset($rxSettingMissingMarker);
    return;
}
$ManagePanel = new ManagePanel();
$keyboard_check = json_decode($setting['keyboardmain'], true);
if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    update("setting", "keyboardmain", $keyboardmain, null, null);
}

if (!checktelegramip())
    die("Unauthorized access");

if (intval($from_id) == 0)
    return;

if (isset($update['message']['successful_payment']) && function_exists('rxStarsHandleSuccessfulPayment') && rxStarsHandleSuccessfulPayment($update)) {
    return;
}

if (!empty($callback_query_id) && function_exists('rx_flushPendingReplyKeyboardCleanup')) {
    rx_flushPendingReplyKeyboardCleanup($from_id);
}

$user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
$isNewUser = !is_array($user);
$otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
if ($isNewUser && $setting['statusnewuser'] == "onnewuser") {
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $newuser = sprintf($textbotlang['Admin']['ManageUser']['newuser'], $first_name, $username, "<a href = \"tg://user?id=$from_id\">$from_id</a>");
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $newuser,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
$date = time();
if ($from_id != 0 && $isNewUser) {
    if ($setting['verifystart'] != "onverify") {
        $valueverify = 1;
    } else {
        $valueverify = 0;
    }
    $randomString = bin2hex(random_bytes(6));
    $initialProcessingValue = '0';
    $initialProcessingValueOne = 'none';
    $initialProcessingValueTow = 'none';
    $initialProcessingValueFour = '0';
    $initialRollStatus = '0';
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron,roll_Status,Processing_value,Processing_value_one,Processing_value_tow,Processing_value_four) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1',:roll_status,:processing_value,:processing_value_one,:processing_value_tow,:processing_value_four)");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':showcard', $setting['showcard']);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->bindParam(':roll_status', $initialRollStatus);
    $stmt->bindParam(':processing_value', $initialProcessingValue);
    $stmt->bindParam(':processing_value_one', $initialProcessingValueOne);
    $stmt->bindParam(':processing_value_tow', $initialProcessingValueTow);
    $stmt->bindParam(':processing_value_four', $initialProcessingValueFour);
    $stmt->execute();
    clearSelectCache('user');
    $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    $isNewUser = !is_array($user);
}
if (!is_array($user)) {
    $user = array();
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'agent' => '',
        'username' => '',
        'limit_usertest' => '',
        'message_count' => '',
        'affiliates' => '',
        'last_message_time' => '',
        'cardpayment' => '',
        'roll_Status' => '',
        'number_username' => '',
        'number' => '',
        'register' => '',
        'codeInvitation' => '',
        'pricediscount' => '',
        'joinchannel' => '',
        'score' => "",
        'limitchangeloc' => ''
    );
} else {
    $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
}
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN", ['cache' => false]);
if (!is_array($admin_ids)) {
    $admin_ids = [];
}
$admin_ids_str = array_map('strval', $admin_ids);

if (!function_exists('rx_forced_miniapp_active')) {
    function rx_forced_miniapp_active($setting, $from_id, $admin_ids_str)
    {
        return is_array($setting)
            && (string)($setting['forced_miniapp_mode'] ?? '0') === '1'
            && !in_array((string)$from_id, $admin_ids_str, true);
    }
}
if (!function_exists('rx_send_forced_miniapp_redirect')) {
    function rx_send_forced_miniapp_redirect($from_id, $message_id, $user, $callback_query_id)
    {
        global $domainhosts;
        $text = '📱 برای استفاده از خدمات ما، مینی‌اپ را باز کنید.';
        if (function_exists('faoxima_textbot_get')) {
            $rxSuggestText = trim((string) faoxima_textbot_get('miniapp_suggest_1', ''));
            if ($rxSuggestText !== '') {
                $text = $rxSuggestText;
            }
        }
        $first = trim((string)($user['first_name'] ?? ''));
        $text = str_replace('{first_name}', $first !== '' ? $first : 'کاربر', $text);
        $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
        $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => '🚀 باز کردن مینی‌اپ', 'web_app' => ['url' => 'https://' . $host . '/app/']]]]], JSON_UNESCAPED_UNICODE) : null;
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'cache_time' => 0,
            ]);
        }
        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }
}
if (rx_forced_miniapp_active($setting, $from_id, $admin_ids_str)) {
    rx_send_forced_miniapp_redirect($from_id, $message_id, $user, $callback_query_id);
    return;
}

if (
    isset($from_id)
    && intval($from_id) !== 0
    && is_array($setting)
    && (string)($setting['antispam_status'] ?? '0') === '1'
    && !in_array((string)$from_id, $admin_ids_str, true)
    && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)
) {
    try {
        $rxAsGateStmt = $pdo->prepare("SELECT antispam_muted_until FROM user WHERE id = :id LIMIT 1");
        $rxAsGateStmt->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsGateStmt->execute();
        $rxAsGateRow = $rxAsGateStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($rxAsGateRow)) {
            $rxAsGateMutedUntil = (int)($rxAsGateRow['antispam_muted_until'] ?? 0);
        } else {
            $rxAsGateMutedUntil = 0;
        }
    } catch (\Throwable $rxAsGateErr) {

        $rxAsGateMutedUntil = (int)($user['antispam_muted_until'] ?? 0);
    }
    if ($rxAsGateMutedUntil > 0 && time() < $rxAsGateMutedUntil) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 0,
                ]);
            } catch (\Throwable $rxAsAckErr) {  }
        }
        return;
    }
}
$datatextbotget = isset($GLOBALS['_rx_textbot_rows']) && is_array($GLOBALS['_rx_textbot_rows'])
    ? $GLOBALS['_rx_textbot_rows']
    : select("textbot", "*", null, null, "fetchAll");
$datatxtbot = array();
$topic_id = select("topicid", "*", null, null, "fetchAll");
$statusnote = false;
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'reportcron')
        $reportcron = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];
    if ($topic['report'] == 'receiptreport')
        $receiptreport = $topic['idreport'];

}
if ($setting['statusnamecustom'] == 'onnamecustom')
    $statusnote = true;
if ($setting['statusnoteforf'] == "0" && $user['agent'] == "f")
    $statusnote = false;
if (!function_exists('createForumTopicIfMissing')) {
    function createForumTopicIfMissing($currentId, $reportKey, $topicName, $channelId)
    {
        $numericId = intval($currentId);
        if ($numericId !== 0) {
            return;
        }

        $channelId = trim((string)$channelId);
        if ($channelId === '' || $channelId === '0') {
            return;
        }

        $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'faoxima_topic_' . md5($channelId . '|' . $reportKey) . '.lock';
        $markerExisted = is_file($marker);
        $markerHandle = @fopen($marker, 'c');
        if ($markerHandle !== false) {
            if (!@flock($markerHandle, LOCK_EX)) {
                @fclose($markerHandle);
                return;
            }
            clearstatcache(true, $marker);
            $lastAttempt = (int) @filemtime($marker);
            if ($markerExisted && $lastAttempt > 0 && (time() - $lastAttempt) < 3600) {
                @flock($markerHandle, LOCK_UN);
                @fclose($markerHandle);
                return;
            }
            @touch($marker);
            @flock($markerHandle, LOCK_UN);
            @fclose($markerHandle);
        }

        $response = telegram('createForumTopic', [
            'chat_id' => $channelId,
            'name' => $topicName
        ]);

        if (!is_array($response) || empty($response['ok'])) {
            $context = is_array($response) ? json_encode($response) : 'empty response';
            error_log("Failed to create forum topic {$reportKey}: {$context}");

            if (is_array($response) && isset($response['error_code']) && in_array($response['error_code'], [400, 403], true)) {
                update("topicid", "idreport", -1, "report", $reportKey);
            }

            return;
        }

        $threadId = $response['result']['message_thread_id'] ?? null;
        if ($threadId !== null) {
            update("topicid", "idreport", $threadId, "report", $reportKey);
        }
    }
}

createForumTopicIfMissing($porsantreport, 'porsantreport', $textbotlang['Admin']['affiliates']['titletopic'], $setting['Channel_Report']);
createForumTopicIfMissing($reportnight, 'reportnight', $textbotlang['Admin']['report']['reportnight'], $setting['Channel_Report']);
createForumTopicIfMissing($reportcron, 'reportcron', $textbotlang['Admin']['report']['reportcron'], $setting['Channel_Report']);
createForumTopicIfMissing($reportbackup, 'backupfile', "🤖 بکاپ ربات نماینده", $setting['Channel_Report']);
createForumTopicIfMissing($receiptreport, 'receiptreport', $textbotlang['Admin']['report']['receiptreport'], $setting['Channel_Report']);
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_roll' => '',
    'text_fq' => '',
    'text_dec_fq' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_channel' => '',
    'text_Tariff_list' => '',
    'text_dec_Tariff_list' => '',
    'text_affiliates' => '',
    'text_pishinvoice' => '',
    'text_account_info' => '',
    'text_service_detail' => '',
    'dyn_errors_verification_failed' => '',
    'dyn_errors_restart_process' => '',
    'dyn_errors_concurrency_insufficient_stock' => '',
    'dyn_errors_panel_connection_error' => '',
    'dyn_errors_button_disabled' => '',
    'dyn_errors_feature_unavailable' => '',
    'dyn_errors_feature_unavailable_short' => '',
    'dyn_errors_feature_currently_unavailable_nodot' => '',
    'dyn_errors_renewal_failed_restart' => '',
    'dyn_errors_renewal_support_error' => '',
    'dyn_errors_purchase_info_incomplete' => '',
    'dyn_errors_panel_not_found' => '',
    'dyn_errors_panel_unavailable_choose_other' => '',
    'dyn_errors_extra_volume_purchase_error' => '',
    'dyn_errors_location_change_limit_reached' => '',
    'dyn_errors_min_max_deposit_amount' => '',
    'dyn_errors_stock_depleted_no_charge' => '',
    'dyn_errors_stock_extend_success' => '',
    'dyn_errors_invalid_subscription_link' => '',
    'dyn_errors_service_found_count' => '',
    'dyn_errors_service_not_found' => '',
    'dyn_errors_view_account_unavailable' => '',
    'dyn_errors_qr_unavailable' => '',
    'dyn_errors_service_deleted' => '',
    'dyn_errors_config_read_error' => '',
    'dyn_errors_panel_unavailable_dot' => '',
    'dyn_errors_config_read_from_panel_error' => '',
    'dyn_errors_usage_stats_failed' => '',
    'dyn_errors_server_list_failed' => '',
    'dyn_errors_not_connected_change_status' => '',
    'dyn_errors_extend_unavailable_panel' => '',
    'dyn_errors_not_connected_extend' => '',
    'dyn_errors_extend_current_plan_unavailable' => '',
    'dyn_errors_extend_restart_process' => '',
    'dyn_errors_exclusive_discount_conflict' => '',
    'dyn_errors_discount_expired' => '',
    'dyn_errors_discount_applied' => '',
    'dyn_errors_link_change_service_disabled' => '',
    'dyn_errors_extra_volume_unavailable_panel' => '',
    'dyn_errors_extra_volume_restart_process' => '',
    'dyn_errors_purchase_failed_restart' => '',
    'dyn_errors_location_change_unavailable' => '',
    'dyn_errors_generic_restart_process' => '',
    'dyn_errors_restart_buy_process_short' => '',
    'dyn_errors_transfer_panel_unavailable' => '',
    'dyn_errors_transfer_unused_config_only' => '',
    'dyn_errors_removal_request_thanks' => '',
    'dyn_errors_extra_time_unavailable_panel' => '',
    'dyn_errors_extra_time_restart_process' => '',
    'dyn_errors_service_removed' => '',
    'dyn_errors_service_removal_unavailable' => '',
    'dyn_errors_removal_reason_prompt' => '',
    'dyn_errors_test_service_unavailable' => '',
    'dyn_errors_min_bulk_purchase_balance' => '',
    'dyn_errors_section_disabled' => '',
    'dyn_errors_discount_code_not_applicable' => '',
    'dyn_errors_stock_low_purchase_blocked' => '',
    'dyn_referral_welcome_alert' => '',
    'dyn_referral_inviter_reward_alert' => '',
    'dyn_referral_commission_credited' => '',
    'dyn_rewards_points_earned' => '',
    'dyn_rewards_cashback_gift' => '',
    'dyn_tickets_select_category' => '',
    'dyn_tickets_select_department' => '',
    'dyn_tickets_ask_subject' => '',
    'dyn_tickets_operation_canceled' => '',
    'dyn_tickets_invalid_subject_text' => '',
    'dyn_tickets_ask_media_new' => '',
    'dyn_tickets_ask_media_reply' => '',
    'dyn_tickets_media_yes' => '',
    'dyn_tickets_media_no' => '',
    'dyn_tickets_cancel' => '',
    'dyn_tickets_ask_media_count' => '',
    'dyn_tickets_ask_media_upload' => '',
    'dyn_tickets_invalid_media_choice' => '',
    'dyn_tickets_invalid_media_type' => '',
    'dyn_tickets_media_received' => '',
    'dyn_tickets_ask_body_text' => '',
    'dyn_tickets_ask_body_text_short' => '',
    'dyn_tickets_not_available' => '',
    'dyn_tickets_reply_recorded' => '',
    'dyn_tickets_invalid_body_text' => '',
    'dyn_tickets_created' => '',
    'dyn_tickets_closed_locked' => '',
    'dyn_tickets_no_media' => '',
    'dyn_tickets_closed_confirm' => '',
    'dyn_wallet_select_amount' => '',
    'dyn_wallet_recheck_canceled' => '',
    'dyn_wallet_amount_not_numeric' => '',
    'dyn_wallet_rate_unavailable' => '',
    'dyn_wallet_calculated_amount_invalid' => '',
    'dyn_wallet_hash_not_found' => '',
    'dyn_wallet_ask_receipt_photo' => '',
    'dyn_wallet_incomplete_info' => '',
    'dyn_wallet_hash_already_used' => '',
    'dyn_wallet_internal_error_retry' => '',
    'dyn_wallet_invalid_charge_amount' => '',
    'dyn_wallet_discount_not_applicable_zero' => '',
    'dyn_wallet_no_active_card' => '',
    'dyn_wallet_select_verified_card' => '',
    'dyn_wallet_card_auth_active_notice' => '',
    'dyn_wallet_queue_too_busy' => '',
    'dyn_wallet_hashchecker_not_loaded' => '',
    'dyn_wallet_no_wallet_address_configured' => '',
    'dyn_wallet_charge_amount_undetectable' => '',
    'dyn_wallet_invoice_not_found' => '',
    'dyn_wallet_invoice_not_pending' => '',
    'dyn_affiliates_extra_section_disabled' => '',
    'dyn_affiliates_extra_not_affiliate_of_anyone' => '',
    'dyn_affiliates_extra_gift_already_received' => '',
    'dyn_affiliates_extra_gift_credited_inviter' => '',
    'dyn_affiliates_extra_gift_activated' => '',
    'dyn_wheel_button_disabled_you' => '',
    'dyn_wheel_no_purchase_users_only' => '',
    'dyn_wheel_price_unavailable' => '',
    'dyn_wallet_prompt_amount_prompt' => '',
    'dyn_wallet_prompt_amount_out_of_range' => '',
    'dyn_wallet_prompt_debt_must_pay_first' => '',
    'dyn_wallet_prompt_has_discount_yes' => '',
    'dyn_wallet_prompt_has_discount_no' => '',
    'dyn_wallet_prompt_ask_has_discount' => '',
    'dyn_wallet_prompt_auth_success' => '',
    'dyn_group_setup_topic_not_active' => '',
    'dyn_group_setup_insufficient_access' => '',
    'dyn_group_setup_chat_id_received' => '',
    'accountwallet' => '',
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => '',
    'crontest' => '',
    'textrequestagent' => '',
    'textpanelagent' => '',
    'text_wheel_luck' => '',
    'text_cart' => '',
    'text_request_agent_dec' => '',
    'carttocart' => '',
    'textnowpayment' => '',
    'textnowpaymenttron' => '',
    'iranpay1' => '',
    'iranpay2' => '',
    'iranpay3' => '',
    'tonpay' => '',
    'cubepay' => '',
    'blupal' => '',
    'variza' => '',
    'abangateway' => '',
    'atlaspay' => '',
    'zarinpal' => '',
    'textsnowpayment' => '',
    'textpaymentnotverify' => "",
    'text_star_telegram' => '',
    'text_extend' => '',
    'text_wgdashboard' => '',
    'text_Discount' => '',
    'miniapp_suggest_1' => '',
);
foreach ($datatxtbot as $item) {
    if (array_key_exists($item['id_text'], $datatextbot) || (is_string($item['text']) && trim($item['text']) !== '')) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
foreach ($datatextbot as $rxTbKey => $rxTbVal) {
    if (strpos((string) $rxTbKey, 'dyn_') === 0 && trim((string) $rxTbVal) === '') {
        unset($datatextbot[$rxTbKey]);
    }
}
$time_Start = jdate('Y/m/d');
$date_start = jdate('H:i:s', time());
$time_string = "📆 $date_start → ⏰ $time_Start";
$varable_start = [
    '{username}' => $username,
    '{first_name}' => $first_name,
    '{last_name}' => $last_name,
    '{time}' => $time_string,
    '{version}' => $version
];
$datatextbot['text_start'] = strtr($datatextbot['text_start'], $varable_start);
if (function_exists('rx_resolveInlineButtonText') && isset($datain) && is_string($datain) && strpos($datain, 'rxb_') === 0) {
    $rxResolvedInlineText = rx_resolveInlineButtonText($datain);
    if ($rxResolvedInlineText !== null && $rxResolvedInlineText !== '') {
        $datain = $rxResolvedInlineText;
        $text = $rxResolvedInlineText;
    }
}

if (
    function_exists('rx_restorePremiumReplyText')
    && isset($setting['inlinebtnmain']) && $setting['inlinebtnmain'] !== 'oninline'
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = rx_restorePremiumReplyText($text);
}

if (
    function_exists('stripReplyStyleEmoji')
    && is_string($text) && $text !== ''
    && empty($datain)
) {
    $text = stripReplyStyleEmoji($text);
}

if (
    is_string($datain) && $datain !== ''
    && function_exists('rx_resolveAdminPanelCallback')
    && (strpos($datain, 'apn:') === 0 || strpos($datain, 'apnh:') === 0)
) {
    $rxResolvedText = rx_resolveAdminPanelCallback($datain);
    if ($rxResolvedText !== null && $rxResolvedText !== '') {

        if (!empty($callback_query_id) && function_exists('telegram')) {
            @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        }
        $text  = $rxResolvedText;
        $datain = '';
    }
}

if (function_exists('rx_ui_resolve_request')) {
    rx_ui_resolve_request();
}
if (class_exists('logNavigation')) {
    logNavigation::button($from_id);
}
if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
if ($user['register'] == "none") {
    update("user", "register", time(), "id", $from_id);
}
if (!in_array($user['agent'], ["n", "n2", "f"]))
    update("user", "agent", "f", "id", $from_id);

if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
    $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}

$timebot = time();
$rxAntispamStatus = (string)($setting['antispam_status'] ?? '0');
if ($rxAntispamStatus === '1' && !in_array((string)$from_id, $admin_ids_str, true) && !in_array((string)($user['agent'] ?? 'f'), ['n', 'n2'], true)) {

    $rxAsMsgCount = (int)($setting['antispam_msg_count'] ?? 5);
    if ($rxAsMsgCount < 1)    { $rxAsMsgCount = 1; }
    if ($rxAsMsgCount > 1000) { $rxAsMsgCount = 1000; }
    $rxAsSeconds = (int)($setting['antispam_seconds'] ?? 3);
    if ($rxAsSeconds < 1)    { $rxAsSeconds = 1; }
    if ($rxAsSeconds > 3600) { $rxAsSeconds = 3600; }
    $rxAsMuteSeconds = (int)($setting['antispam_mute_seconds'] ?? 5);
    if ($rxAsMuteSeconds < 1)     { $rxAsMuteSeconds = 1; }
    if ($rxAsMuteSeconds > 86400) { $rxAsMuteSeconds = 86400; }

    $rxAsShouldDrop = false;
    $rxAsHandledByRedis = false;

    if (function_exists('rx_redis_is_active') && rx_redis_is_active()) {
        try {
            $rxAsLuaScript = "local key = KEYS[1] "
                . "local now = tonumber(ARGV[1]) "
                . "local win_seconds = tonumber(ARGV[2]) "
                . "local msg_count = tonumber(ARGV[3]) "
                . "local mute_seconds = tonumber(ARGV[4]) "
                . "local win_start = tonumber(redis.call('HGET', key, 'window_start') or '0') "
                . "local win_count = tonumber(redis.call('HGET', key, 'window_count') or '0') "
                . "local muted_until = tonumber(redis.call('HGET', key, 'muted_until') or '0') "
                . "local should_drop = 0 "
                . "local sync_needed = 0 "
                . "if muted_until > 0 and now < muted_until then "
                . "  should_drop = 1 "
                . "elseif muted_until > 0 and now >= muted_until then "
                . "  muted_until = 0 win_start = now win_count = 1 sync_needed = 1 "
                . "elseif win_start == 0 or (now - win_start) >= win_seconds then "
                . "  win_start = now win_count = 1 sync_needed = 1 "
                . "else "
                . "  win_count = win_count + 1 "
                . "  if win_count > msg_count then "
                . "    muted_until = now + mute_seconds should_drop = 1 sync_needed = 1 "
                . "  end "
                . "end "
                . "redis.call('HSET', key, 'window_start', win_start, 'window_count', win_count, 'muted_until', muted_until) "
                . "local ttl = win_seconds "
                . "if mute_seconds > ttl then ttl = mute_seconds end "
                . "redis.call('EXPIRE', key, ttl + 5) "
                . "return {win_start, win_count, muted_until, should_drop, sync_needed}";

            $rxAsRedisKey = rx_redis_key('faoxima:antispam:' . $from_id);
            $rxAsRedisClient = getRedisConnection();
            if ($rxAsRedisClient instanceof \Redis) {
                $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, [$rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds], 1);
            } else {
                $rxAsLuaResult = $rxAsRedisClient->eval($rxAsLuaScript, 1, $rxAsRedisKey, $timebot, $rxAsSeconds, $rxAsMsgCount, $rxAsMuteSeconds);
            }

            if (is_array($rxAsLuaResult) && count($rxAsLuaResult) === 5) {
                $rxAsNewWinStart   = (int) $rxAsLuaResult[0];
                $rxAsNewWinCount   = (int) $rxAsLuaResult[1];
                $rxAsNewMutedUntil = (int) $rxAsLuaResult[2];
                $rxAsShouldDrop    = ((int) $rxAsLuaResult[3]) === 1;
                $rxAsSyncNeeded    = ((int) $rxAsLuaResult[4]) === 1;

                $user['antispam_window_start'] = (string) $rxAsNewWinStart;
                $user['antispam_window_count'] = (string) $rxAsNewWinCount;
                $user['antispam_muted_until']  = (string) $rxAsNewMutedUntil;

                if ($rxAsSyncNeeded) {
                    try {
                        $rxAsSyncStmt = $pdo->prepare(
                            "UPDATE user SET "
                            . "antispam_window_start = :ws, "
                            . "antispam_window_count = :wc, "
                            . "antispam_muted_until  = :mu "
                            . "WHERE id = :id"
                        );
                        $rxAsSyncStmt->bindValue(':ws', (string) $rxAsNewWinStart,   PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':wc', (string) $rxAsNewWinCount,   PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':mu', (string) $rxAsNewMutedUntil, PDO::PARAM_STR);
                        $rxAsSyncStmt->bindValue(':id', (string) $from_id,          PDO::PARAM_STR);
                        $rxAsSyncStmt->execute();
                        if (function_exists('clearSelectCacheRow')) {
                            clearSelectCacheRow('user', 'id', (string) $from_id);
                        }
                    } catch (\Throwable $rxAsSyncErr) {
                        error_log('antispam Redis->MySQL sync failed: ' . $rxAsSyncErr->getMessage());
                    }
                }

                $rxAsHandledByRedis = true;
            }
        } catch (\Throwable $rxAsRedisErr) {
            $rxAsHandledByRedis = false;
        }
    }

    if (!$rxAsHandledByRedis) {
    $rxAsAtomicOk   = false;
    $rxAsTxOpened   = false;
    try {
        $pdo->beginTransaction();
        $rxAsTxOpened = true;

        $rxAsSel = $pdo->prepare(
            "SELECT antispam_window_start, antispam_window_count, antispam_muted_until "
            . "FROM user WHERE id = :id FOR UPDATE"
        );
        $rxAsSel->bindValue(':id', (string)$from_id, PDO::PARAM_STR);
        $rxAsSel->execute();
        $rxAsRow = $rxAsSel->fetch(PDO::FETCH_ASSOC);

        if (!is_array($rxAsRow)) {

            $pdo->commit();
            $rxAsTxOpened = false;
            $rxAsAtomicOk = true;
        } else {
            $rxAsWinStart   = (int)($rxAsRow['antispam_window_start'] ?? 0);
            $rxAsWinCount   = (int)($rxAsRow['antispam_window_count'] ?? 0);
            $rxAsMutedUntil = (int)($rxAsRow['antispam_muted_until']  ?? 0);

            $rxAsNewWinStart   = $rxAsWinStart;
            $rxAsNewWinCount   = $rxAsWinCount;
            $rxAsNewMutedUntil = $rxAsMutedUntil;

            if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {

                $rxAsShouldDrop = true;
            } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {

                $rxAsNewMutedUntil = 0;
                $rxAsNewWinStart   = $timebot;
                $rxAsNewWinCount   = 1;
            } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {

                $rxAsNewWinStart = $timebot;
                $rxAsNewWinCount = 1;
            } else {

                $rxAsNewWinCount = $rxAsWinCount + 1;
                if ($rxAsNewWinCount > $rxAsMsgCount) {

                    $rxAsNewMutedUntil = $timebot + $rxAsMuteSeconds;
                    $rxAsShouldDrop    = true;
                }
            }

            $rxAsUpd = $pdo->prepare(
                "UPDATE user SET "
                . "antispam_window_start = :ws, "
                . "antispam_window_count = :wc, "
                . "antispam_muted_until  = :mu "
                . "WHERE id = :id"
            );
            $rxAsUpd->bindValue(':ws', (string)$rxAsNewWinStart,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':wc', (string)$rxAsNewWinCount,   PDO::PARAM_STR);
            $rxAsUpd->bindValue(':mu', (string)$rxAsNewMutedUntil, PDO::PARAM_STR);
            $rxAsUpd->bindValue(':id', (string)$from_id,           PDO::PARAM_STR);
            $rxAsUpd->execute();

            $pdo->commit();
            $rxAsTxOpened = false;

            $user['antispam_window_start'] = (string)$rxAsNewWinStart;
            $user['antispam_window_count'] = (string)$rxAsNewWinCount;
            $user['antispam_muted_until']  = (string)$rxAsNewMutedUntil;

            if (function_exists('clearSelectCacheRow')) {
                clearSelectCacheRow('user', 'id', (string)$from_id);
            } elseif (function_exists('clearSelectCache')) {
                clearSelectCache('user');
            }

            $rxAsAtomicOk = true;
        }
    } catch (\Throwable $rxAsTxErr) {

        if ($rxAsTxOpened) {
            try { $pdo->rollBack(); } catch (\Throwable $rxAsRbErr) {  }
        }
        $rxAsAtomicOk = false;
    }

    if (!$rxAsAtomicOk) {
        $rxAsWinStart   = (int)($user['antispam_window_start']  ?? 0);
        $rxAsWinCount   = (int)($user['antispam_window_count']  ?? 0);
        $rxAsMutedUntil = (int)($user['antispam_muted_until']   ?? 0);

        if ($rxAsMutedUntil > 0 && $timebot < $rxAsMutedUntil) {
            $rxAsShouldDrop = true;
        } elseif ($rxAsMutedUntil > 0 && $timebot >= $rxAsMutedUntil) {
            update("user", "antispam_muted_until",  "0",                "id", $from_id);
            update("user", "antispam_window_start", (string)$timebot,   "id", $from_id);
            update("user", "antispam_window_count", "1",                "id", $from_id);
            $user['antispam_muted_until']  = "0";
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } elseif ($rxAsWinStart === 0 || ($timebot - $rxAsWinStart) >= $rxAsSeconds) {
            update("user", "antispam_window_start", (string)$timebot, "id", $from_id);
            update("user", "antispam_window_count", "1",              "id", $from_id);
            $user['antispam_window_start'] = (string)$timebot;
            $user['antispam_window_count'] = "1";
        } else {
            $rxAsNewCount = $rxAsWinCount + 1;
            update("user", "antispam_window_count", (string)$rxAsNewCount, "id", $from_id);
            $user['antispam_window_count'] = (string)$rxAsNewCount;
            if ($rxAsNewCount > $rxAsMsgCount) {
                $rxAsMuteEnd = $timebot + $rxAsMuteSeconds;
                update("user", "antispam_muted_until", (string)$rxAsMuteEnd, "id", $from_id);
                $user['antispam_muted_until'] = (string)$rxAsMuteEnd;
                $rxAsShouldDrop = true;
            }
        }
    }
    }

    if ($rxAsShouldDrop) {

        if (!empty($callback_query_id)) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 0,
                ]);
            } catch (\Throwable $rxAsAckErr2) {  }
        }
        return;
    }
} else {

    $TimeLastMessage = $timebot - intval($user['last_message_time']);
    if (floor($TimeLastMessage / 60) >= 1) {
        update("user", "last_message_time", $timebot, "id", $from_id);
        update("user", "message_count", "1", "id", $from_id);
    } else {
        if (!in_array($from_id, $admin_ids)) {
            $addmessage = intval($user['message_count']) + 1;
            update("user", "message_count", $addmessage, "id", $from_id);
            $spamThreshold = 35;
            if ($addmessage >= $spamThreshold) {
                $User_Status = "block";
                $textblok = sprintf($textbotlang['users']['spam']['spamedreport'], $from_id);
                $Response = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                        ],
                    ]
                ]);
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $otherservice,
                        'text' => $textblok,
                        'parse_mode' => "HTML",
                        'reply_markup' => $Response
                    ]);
                }
                update("user", "User_Status", $User_Status, "id", $from_id);
                update("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
                sendmessage($from_id, $textbotlang['users']['spam']['spamedmessage'], null, 'html');
                return;
            }
        }
    }
}

$rxStartLanding = false;
if (strpos($text, "/start ") !== false && $user['step'] != "gettextSystemMessage") {
    $affiliatesid = explode(" ", $text)[1];
    if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
        isValidInvitationCode($setting, $from_id, $user['verify']);
        if (is_numeric($affiliatesid) && $setting['affiliatesstatus'] == "offaffiliates") {
            sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
            return;
        }
        if (is_numeric($affiliatesid) && userExists($affiliatesid)) {
            if ($affiliatesid == $from_id) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                return;
            }
            $user = select("user", "*", "id", $from_id, "select", ['cache' => false]);
            if (intval($user['affiliates']) != 0) {
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            }
            $rxReferralState = rxReferralRegister($from_id, $affiliatesid);
            if ($rxReferralState === 'already_referred') {
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            }
            if ($rxReferralState === 'registered') {
                $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
                $rxWelcomeAlertTpl = $datatextbot['dyn_referral_welcome_alert'] ?? "<b>🎉 خوش آمدی!</b>\n\nشما با دعوت <b>@%s</b> وارد ربات شدی و به عنوان زیرمجموعه ثبت شدی ✅\n\nبرای دریافت هدیه عضویت:\n🔘 به منوی <b>زیرمجموعه‌گیری</b> برو\n🔘 دکمه <b>🎁 هدیه عضویت</b> را بزن\n\nبا این کار، هم خودت و هم معرفت هدیه می‌گیرید! 💰";
                sendmessage($from_id, sprintf($rxWelcomeAlertTpl, $useraffiliates['username']), $keyboard, 'html');
                $rxInviterRewardTpl = $datatextbot['dyn_referral_inviter_reward_alert'] ?? "<b>🎉 یک زیرمجموعه جدید!</b>\n\nکاربر <b>@%s</b> با لینک دعوت شما وارد ربات شد ✅\n\nبا خریدهای این کاربر، <b>سهم هدیه شما</b> به حسابت واریز می‌شه 🔥";
                sendmessage($affiliatesid, sprintf($rxInviterRewardTpl, $username), $keyboard, 'html');
                rxReferralGiftNotify(rxReferralGiftPayout($from_id));
            }
        } else {
            if (!is_numeric($affiliatesid) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string)$affiliatesid) && strpos((string)$affiliatesid, 'manageuser_') !== 0) {
                try {
                    $stmtSourceInv = $pdo->prepare("SELECT 1 FROM user WHERE codeInvitation = ? LIMIT 1");
                    $stmtSourceInv->execute([$affiliatesid]);
                    if (!$stmtSourceInv->fetchColumn()) {
                        $stmtSource = $pdo->prepare("UPDATE user SET `source` = ? WHERE id = ? AND (`source` IS NULL OR `source` = '')");
                        $stmtSource->execute([$affiliatesid, $from_id]);
                        if ($stmtSource->rowCount() > 0 && function_exists('clearSelectCache')) {
                            clearSelectCache('user');
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[start_source] ' . $e->getMessage());
                }
            }
            $rxStartLanding = true;
        }
    } else {
        $text = $affiliatesid;
    }
}
if (intval($user['verify']) == 0 && !in_array($from_id, $admin_ids) && $setting['verifystart'] == "onverify" && !rx_auth_skip_user($user)) {
    $textverify = "⚠️ حساب شما احراز هویت نشده است پیام  شما  به ادمین ارسال شده
    در صورت پیگیری  سریع تر می توانید به آیدی زیر پیام دهید
    @{$setting['id_support']}";
    sendmessage($from_id, $textverify, null, 'html');
    return;
}
;

if ($setting['roll_Status'] == "rolleon" && $user['roll_Status'] == 0 && ($text != "✅ قوانین را می پذیرم" and $datain != "acceptrule") && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_roll'], $confrimrolls, 'html');
    return;
}
if (!function_exists('rx_config_deliver_list')) {
    function rx_config_deliver_list($chatId, $messageId, $text, $keyboard)
    {
        $result = Editmessagetext($chatId, $messageId, $text, $keyboard);
        $ok = is_array($result) && !empty($result['ok']);
        if (!$ok && is_array($result) && stripos((string) ($result['description'] ?? ''), 'message is not modified') !== false) {
            return true;
        }
        if (!$ok) {
            $result = sendmessage($chatId, $text, $keyboard, 'html');
            $ok = is_array($result) && !empty($result['ok']);
        }
        if (!$ok) {
            sendmessage($chatId, "❌ نمایش لیست کانفیگ‌ها ناموفق بود. لطفاً دوباره تلاش کنید.", null, 'html');
        }
        return $ok;
    }
}
if ($text == "✅ قوانین را می پذیرم" or $datain == "acceptrule") {
    if ($setting['roll_Status'] == "rolleon" && intval($user['roll_Status']) == 0) {
        $rxStartLanding = true;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Rules'], $keyboard, 'html');
    $confrim = true;
    update("user", "roll_Status", $confrim, "id", $from_id);
}

if ($setting['Bot_Status'] == "botstatusoff" && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_bot_off'], null, 'html');
    return;
}

$isStartRequest = ($text == "/start" || $datain == "start" || $text == "start" || (is_string($text) && strpos($text, "/start ") === 0) || $rxStartLanding);
$isConfirmChannel = ($datain == "confirmchannel");
$shouldCheckChannel = true;

if ($shouldCheckChannel && !in_array($from_id, $admin_ids)) {
    $channels_id = select("channels", "link", null, null, "FETCH_COLUMN", ['cache' => false]);
    if (!empty($channels_id) && is_array($channels_id)) {
        $channels = null;
        $rxChannelCacheApcu = function_exists('apcu_fetch') && function_exists('apcu_store') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
        $rxChannelCacheKey = 'faoxima:joinchannel:' . $from_id . ':' . md5(implode(',', $channels_id));
        if (!$isConfirmChannel && !$isStartRequest && $rxChannelCacheApcu) {
            $rxChannelCacheHit = false;
            $rxChannelCached = apcu_fetch($rxChannelCacheKey, $rxChannelCacheHit);
            if ($rxChannelCacheHit && is_array($rxChannelCached)) {
                $channels = $rxChannelCached;
            }
        }
        $rxChannelCheckFailed = [];
        if ($channels === null) {
            $channels = channel($channels_id, $rxChannelCheckFailed);
            if ($rxChannelCacheApcu && empty($rxChannelCheckFailed)) {
                @apcu_store($rxChannelCacheKey, $channels, 30);
            }
        }
        $rxChannelCheckFailedText = "⚠️ بررسی عضویت شما در کانال در حال حاضر با خطا مواجه شد؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.";
        if ($isConfirmChannel && count($channels) == 0 && !empty($rxChannelCheckFailed)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $rxChannelCheckFailedText,
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }
        if ($isConfirmChannel) {
            if (count($channels) == 0) {
                update("user", "joinchannel", "active", "id", $from_id);
                $user['joinchannel'] = "active";
                deletemessage($from_id, $message_id);
                rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['channel']['confirmed'],
                    'show_alert' => false,
                    'cache_time' => 5,
                ]);
                $partsaffiliates = explode("_", (string)($user['Processing_value_four'] ?? ''));
                if ($partsaffiliates[0] == "affiliates") {
                    $affiliatesid = $partsaffiliates[1] ?? '';
                    if (!userExists($affiliatesid) || $affiliatesid == $from_id || intval($user['affiliates']) != 0) {
                        update("user", "Processing_value_four", "none", "id", $from_id);
                        return;
                    }
                    if (rxReferralRegister($from_id, $affiliatesid) === 'registered') {
                        rxReferralGiftNotify(rxReferralGiftPayout($from_id));
                    }
                    update("user", "Processing_value_four", "none", "id", $from_id);
                }
                return;
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select", ['cache' => false]);
                if (empty($channelremark['remark']) || empty($channelremark['linkjoin'])) {
                    continue;
                }
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            Editmessagetext($from_id, $message_id, $datatextbot['text_channel'], $keyboardchannel);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }

        if (count($channels) != 0) {
            if ($user['joinchannel'] == "active") {
                update("user", "joinchannel", "0", "id", $from_id);
                $user['joinchannel'] = "0";
            }
            if (is_string($text) && strpos($text, "/start ") === 0) {
                $startParts = explode(" ", $text, 2);
                if (!empty($startParts[1])) {
                    update("user", "Processing_value_four", "affiliates_" . trim($startParts[1]), "id", $from_id);
                }
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach ($channels as $channel) {
                $channelremark = select("channels", "*", 'link', $channel, "select", ['cache' => false]);
                if (empty($channelremark['remark']) || empty($channelremark['linkjoin'])) {
                    continue;
                }
                $keyboardchannel['inline_keyboard'][] = [
                    [
                        'text' => "{$channelremark['remark']}",
                        'url' => $channelremark['linkjoin']
                    ],
                ];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            sendmessage($from_id, $datatextbot['text_channel'], $keyboardchannel, 'html');
            return;
        } elseif (!empty($rxChannelCheckFailed)) {
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'cache_time' => 0,
                ]);
            }
            $rxChannelRetryKeyboard = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]]]]);
            sendmessage($from_id, $rxChannelCheckFailedText, $rxChannelRetryKeyboard, 'html');
            return;
        } else {
            if ($user['joinchannel'] != "active") {
                update("user", "joinchannel", "active", "id", $from_id);
                $user['joinchannel'] = "active";
            }
        }
    }
}
if ($text == "/start" || $datain == "start" || $text == "start" || $rxStartLanding) {
    $rxStartResetStmt = $pdo->prepare("UPDATE user SET Processing_value = '0', Processing_value_one = '0', Processing_value_tow = '0', Processing_value_four = '0' WHERE id = ?");
    $rxStartResetStmt->execute([$from_id]);
    if (function_exists('clearSelectCacheRow')) {
        clearSelectCacheRow('user', 'id', $from_id);
    } else {
        clearSelectCache('user');
    }
    step('home', $from_id);
    if (function_exists('removeReplyKeyboardOnStartIfNeeded')) {
        removeReplyKeyboardOnStartIfNeeded($from_id);
    }
    if (!empty($callback_query_id)) {
        @telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'cache_time' => 0,
        ]);
    }
    rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, "html");
    return;
}
if ((!isset($connect) || !($connect instanceof mysqli)) && function_exists('getMysqliConnection')) {
    $connect = getMysqliConnection();
}
if ($text == "version") {
    sendmessage($from_id, $version, null, 'html');
} elseif ($text == $textbotlang['users']['backbtn'] || $datain == "backuser" || $datain == "admin_back") {
    if ($datain == "backuser" || $datain == "admin_back")
        deletemessage($from_id, $message_id);
    $message_id = sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    if (function_exists('rxCancelAbandonedCardPaymentsForUser')) {
        rxCancelAbandonedCardPaymentsForUser($from_id, 'user_back', ['methods' => ['cart to cart']]);
    }
    return;
} elseif ($user['step'] == 'get_number' && in_array($from_id, $admin_ids)) {
} elseif ($user['step'] == 'get_number' && !in_array($from_id, $admin_ids)) {
    if (empty($user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['false'], $request_contact, 'html');
        return;
    }
    if ($contact_id != $from_id) {
        sendmessage($from_id, $textbotlang['users']['number']['Warning'], $request_contact, 'html');
        return;
    }
    if ($setting['iran_number'] == "onAuthenticationiran" && !preg_match("/989[0-9]{9}$/", $user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['erroriran'], $request_contact, 'html');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['number']['active'], json_encode(['inline_keyboard' => [], 'remove_keyboard' => true]), 'html');
    rx_send_banner_message($from_id, 'start', $datatextbot['text_start'], $keyboard, 'html');
    update("user", "number", $user_phone, "id", $from_id);
    if ($setting['verifystart'] == "onverify") {
        update("user", "verify", "1", "id", $from_id);
    }
    step('home', $from_id);
} elseif (($text !== '' && $text == $datatextbot['text_Purchased_services']) || $datain == "backorder" || $datain == "myservicesbtn" || $text == "/services") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_null($invoices) && $setting['NotUser'] == "offnotuser") {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
        return;
    }
    rx_section_media_enter('services', $from_id, $datain, $message_id, ['myservicesbtn'], ['myservicesbtn']);

    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'], $setting['statusnamecustom'] == 'onnamecustom');
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        ['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder" || $datain == "myservicesbtn") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json, 'html');
    }


} elseif ($datain == 'next_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'], $setting['statusnamecustom'] == 'onnamecustom');
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == 'previous_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = '$from_id' AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin') ORDER BY time_sell DESC LIMIT $previous_page, $items_per_page");
    $stmt->execute();
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'], $setting['statusnamecustom'] == 'onnamecustom');
    if ($setting['statusnamecustom'] == 'onnamecustom') {
        foreach ($serviceRows as $row) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        foreach ($serviceRows as $row) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . rxServiceListStatusSuffix($row) . "✨",
                    'callback_data' => "quickview_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['search']['title'], 'callback_data' => 'searchservice']];
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);

} elseif ($datain == "notusernameme") {
    sendmessage($from_id, $textbotlang['users']['stateus']['SendUsername'], $backuser, 'html');
    step('getusernameinfo', $from_id);
} elseif ($user['step'] == "getusernameinfo") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (empty($text))
        return;
    $usernameconfig = "";
    if (strlen($text) > 32) {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = outputlunksub($text);
        if (!isset($date)) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $date = json_decode($date, true);
        if (!isset($date['username'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_invalid_subscription_link'] ?? "❌ لینک اشتراک نامعتبر است", $backuser, 'HTML');
            return;
        }
        $usernameconfig = $date['username'];
    } else {
        $text = str_replace('_', '-', $text);
        if (!preg_match('/^[A-Za-z0-9-]{3,32}$/', $text)) {
            sendmessage($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
            return;
        }
        $usernameconfig = $text;
    }
    update("user", "Processing_value", $usernameconfig, "id", $from_id);
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_user, 'html');
    step('getdata', $from_id);
} elseif (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1]);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        if ($DataUserOut['msg'] == "User not found") {
            sendmessage($from_id, $textbotlang['users']['stateus']['notUsernameget'], $keyboard, 'html');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }

    $status = $DataUserOut['status'];
    $bExp = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp > 0 && $bExp <= time()) {
        $status = 'expired';
    } elseif ($bDl > 0.0 && $bUt >= $bDl) {
        $status = 'limited';
    }
    $status_map = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $keyboardinfo = [
        'inline_keyboard' => [
            [
                ['text' => $DataUserOut['username'], 'callback_data' => "username"],
                ['text' => $textbotlang['users']['stateus']['username'], 'callback_data' => 'username'],
            ],
            [
                ['text' => $status_var, 'callback_data' => 'status_var', '_rxsk' => 'Status'],
                ['text' => $textbotlang['users']['stateus']['stateus'], 'callback_data' => 'status_var', '_rxsk' => 'Status'],
            ],
            [
                ['text' => $expirationDate, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['stateus']['expirationDate'], 'callback_data' => 'expirationDate'],
            ],
            [],
            [
                ['text' => $day, 'callback_data' => 'روز', '_rxsk' => 'daysleft'],
                ['text' => $textbotlang['users']['stateus']['daysleft'], 'callback_data' => 'day', '_rxsk' => 'daysleft'],
            ],
            [
                ['text' => $LastTraffic, 'callback_data' => 'LastTraffic'],
                ['text' => $textbotlang['users']['stateus']['LastTraffic'], 'callback_data' => 'LastTraffic'],
            ],
            [
                ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic'],
                ['text' => $textbotlang['users']['stateus']['usedTrafficGb'], 'callback_data' => 'expirationDate', '_rxsk' => 'usedtraffic'],
            ],
            [
                ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume'],
                ['text' => $textbotlang['users']['stateus']['RemainingVolume'], 'callback_data' => 'RemainingVolume'],
            ]
        ]
    ];
    $marzbanstatusextra = panel_feature_enabled($marzban_list_get, 'extravolume') ? "onextra" : "offextra";
    if ($marzbanstatusextra == "onextra") {
        $keyboardinfo['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]],
            ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volumes_' . $DataUserOut['username'] . '_' . $dataget[1]],
        ];
    } else {
        $keyboardinfo['inline_keyboard'][] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]]];
    }
    $keyboardinfo = json_encode($keyboardinfo);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['info'], $keyboardinfo);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
    step('home', $from_id);
} elseif (function_exists('nmMaybeHandleStockCallback') && nmMaybeHandleStockCallback($datain ?? '', $from_id, $message_id ?? null, $callback_query_id ?? null)) {
    return;
} elseif (preg_match('/^quickview_(\w+)/', $datain, $dataget) || preg_match('/^product_(\w+)/', $datain, $dataget) || preg_match('/updateproduct_(\w+)/', $datain, $dataget) || $user['step'] == "getuseragnetservice" || $datain == "productcheckdata") {

    if (is_string($datain) && strpos($datain, 'quickview_') === 0) {
        $id_invoice_qv = $dataget[1];
        $stmtQv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :i AND id_user = :u LIMIT 1");
        $stmtQv->execute([':i' => $id_invoice_qv, ':u' => $from_id]);
        $nameloc_qv = $stmtQv->fetch(PDO::FETCH_ASSOC);
        if (!is_array($nameloc_qv)) {
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ سرویس پیدا نشد', 'show_alert' => true]);
            }
            return;
        }
        if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, null, $nameloc_qv)) {
            if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            step('home', $from_id);
            return;
        }
        if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc_qv, $from_id, null)) {
            if (isset($callback_query_id)) telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            step('home', $from_id);
            return;
        }
        $panel_qv = select("marzban_panel", "*", "name_panel", $nameloc_qv['Service_location'], "select");
        $cardPath_qv = null;
        if (is_array($panel_qv) && function_exists('nm_renderInfoCardForInvoice')) {
            $cardPath_qv = nm_renderInfoCardForInvoice($panel_qv, $nameloc_qv['username'], $nameloc_qv['id_invoice'], $from_id);
        }
        if ($cardPath_qv !== null && is_file($cardPath_qv)) {
            $note_qv = isset($nameloc_qv['note']) && $nameloc_qv['note'] !== '' ? ' | ' . $nameloc_qv['note'] : '';
            $caption_qv = '✨ <b>' . htmlspecialchars((string)$nameloc_qv['username'], ENT_QUOTES, 'UTF-8') . '</b>'
                        . htmlspecialchars($note_qv, ENT_QUOTES, 'UTF-8');
            $__qvRow = [['text' => '🔧 مدیریت سرویس', 'callback_data' => 'product_' . $nameloc_qv['id_invoice']]];
            if (!(function_exists('isQrDisabled') && isQrDisabled())) {
                $__qvRow[] = ['text' => faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $nameloc_qv['id_invoice']];
            }
            $kb_qv = json_encode([
                'inline_keyboard' => [
                    $__qvRow,
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'] ?? '🔙 بازگشت', 'callback_data' => 'backorder'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo'   => new CURLFile($cardPath_qv),
                'caption' => $caption_qv,
                'parse_mode' => 'HTML',
                'reply_markup' => $kb_qv,
            ]);
            @unlink($cardPath_qv);
            if (isset($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            }
            step('home', $from_id);
            return;
        }

        $datain = "product_" . $id_invoice_qv;
        $dataget = [$datain, $id_invoice_qv];
    }

    if ($user['step'] == "getuseragnetservice") {
        $username = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND id_user = :id_user AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold' OR status = 'disabled' OR status = 'disablebyadmin')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Service_time', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Volume', $username, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $username, PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain == "productcheckdata") {
        $username = $user['Processing_value'];
        $sql = "SELECT * FROM invoice WHERE username = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain[0] == "u") {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "♻️ اطلاعات بروز شد",
            'show_alert' => false,
            'cache_time' => 0,
        ));
    } else {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    }
    if ($user['step'] == "getuseragnetservice" && $stmt->rowCount() > 1) {
        $countservice = $stmt->rowCount();
        $pages = 1;
        update("user", "pagenumber", $pages, "id", $from_id);
        $page = 1;
        $items_per_page = 20;
        $start_index = ($page - 1) * $items_per_page;
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'], $setting['statusnamecustom'] == 'onnamecustom');
        if ($setting['statusnamecustom'] == 'onnamecustom') {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = "";
                if ($row != null)
                    $data = " | {$row['note']}";
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . rxServiceListStatusSuffix($row) . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        } else {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $keyboardlists['inline_keyboard'][] = [
                    [
                        'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . rxServiceListStatusSuffix($row) . "✨",
                        'callback_data' => "quickview_" . $row['id_invoice']
                    ],
                ];
            }
        }
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        $backuser = [
            rx_kb_style(['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
        ];
        if ($setting['NotUser'] == "onnotuser") {
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
        }
        $keyboardlists['inline_keyboard'][] = $backuser;
        $keyboard_json = json_encode($keyboardlists);
        $rxSvcFoundTpl = $datatextbot['dyn_errors_service_found_count'] ?? "🛍 %s عدد سرویس یافت برای مشاهده و مدیریت سرویس روی یکی از سرویس ها کلیک کنید";
        sendmessage($from_id, sprintf($rxSvcFoundTpl, $countservice), $keyboard_json, 'html');
        step("home", $from_id);
        return;
    }
    $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($nameloc)) {
        sendmessage($from_id, $datatextbot['dyn_errors_service_not_found'] ?? "❌ سرویس مورد نظر پیدا نشد.", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $username = $nameloc['id_invoice'];
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_view_account_unavailable'] ?? "❌ امکان مشاهده اطلاعات اکانت درحال حاضر وجود ندارد", $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    $marzban = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($marzban) && isset($marzban['name_panel']) && $marzban['name_panel'] != null) {
        update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (is_array($DataUserOut) && isset($DataUserOut['status']) && $DataUserOut['status'] !== 'Unsuccessful') {
        try {
            update("invoice", "user_info", json_encode($DataUserOut, JSON_UNESCAPED_UNICODE), "id_invoice", $nameloc['id_invoice']);
        } catch (Throwable $e) {
        }
        $bSt = strtolower((string)$DataUserOut['status']);
        if ($bSt === 'expired' && $nameloc['Status'] !== 'end_of_time') {
            update("invoice", "Status", "end_of_time", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'end_of_time';
        } elseif ($bSt === 'limited' && $nameloc['Status'] !== 'end_of_volume') {
            update("invoice", "Status", "end_of_volume", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'end_of_volume';
        } elseif ($bSt === 'on_hold' && $nameloc['Status'] !== 'send_on_hold') {
            update("invoice", "Status", "send_on_hold", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'send_on_hold';
        } elseif ($bSt === 'disabled' && !in_array($nameloc['Status'], ['disabled', 'disablebyadmin'], true)) {
            update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'disabled';
        } elseif ($bSt === 'active' && !in_array($nameloc['Status'], ['active', 'sendedwarn'], true)) {
            update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
            $nameloc['Status'] = 'active';
        }
    }
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        if (empty($nameloc['invalidated_at'])) {
            update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
        }
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['UserNotFound'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "Panel Not Found") {
            if (empty($nameloc['invalidated_at'])) {
                update("invoice", "invalidated_at", time(), "id_invoice", $nameloc['id_invoice']);
            }
            update("invoice", "Status", "Unsuccessful", "id_invoice", $nameloc['id_invoice']);
        }
        $keyboard_remove = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['deleteFromListBtn'], 'callback_data' => 'deletelist-' . $nameloc['id_invoice']]
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']
                ]
            ]
        ];
        $keyboard_remove = json_encode($keyboard_remove);
        $msg = $textbotlang['users']['stateus']['panelNotConnected'] . "\n\n" . $textbotlang['users']['stateus']['deleteSuggestion'];
        sendmessage($from_id, $msg, $keyboard_remove, 'html');
        step('home', $from_id);
        return;
    }
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $bExp2 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl2 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt2 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp2 > 0 && $bExp2 <= time()) {
        $status = 'expired';
    } elseif ($bDl2 > 0.0 && $bUt2 >= $bDl2) {
        $status = 'limited';
    }
    $status_map2 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map2[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    if ($timeDiff < 0) {
        $day = 0;
    } else {
        $day = "";
        $timemonth = floor($timeDiff / 2592000);
        if ($timemonth > 0) {
            $day .= $timemonth . $textbotlang['users']['stateus']['month'];
            $timeDiffday = $timeDiff - (2592000 * $timemonth);
        } else {
            $timeDiffday = $timeDiff;
        }
        $timereminday = floor($timeDiffday / 86400);
        if ($timereminday > 0) {
            $day .= $timereminday . $textbotlang['users']['stateus']['day'];
        }
        $timehoures = intval(($timeDiffday - ($timereminday * 86400)) / 3600);
        if ($timehoures > 0) {
            $day .= $timehoures . $textbotlang['users']['stateus']['hour'];
        }
        $timehoursall = $timeDiffday - ($timereminday * 86400);
        $timehoursall = $timehoursall - ($timehoures * 3600);
        $timeminuts = intval($timehoursall / 60);
        if ($timeminuts > 0) {
            $day .= $timeminuts . $textbotlang['users']['stateus']['min'];
        }
        $day .= " دیگر";
    }

    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }

    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
            ]
        ]
    ]);
    $userpassword = "";
    if ($marzban['type'] == "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        $manualHasConfig = !empty($manualFileItems);
        if (!$manualHasConfig) {
            foreach ($manualDelivery['configs'] as $manualCfgItem) {
                if (is_string($manualCfgItem) && trim($manualCfgItem) !== '') {
                    $manualHasConfig = true;
                    break;
                }
            }
        }
        $manualHasSub = trim((string) $manualDelivery['sub']) !== '';
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
نام کاربری سرویس : {$DataUserOut['username']}
📎 کد پیگیری سرویس : {$nameloc['id_invoice']}

📅 تاریخ اتمام : $expirationDate ($day)";
        $manualStatusExtend  = ($marzban['status_extend'] ?? 'on_extend') != "off_extend";
        $manualRefundEnabled = panel_feature_enabled($nameloc['Service_location'], 'refund');
        $manualRows = [];
        if ($manualHasConfig) {
            $manualRows[] = [
                ['text' => "🔐 دریافت کانفیگ", 'callback_data' => 'config_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualHasSub) {
            $manualRows[] = [
                ['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => 'subscriptionurl_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualStatusExtend) {
            $manualRows[] = [
                ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $nameloc['id_invoice']],
            ];
        }
        if ($manualRefundEnabled) {
            $manualRows[] = [
                ['text' => $textbotlang['users']['stateus']['removeservice'], 'callback_data' => 'removeserviceuser_' . $nameloc['id_invoice']],
            ];
        }
        $manualRows[] = [
            ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
        ];
        $keyboardsettingManual = json_encode(['inline_keyboard' => $manualRows], JSON_UNESCAPED_UNICODE);
        if ($user['step'] == "getuseragnetservice") {
            sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
        } elseif ($datain == "productcheckdata") {
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textinfo, $keyboardsettingManual, 'html');
        } else {
            Editmessagetext($from_id, $message_id, $textinfo, $keyboardsettingManual);
        }
        return;
    }
    $nameconfig = "";
    if ($nameloc['note'] != null) {
        $nameconfig = "✍️ یادداشت کانفیگ : {$nameloc['note']}";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
    $stmt->execute([
        ':username' => $nameloc['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "*", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $nameloc['name_product'] = $codeproduct['name_product'];
                $nameloc['Volume'] = $codeproduct['Volume_constraint'];
                $nameloc['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }

    $statustimeextra = panel_feature_enabled($nameloc['Service_location'], 'timeextra') ? "ontimeextraa" : "offtimeextraa";
    $marzbanstatusextra = panel_feature_enabled($nameloc['Service_location'], 'extravolume') ? "onextra" : "offextra";
    $statusdisorder = panel_feature_enabled($nameloc['Service_location'], 'disorder') ? "ondisorder" : "offdisorder";
    $statuschangeservice = panel_feature_enabled($nameloc['Service_location'], 'changeservice') ? "onstatus" : "offstatus";
    $statusshowconfig = panel_feature_enabled($nameloc['Service_location'], 'configbtn') ? "onconfig" : "offconfig";
    $statusremoveserveice = panel_feature_enabled($nameloc['Service_location'], 'refund') ? "on" : "off";
    if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
        $textinfo = "وضعیت سرویس : <b>$status_var</b>
👤 نام کاربری سرویس : <code>{$DataUserOut['username']}</code>
🌍 موقعیت سرویس :{$nameloc['Service_location']}
نام محصول :{$nameloc['name_product']}

📶 اخرین زمان اتصال شما : $lastonline

🔋 ترافیک : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 تاریخ اتمام :  $expirationDate ($day)

$nameconfig";

        $keyboardsetting = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username],
                    ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username],
                ],
                [
                    ['text' => "❌ حذف سرویس", 'callback_data' => 'removeauto-' . $username],
                    ['text' => $textbotlang['users']['Extra_time']['title'], 'callback_data' => 'Extra_time_' . $username],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'],
                ]
            ]
        ];
        if ($statustimeextra == "offtimeextraa")
            unset($keyboardsetting['inline_keyboard'][1][1]);
        if ($marzbanstatusextra == "offextra")
            unset($keyboardsetting['inline_keyboard'][0][1]);
        $keyboardsetting['inline_keyboard'] = array_values($keyboardsetting['inline_keyboard']);
        $keyboardsetting = json_encode($keyboardsetting);
    } else {
        $marzbancount = select("marzban_panel", "*", "status", "active", "count");
        if ($DataUserOut['status'] == "active") {
            $namestatus = '❌ خاموش کردن اکانت';
        } else {
            $namestatus = '💡 روشن کردن اکانت';
        }
        $keyboarddate = array(
            'updateinfo' => array(
                'text' => "♻️ بروزرسانی اطلاعات",
                'callback_data' => "updateproduct_"
            ),
            'linksub' => array(
                'text' => $textbotlang['users']['stateus']['linksub'],
                'callback_data' => "subscriptionurl_"
            ),
            'config' => array(
                'text' => $textbotlang['users']['stateus']['config'],
                'callback_data' => "config_"
            ),
            'extend' => array(
                'text' => $textbotlang['users']['extend']['title'],
                'callback_data' => "extend_"
            ),
            'changelink' => array(
                'text' => $textbotlang['users']['changelink']['btntitle'],
                'callback_data' => "changelink_"
            ),
            'removeservice' => array(
                'text' => $textbotlang['users']['stateus']['removeservice'],
                'callback_data' => "removeserviceuser_"
            ),
            'changenameconfig' => array(
                'text' => '📝 تغییر یادداشت',
                'callback_data' => "changenote_"
            ),
            'Extra_volume' => array(
                'text' => $textbotlang['users']['Extra_volume']['sellextra'],
                'callback_data' => "Extra_volume_"
            ),
            'Extra_time' => array(
                'text' => $textbotlang['users']['Extra_time']['title'],
                'callback_data' => "Extra_time_"
            ),
            'changestatus' => array(
                'text' => $namestatus,
                'callback_data' => "changestatus_"
            ),
            'transfor' => array(
                'text' => $textbotlang['Admin']['transfor']['title'],
                'callback_data' => "transfer_"
            ),
            'change-location' => array(
                'text' => $textbotlang['Admin']['change-location']['title'],
                'callback_data' => "changeloc_"
            ),
            'ekhtelal' => array(
                'text' => "⚠️ ارسال گزارش اختلال",
                'callback_data' => "disorder-"
            )
        );
        if ($nameloc['name_product'] == "سرویس تست") {
            unset($keyboarddate['transfor']);
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['removeservice']);
        }
        if ($marzban['type'] == "eylanpanel") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['type'] == "WGDashboard") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changestatus']);
            unset($keyboarddate['change-location']);
            unset($keyboarddate['changelink']);
        }
        if ($marzban['type'] == "remnawave") {
            if (($marzban['remna_revoke'] ?? 'off') !== "on") {
                unset($keyboarddate['changelink']);
            }
            unset($keyboarddate['transfor']);
            if (($marzban['changeloc'] ?? '') !== "onchangeloc") {
                unset($keyboarddate['change-location']);
            }
            if (($marzban['sublink'] ?? '') !== "onsublink") {
                unset($keyboarddate['linksub']);
            }
            if (($marzban['config'] ?? '') !== "onconfig") {
                unset($keyboarddate['config']);
            }
            if (($marzban['remna_usage_btn'] ?? 'on') === "on") {
                $keyboarddate['remna_usage'] = ['text' => '📊 مصرف به‌تفکیک سرور', 'callback_data' => 'remnausage_'];
            }
            if (($marzban['remna_server_picker'] ?? 'off') === "on") {
                $keyboarddate['remna_picker'] = ['text' => '🌍 انتخاب سرور', 'callback_data' => 'remnapicker_'];
            }
        }
        if ($marzban['status_extend'] == "off_extend") {
            unset($keyboarddate['Extra_time']);
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['extend']);
        }
        if ($statusremoveserveice == "off")
            unset($keyboarddate['removeservice']);
        if ($statusshowconfig == "offconfig")
            unset($keyboarddate['config']);
        if ($statusdisorder == "offdisorder")
            unset($keyboarddate['ekhtelal']);
        if ($nameloc['Service_time'] == "0")
            unset($keyboarddate['Extra_time']);
        if ($nameloc['Volume'] == "0") {
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['Extra_time']);
        }
        if ($statuschangeservice == "offstatus")
            unset($keyboarddate['changestatus']);
        if ($setting['statusnamecustom'] == 'offnamecustom')
            unset($keyboarddate['changenameconfig']);
        if ($marzbancount == 1)
            unset($keyboarddate['change-location']);
        if ($marzban['changeloc'] == "offchangeloc")
            unset($keyboarddate['change-location']);
        if ($statustimeextra == "offtimeextraa")
            unset($keyboarddate['Extra_time']);
        if ($marzbanstatusextra == "offextra")
            unset($keyboarddate['Extra_volume']);
        $_rx_svc_styles = [];
        if (!empty($setting['keyboard_styles_all'])) {
            $_rx_all_kbd_s = json_decode($setting['keyboard_styles_all'], true);
            if (is_array($_rx_all_kbd_s) && !empty($_rx_all_kbd_s['service'])) {
                $_rx_svc_styles = $_rx_all_kbd_s['service'];
            }
        }
        if (function_exists('rx_getKeyboardDefaultStyles') && (!function_exists('rx_kb_use_defaults') || rx_kb_use_defaults())) {
            $_rx_svc_styles = $_rx_svc_styles + rx_getKeyboardDefaultStyles('service');
        }
        $tempArray = [];
        $keyboardsetting = ['inline_keyboard' => []];
        foreach ($keyboarddate as $_rx_svc_key => $keyboardtext) {
            $_rx_svc_btn = ['text' => $keyboardtext['text'], 'callback_data' => $keyboardtext['callback_data'] . $username];
            if (!empty($_rx_svc_styles[$_rx_svc_key]) && $_rx_svc_styles[$_rx_svc_key] !== 'default') {
                $_rx_svc_btn['style'] = $_rx_svc_styles[$_rx_svc_key];
            }
            $tempArray[] = $_rx_svc_btn;
            if (count($tempArray) == 2 or $keyboardtext['text'] == "♻️ بروزرسانی اطلاعات") {
                $keyboardsetting['inline_keyboard'][] = $tempArray;
                $tempArray = [];
            }
        }
        if (count($tempArray) > 0) {
            $keyboardsetting['inline_keyboard'][] = $tempArray;
        }
        $_rx_back_btn = ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder'];
        if (!empty($_rx_svc_styles['backorder']) && $_rx_svc_styles['backorder'] !== 'default') {
            $_rx_back_btn['style'] = $_rx_svc_styles['backorder'];
        }
        $keyboardsetting['inline_keyboard'][] = [$_rx_back_btn];
        $keyboardsetting = json_encode($keyboardsetting);
        $subLastUserAgent = trim((string)($DataUserOut['sub_last_user_agent'] ?? ''));
        $subClientLine = $subLastUserAgent !== '' && strcasecmp($subLastUserAgent, 'Faoxima-Mini/1.0') !== 0
            ? "\n#️⃣ کلاینت متصل شده :<code>{$subLastUserAgent}</code>"
            : '';
        if ($DataUserOut['sub_updated_at'] !== null) {
            $textconnect = "
📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate{$subClientLine}";
        } elseif ($marzban['type'] == "WGDashboard") {
            $textconnect = "";
        } else {
            $textconnect = "📶 اخرین زمان اتصال شما : $lastonline";
        }
        $rwChangelinkHint = (($marzban['type'] ?? '') != "remnawave") ? "💡 برای قطع دسترسی دیگران کافیست روی گزینه \"تغییر لینک\" کلیک کنید." : "";
        $textinfo_tpl = $datatextbot['text_service_detail'] ?? "🗂 نام سرویس : {product}
📊وضعیت سرویس : {status}
🌍 موقعیت سرویس :{location}
👤 شناسه سرویس : <code>{username}</code>

🔋 ترافیک : {traffic}
📥 حجم مصرفی : {used}
💢 حجم باقی مانده : {remaining} ({percent}%)

📅 تاریخ اتمام : {expire_date} ({days_left})";
        $textinfo_replacements = [
            '{status}' => $status_var,
            '{username}' => $DataUserOut['username'],
            '{location}' => $nameloc['Service_location'],
            '{product}' => $nameloc['name_product'],
            '{traffic}' => $LastTraffic,
            '{used}' => $usedTrafficGb,
            '{remaining}' => $RemainingVolume,
            '{percent}' => $Percent,
            '{expire_date}' => $expirationDate,
            '{days_left}' => $day,
        ];
        $textinfo = strtr($textinfo_tpl, $textinfo_replacements);
        $textinfo = "$textinfo\n$userpassword\n$nameconfig\n\n$textconnect\n\n$rwChangelinkHint";
        $rxReservationSummary = function_exists('rxReservationSummaryForService') ? rxReservationSummaryForService((string) $nameloc['username'], (string) $nameloc['Service_location'], (string) $nameloc['id_invoice']) : null;
        if (is_array($rxReservationSummary)) {
            $textinfo .= "\n\n" . htmlspecialchars((string) $rxReservationSummary['text'], ENT_QUOTES, 'UTF-8');
        }
    }
    if (($marzban['type'] ?? '') == "remnawave") {
        $rwLight = !empty($DataUserOut['is_online']) ? '🟢 آنلاین' : '🔴 آفلاین';
        $textinfo .= "\n📶 وضعیت اتصال: $rwLight";
        if (!empty($DataUserOut['node_name'])) {
            $textinfo .= "\n🌍 سرور فعال: " . $DataUserOut['node_name'];
        }
    }
    $symbolicLimitLabel = faoxima_symbolic_limit_label($nameloc);
    if ($symbolicLimitLabel !== null) {
        $textinfo .= "\n👥 تعداد کاربر مجاز: $symbolicLimitLabel";
    } elseif (($marzban['type'] ?? '') == "x-ui_single") {
        $xuiIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
        if ($xuiIpGuardActive) {
            $xuiIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
            $xuiIpLimitText = $xuiIpLimitConfigured > 0 ? "{$xuiIpLimitConfigured} کاربر" : 'نامحدود';
            $textinfo .= "\n👥 تعداد کاربر مجاز: $xuiIpLimitText";
        }
        if (function_exists('xui_panel_uses_token') && xui_panel_uses_token($marzban)) {
            $xuiOnlineCheck = xui_client_is_online($marzban, $DataUserOut['username'] ?? '');
            if (!empty($xuiOnlineCheck['status'])) {
                $textinfo .= "\n📶 وضعیت اتصال: " . (!empty($xuiOnlineCheck['online']) ? '🟢 آنلاین' : '⚪ آفلاین');
            }
            if ($xuiIpGuardActive) {
                $xuiIpSummary = xui_client_ip_summary($marzban, $DataUserOut['username'] ?? '');
                if (!empty($xuiIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $xuiIpSummary = faoxima_cap_ip_summary($xuiIpSummary, $xuiIpLimitConfigured);
                }
                if (!empty($xuiIpSummary['status'])) {
                    $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($xuiIpSummary['count']);
                    if (!empty($xuiIpSummary['raw'])) {
                        foreach (array_slice($xuiIpSummary['raw'], 0, 5) as $xuiRawIp) {
                            $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($xuiRawIp, ENT_QUOTES, 'UTF-8');
                        }
                    }
                } else {
                    $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                }
            }
            $xuiHwidLimitConfigured = intval($nameloc['hwid_limit'] ?? 0);
            if ($xuiHwidLimitConfigured > 0 && function_exists('xui_client_hwid_devices')) {
                $xuiHwidResponse = xui_client_hwid_devices($marzban, $DataUserOut['username'] ?? '');
                if (!empty($xuiHwidResponse['status'])) {
                    $xuiHwidList = is_array($xuiHwidResponse['devices'] ?? null) ? $xuiHwidResponse['devices'] : [];
                    $textinfo .= "\n👥 دستگاه‌های متصل: " . count($xuiHwidList) . " از {$xuiHwidLimitConfigured} دستگاه";
                    foreach (array_slice($xuiHwidList, 0, 5) as $xuiHwidEntry) {
                        $xuiDeviceOs = (string) ($xuiHwidEntry['deviceOs'] ?? '');
                        $xuiDeviceModel = (string) ($xuiHwidEntry['deviceModel'] ?? '');
                        $xuiDeviceLabel = $xuiDeviceOs !== '' ? ($xuiDeviceModel !== '' ? "{$xuiDeviceOs} ({$xuiDeviceModel})" : $xuiDeviceOs) : 'نامشخص';
                        $xuiLastSeenRaw = intval($xuiHwidEntry['lastSeen'] ?? 0);
                        $xuiLastSeenText = '';
                        if ($xuiLastSeenRaw > 0) {
                            try {
                                $xuiLastSeenDt = new DateTime('@' . intval($xuiLastSeenRaw / 1000));
                                $xuiLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                                $xuiLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $xuiLastSeenDt->getTimestamp());
                            } catch (Throwable $e) {
                            }
                        }
                        $textinfo .= "\n📱 دستگاه: {$xuiDeviceLabel}{$xuiLastSeenText}";
                    }
                } else {
                    $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$xuiHwidLimitConfigured} دستگاه";
                }
            }
        }
    } elseif (($marzban['type'] ?? '') == "rebecca") {
        $rebeccaIpGuardActive = (($marzban['ip_limit_guard'] ?? '') === 'onipguard');
        if ($rebeccaIpGuardActive) {
            $rebeccaIpLimitConfigured = intval($nameloc['ip_limit'] ?? 0);
            $rebeccaIpLimitText = $rebeccaIpLimitConfigured > 0 ? "{$rebeccaIpLimitConfigured} کاربر" : 'نامحدود';
            $textinfo .= "\n👥 تعداد کاربر مجاز: $rebeccaIpLimitText";
            $rebeccaInsightsAllowed = !function_exists('rebeccaAccessInsightsPermissionStatus')
                || (rebeccaAccessInsightsPermissionStatus($marzban['name_panel'])['status'] ?? false) === true;
            if ($rebeccaInsightsAllowed && function_exists('rebeccaClientIpSummary')) {
                $rebeccaIpSummary = rebeccaClientIpSummary($marzban['name_panel'], $DataUserOut['username'] ?? '');
                if (!empty($rebeccaIpSummary['status']) && function_exists('faoxima_cap_ip_summary')) {
                    $rebeccaIpSummary = faoxima_cap_ip_summary($rebeccaIpSummary, $rebeccaIpLimitConfigured);
                }
                if (!empty($rebeccaIpSummary['status'])) {
                    $textinfo .= "\n🌐 تعداد IP مشاهده‌شده: " . intval($rebeccaIpSummary['count']);
                    if (!empty($rebeccaIpSummary['raw'])) {
                        foreach (array_slice($rebeccaIpSummary['raw'], 0, 5) as $rebeccaRawIp) {
                            $textinfo .= "\n📍 IP متصل: " . htmlspecialchars($rebeccaRawIp, ENT_QUOTES, 'UTF-8');
                        }
                    }
                } else {
                    $textinfo .= "\n🌐 اطلاعات IP متصل در حال حاضر در دسترس نیست.";
                }
            }
        }
    } elseif (($marzban['type'] ?? '') == "pasarguard") {
        $pasarguardHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
        if ($pasarguardHwidLimitConfigured > 0 && function_exists('pasarguardGetUserHwids')) {
            $pasarguardHwidResponse = pasarguardGetUserHwids($DataUserOut['username'] ?? $nameloc['username'], $marzban['name_panel']);
            if (empty($pasarguardHwidResponse['error']) && !empty($pasarguardHwidResponse['body'])) {
                $pasarguardHwidData = json_decode($pasarguardHwidResponse['body'], true);
                $pasarguardHwidList = is_array($pasarguardHwidData['hwids'] ?? null) ? $pasarguardHwidData['hwids'] : [];
                $textinfo .= "\n👥 دستگاه‌های متصل: " . count($pasarguardHwidList) . " از {$pasarguardHwidLimitConfigured} دستگاه";
                foreach (array_slice($pasarguardHwidList, 0, 5) as $pasarguardHwidEntry) {
                    $pasarguardDeviceOs = (string) ($pasarguardHwidEntry['device_os'] ?? '');
                    $pasarguardDeviceModel = (string) ($pasarguardHwidEntry['device_model'] ?? '');
                    $pasarguardDeviceLabel = $pasarguardDeviceOs !== '' ? ($pasarguardDeviceModel !== '' ? "{$pasarguardDeviceOs} ({$pasarguardDeviceModel})" : $pasarguardDeviceOs) : 'نامشخص';
                    $pasarguardLastUsedRaw = (string) ($pasarguardHwidEntry['last_used_at'] ?? '');
                    $pasarguardLastSeenText = '';
                    if ($pasarguardLastUsedRaw !== '') {
                        try {
                            $pasarguardLastSeenDt = new DateTime($pasarguardLastUsedRaw, new DateTimeZone('UTC'));
                            $pasarguardLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                            $pasarguardLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $pasarguardLastSeenDt->getTimestamp());
                        } catch (Throwable $e) {
                        }
                    }
                    $textinfo .= "\n📱 دستگاه: {$pasarguardDeviceLabel}{$pasarguardLastSeenText}";
                }
            } else {
                $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$pasarguardHwidLimitConfigured} دستگاه";
            }
        }
    } elseif (($marzban['type'] ?? '') == "remnawave") {
        $remnaHwidLimitConfigured = intval($DataUserOut['hwid_limit'] ?? 0);
        if ($remnaHwidLimitConfigured > 0 && function_exists('remnawave_get_user_hwid_devices')) {
            $remnaHwidResponse = remnawave_get_user_hwid_devices($marzban['name_panel'], $DataUserOut['username'] ?? $nameloc['username']);
            if (($remnaHwidResponse['status'] ?? '') === 'successful') {
                $remnaHwidList = is_array($remnaHwidResponse['devices'] ?? null) ? $remnaHwidResponse['devices'] : [];
                $textinfo .= "\n👥 دستگاه‌های متصل: " . count($remnaHwidList) . " از {$remnaHwidLimitConfigured} دستگاه";
                foreach (array_slice($remnaHwidList, 0, 5) as $remnaHwidEntry) {
                    $remnaDevicePlatform = (string) ($remnaHwidEntry['platform'] ?? '');
                    $remnaDeviceModel = (string) ($remnaHwidEntry['deviceModel'] ?? '');
                    $remnaDeviceLabel = $remnaDevicePlatform !== '' ? ($remnaDeviceModel !== '' ? "{$remnaDevicePlatform} ({$remnaDeviceModel})" : $remnaDevicePlatform) : 'نامشخص';
                    $remnaLastUsedRaw = (string) ($remnaHwidEntry['updatedAt'] ?? '');
                    $remnaLastSeenText = '';
                    if ($remnaLastUsedRaw !== '') {
                        try {
                            $remnaLastSeenDt = new DateTime($remnaLastUsedRaw, new DateTimeZone('UTC'));
                            $remnaLastSeenDt->setTimezone(new DateTimeZone('Asia/Tehran'));
                            $remnaLastSeenText = ' — آخرین اتصال: ' . jdate('Y/m/d H:i:s', $remnaLastSeenDt->getTimestamp());
                        } catch (Throwable $e) {
                        }
                    }
                    $textinfo .= "\n📱 دستگاه: {$remnaDeviceLabel}{$remnaLastSeenText}";
                }
            } else {
                $textinfo .= "\n👥 دستگاه‌های متصل: 0 از {$remnaHwidLimitConfigured} دستگاه";
            }
        }
    }
    if ($user['step'] == "getuseragnetservice") {
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } elseif ($datain == "productcheckdata") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
    } else {
        Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^infocard_qr_(\w+)$/', $datain, $dataget)) {

    if (function_exists('isQrDisabled') && isQrDisabled()) {
        if (isset($callback_query_id)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
        }
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler infocard_qr on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'infocard_qr',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || empty($DataUserOut['subscription_url'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_qr_unavailable'] ?? "❌ امکان دریافت QR Code در حال حاضر وجود ندارد.", null, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));
    $urlimage = "$from_id$randomString.png";
    $qrCode = createqrcode($subscriptionurl);
    if ($qrCode === null) {
        sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از دکمهٔ کپی/متن بالا استفاده کنید.", null, 'HTML');
        sendmessage($from_id, "<code>{$subscriptionurl}</code>", rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک'), 'HTML');
        return;
    }
    file_put_contents($urlimage, $qrCode->getString());
    addBackgroundImage($urlimage, $qrCode, 'images.jpg');
    $subQrPayload = [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => "<code>{$subscriptionurl}</code>",
        'parse_mode' => "HTML",
    ];
    $subQrCopyKb = rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک');
    if ($subQrCopyKb !== null) {
        $subQrPayload['reply_markup'] = $subQrCopyKb;
    }
    telegram('sendphoto', $subQrPayload);
    unlink($urlimage);
    if (isset($callback_query_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ QR Code ارسال شد',
            'show_alert' => false,
            'cache_time' => 0,
        ]);
    }
} elseif (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/sub ") !== false)) {
    if (is_string($text) && $text !== '' && $text[0] == "/") {
        $id_invoice = explode(' ', $text)[1];
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    }
    if ($nameloc == false)
        return;
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (($marzban_list_get['type'] ?? '') == "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        if (trim((string) $manualDelivery['sub']) === '') {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            return;
        }
        $manualBackRow = [[
            'text' => $textbotlang['users']['stateus']['backinfo'],
            'callback_data' => "productcheckdata",
        ]];
        $bakinfos = rx_copy_button_keyboard($manualDelivery['sub'], '📋 کپی لینک اشتراک', [$manualBackRow]);
        if ($bakinfos === null) {
            $bakinfos = json_encode(['inline_keyboard' => [$manualBackRow]]);
        }
        update("user", "Processing_value", $nameloc['username'], "id", $from_id);
        sendmessage($from_id, "
{$textbotlang['users']['stateus']['linksub']}

<code>{$manualDelivery['sub']}</code>", $bakinfos, 'HTML');
        return;
    }
    $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    if ($marzban_list_get['type'] == "WGDashboard") {
        $textsub = "qrcode اشتراک شما";
    } else {
        $textsub = "
{$textbotlang['users']['stateus']['linksub']}

<code>$subscriptionurl</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    update("user", "Processing_value", $nameloc['username'], "id", $from_id);
    $subscriptionurl = $DataUserOut['subscription_url'];
    $randomString = bin2hex(random_bytes(3));
    $subBackRow = [[
        'text' => $textbotlang['users']['stateus']['backinfo'],
        'callback_data' => "productcheckdata",
    ]];
    $subCopyKb = rx_copy_button_keyboard($subscriptionurl, '📋 کپی لینک اشتراک', [$subBackRow]);
    if ($subCopyKb !== null) {
        $bakinfos = $subCopyKb;
    }

    if ($marzban_list_get['type'] == "WGDashboard") {
        sendmessage($from_id, $textsub, $bakinfos, 'HTML');
        $urlimage = normalizeConfigFilename("{$marzban_list_get['inboundid']}-{$nameloc['username']}.conf", $marzban_list_get['namecustom'] ?? '', true);
        file_put_contents($urlimage, $DataUserOut['subscription_url']);
        sendDocument($from_id, $urlimage, "⚙️ کانفیگ شما");
        unlink($urlimage);
    } elseif ((!function_exists('isQrDisabled') || !isQrDisabled())
        && !(function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get))
        && trim((string) $subscriptionurl) !== '') {
        $qrCode = createqrcode($subscriptionurl);
        if ($qrCode === null) {
            sendmessage($from_id, $textsub, $bakinfos, 'HTML');
            sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این لینک ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
            return;
        }
        $urlimage = "{$from_id}{$randomString}.png";
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => $textsub,
            'parse_mode' => "HTML",
            'reply_markup' => $bakinfos,
        ]);
        unlink($urlimage);
    } else {
        sendmessage($from_id, $textsub, $bakinfos, 'HTML');
    }
} elseif (preg_match('/removeauto-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeauto on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeauto',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    update('invoice', 'status', 'removebyuser', 'id_invoice', $id_invoice);
    $tetremove = "ادمین عزیز یک کاربر سرویس خود را پس از پایان حجم یا زمان حدف کرده است
نام کاربری کانفیک : {$nameloc['username']}";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $tetremove,
            'parse_mode' => "HTML"
        ]);
    }
    sendmessage($from_id, $datatextbot['dyn_errors_service_deleted'] ?? "📌 سرویس با موفقیت حذف شد", null, 'html');
} elseif (preg_match('/^configpage_(.*)_(\d+)$/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $page = (int) $dataget[2];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configpage on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configpage',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || !is_array($DataUserOut['links'] ?? null)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_dot'] ?? "❌ پنل در دسترس نیست.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    if (count($DataUserOut['links']) === 0) {
        sendmessage($from_id, keyboard_config_unavailable_text($DataUserOut), null, 'html');
        return;
    }
    $configKeyboardRows = 0;
    $configKeyboard = keyboard_config($DataUserOut['links'], $id_invoice, true, $page, $configKeyboardRows);
    if ($configKeyboardRows === 0 || !is_string($configKeyboard)) {
        sendmessage($from_id, keyboard_config_unavailable_text($DataUserOut, 'invalid'), null, 'html');
        return;
    }
    rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", $configKeyboard);
} elseif (preg_match('/config_(\w+)/', $datain, $dataget) || (is_string($text) && strpos($text, "/link ") !== false)) {
    $textCommand = is_string($text) ? $text : '';
    if ($textCommand !== '' && $textCommand[0] === "/") {
        $parts = explode(' ', $textCommand, 2);
        $id_invoice = $parts[1] ?? null;
        if ($id_invoice === null) {
            sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler config on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'config',
                ]);
            }
            $nameloc = false;
        }
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $_configPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_configPanelChk) && ($_configPanelChk['type'] ?? '') === "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        if (!empty($manualFileItems)) {
            $manualBackKb = json_encode(['inline_keyboard' => [[
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$nameloc['id_invoice']}"],
            ]]], JSON_UNESCAPED_UNICODE);
            $sentFiles = rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', $manualBackKb);
            if (!$sentFiles) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            }
            return;
        }
        $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
            return is_string($item) && trim($item) !== '';
        }));
        if (empty($manualConfigs)) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
            return;
        }
        rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", keyboard_config($manualConfigs, $nameloc['id_invoice']));
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if (!is_array($DataUserOut['links'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_config_read_error'] ?? "❌  خطا در خواندن اطلاعات کانفیگ با پشتیبانی در ارتباط باشید.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    if (count($DataUserOut['links']) === 0) {
        sendmessage($from_id, keyboard_config_unavailable_text($DataUserOut), null, 'html');
        return;
    }
    $configKeyboardRows = 0;
    $configKeyboard = keyboard_config($DataUserOut['links'], $nameloc['id_invoice'], true, 0, $configKeyboardRows);
    if ($configKeyboardRows === 0 || !is_string($configKeyboard)) {
        sendmessage($from_id, keyboard_config_unavailable_text($DataUserOut, 'invalid'), null, 'html');
        return;
    }
    rx_config_deliver_list($from_id, $message_id, "📌 از لیست زیر یک کانفیگ را انتخاب استفاده نمایید.", $configKeyboard);
} elseif (preg_match('/configget_(.*)_(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler configget on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'configget',
            ]);
        }
        $nameloc = false;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $_configgetPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_configgetPanelChk) && ($_configgetPanelChk['type'] ?? '') === "Manualsale") {
        $manualDelivery = rxManualsaleDelivery($nameloc['username']);
        $manualFileItems = rxManualsaleFileItems($manualDelivery['items'] ?? []);
        if (!empty($manualFileItems)) {
            $sentFiles = rxManualsaleSendFileItems($from_id, $nameloc['username'], $manualFileItems, '', null);
            if (!$sentFiles) {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            }
            return;
        }
        $manualConfigs = array_values(array_filter($manualDelivery['configs'], function ($item) {
            return is_string($item) && trim($item) !== '';
        }));
        if (empty($manualConfigs)) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            return;
        }
        if ($dataget[2] == "1520") {
            for ($i = 0; $i < count($manualConfigs); ++$i) {
                sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$i], ENT_QUOTES, 'UTF-8') . "</code>", rx_copy_button_keyboard($manualConfigs[$i], '📋 کپی کانفیگ'), 'HTML');
            }
            return;
        }
        if (!isset($manualConfigs[$dataget[2]])) {
            sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            return;
        }
        sendmessage($from_id, "<code>" . htmlspecialchars($manualConfigs[$dataget[2]], ENT_QUOTES, 'UTF-8') . "</code>", rx_copy_button_keyboard($manualConfigs[$dataget[2]], '📋 کپی کانفیگ'), 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_dot'] ?? "❌ پنل در دسترس نیست.", null, 'html');
        return;
    }
    $_remnaPanelChk = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (is_array($_remnaPanelChk) && ($_remnaPanelChk['type'] ?? '') === "remnawave" && function_exists('remnawave_get_configs')) {
        $remnaConfigs = remnawave_get_configs($nameloc['Service_location'], $nameloc['username']);
        if (($remnaConfigs['status'] ?? '') === 'successful' && !empty($remnaConfigs['links'])) {
            $DataUserOut['links'] = $remnaConfigs['links'];
        }
    }
    if (!isset($DataUserOut['links']) || !is_array($DataUserOut['links']) || count($DataUserOut['links']) === 0) {
        sendmessage($from_id, keyboard_config_unavailable_text($DataUserOut), null, 'html');
        return;
    }
    $rwCgSel = $DataUserOut['links'][$dataget[2]] ?? '';
    if ($dataget[2] != "1520" && !isset($DataUserOut['links'][$dataget[2]])) {
        sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
        return;
    }
    $config = "";
    if ($dataget[2] == "1520") {
        for ($i = 0; $i < count($DataUserOut['links']); ++$i) {
            if ($i === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
                continue;
            }
            $allConfigItem = (string) $DataUserOut['links'][$i];
            if (function_exists('xui_wg_conf_from_link')) {
                $allWgConf = xui_wg_conf_from_link($allConfigItem);
                if (is_array($allWgConf)) {
                    $allWgName = normalizeConfigFilename(xui_wg_conf_filename($allConfigItem, $allWgConf['protocol'], $i), '', true);
                    $allWgDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $allWgName;
                    file_put_contents($allWgDest, $allWgConf['conf']);
                    $allWgLabel = $allWgConf['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
                    telegram('sendDocument', [
                        'chat_id' => $from_id,
                        'document' => new CURLFile($allWgDest, 'application/octet-stream', $allWgName),
                        'caption' => "⚙️ کانفیگ شما ({$allWgLabel})",
                        'parse_mode' => 'HTML',
                    ]);
                    unlink($allWgDest);
                    continue;
                }
            }
            if (preg_match('/^https?:\/\//i', $allConfigItem)) {
                $allConfigItem = strtok($allConfigItem, '#');
            }
            sendmessage($from_id, "<code>{$allConfigItem}</code>", rx_copy_button_keyboard($allConfigItem, '📋 کپی کانفیگ'), 'HTML');
        }
        return;
    }
    $selectedConfig = (string) $DataUserOut['links'][$dataget[2]];
    if ((int) $dataget[2] === 0 && !empty($DataUserOut['single_config_file']) && is_array($DataUserOut['single_config_file'])) {
        $singleConfigFile = $DataUserOut['single_config_file'];
        $xuiFileName = normalizeConfigFilename($singleConfigFile['filename'], '', true);
        $xuiFileDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $xuiFileName;
        file_put_contents($xuiFileDest, $singleConfigFile['value']);
        telegram('sendDocument', [
            'chat_id' => $from_id,
            'document' => new CURLFile($xuiFileDest, 'application/octet-stream', $xuiFileName),
            'caption' => "⚙️ کانفیگ شما",
            'parse_mode' => 'HTML',
        ]);
        unlink($xuiFileDest);
        return;
    }
    if (function_exists('xui_wg_conf_from_link')) {
        $xuiWgConf = xui_wg_conf_from_link($selectedConfig);
        if (is_array($xuiWgConf)) {
            $xuiWgName = normalizeConfigFilename(xui_wg_conf_filename($selectedConfig, $xuiWgConf['protocol'], (int) $dataget[2]), '', true);
            $xuiWgDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $xuiWgName;
            file_put_contents($xuiWgDest, $xuiWgConf['conf']);
            $xuiWgLabel = $xuiWgConf['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
            telegram('sendDocument', [
                'chat_id' => $from_id,
                'document' => new CURLFile($xuiWgDest, 'application/octet-stream', $xuiWgName),
                'caption' => "⚙️ کانفیگ شما ({$xuiWgLabel})",
                'parse_mode' => 'HTML',
            ]);
            unlink($xuiWgDest);
            return;
        }
    }
    if (function_exists('rebeccaConfigDownloadInfo')) {
        $rebeccaDownloadInfo = rebeccaConfigDownloadInfo($selectedConfig);
        if ($rebeccaDownloadInfo !== null) {
            $rebeccaPublicName = normalizeConfigFilename($rebeccaDownloadInfo['filename'], '', $rebeccaDownloadInfo['kind'] === 'wireguard');
            $rebeccaDownloadDest = "{$from_id}-" . bin2hex(random_bytes(3)) . '-' . $rebeccaPublicName;
            if (rebeccaDownloadConfigFile($rebeccaDownloadInfo['url'], $rebeccaDownloadDest)) {
                $rebeccaSendResult = sendDocument($from_id, $rebeccaDownloadDest, "⚙️ کانفیگ شما", $rebeccaPublicName);
                @unlink($rebeccaDownloadDest);
                if (!is_array($rebeccaSendResult) || empty($rebeccaSendResult['ok'])) {
                    sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
                }
            } else {
                sendmessage($from_id, $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.", null, 'html');
            }
            return;
        }
    }
    if (preg_match('/^https?:\/\//i', $selectedConfig)) {
        $selectedConfig = strtok($selectedConfig, '#');
    }
    $isStructuredConnectionInfo = strpos($selectedConfig, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $selectedConfig);
    $qrWantedForConfig = (!function_exists('isQrDisabled') || !isQrDisabled())
        && !(function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($_configgetPanelChk))
        && !$isStructuredConnectionInfo
        && !preg_match('#^(tg|tg-proxy|proxy|wireguard|wg|vpn|awg|amneziawg)://#i', trim($selectedConfig))
        && trim($selectedConfig) !== '';
    $selectedConfigCopyKb = rx_copy_button_keyboard($selectedConfig, '📋 کپی کانفیگ');
    if ($qrWantedForConfig) {
        $qrCode = createqrcode($selectedConfig);
        if ($qrCode === null) {
            sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
            sendmessage($from_id, $datatextbot['dyn_errors_qr_too_long'] ?? "❌ ساخت QR کد برای این کانفیگ ممکن نیست — محتوای آن طولانی‌تر از ظرفیت QR کد است. از متن بالا کپی کنید.", null, 'HTML');
            return;
        }
        $urlimage = "{$from_id}" . bin2hex(random_bytes(3)) . ".png";
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        $photoPayload = [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => "<code>{$selectedConfig}</code>",
            'parse_mode' => "HTML",
        ];
        if ($selectedConfigCopyKb !== null) {
            $photoPayload['reply_markup'] = $selectedConfigCopyKb;
        }
        telegram('sendphoto', $photoPayload);
        unlink($urlimage);
    } else {
        sendmessage($from_id, "<code>{$selectedConfig}</code>", $selectedConfigCopyKb, 'HTML');
    }
} elseif (preg_match('/^remnausage_(.+)$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwBw = function_exists('remnawave_user_bandwidth') ? remnawave_user_bandwidth($rwInv['Service_location'], $rwInv['username']) : ['status' => 'Unsuccessful', 'text' => ''];
    if (($rwBw['status'] ?? '') !== 'successful') {
        sendmessage($from_id, $datatextbot['dyn_errors_usage_stats_failed'] ?? "❌ دریافت آمار مصرف ناموفق بود.", null, 'html');
        return;
    }
    $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
    sendmessage($from_id, "📊 مصرف ۳۰ روز اخیر به‌تفکیک سرور:\n\n" . $rwBw['text'], $rwBack, 'HTML');
    return;
} elseif (preg_match('/^remnasetsquad_(.+)_([0-9a-fA-F-]{36})$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwSquad = $rwm[2];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwPanel = select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
    if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
        return;
    }
    $rwSet = function_exists('remnawave_set_squads') ? remnawave_set_squads($rwInv['Service_location'], $rwInv['username'], [$rwSquad]) : ['status' => 'Unsuccessful'];
    $rwBack = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]]]]);
    sendmessage($from_id, ($rwSet['status'] ?? '') === 'successful' ? "✅ سرور با موفقیت تغییر کرد." : "❌ تغییر سرور ناموفق بود.", $rwBack, 'HTML');
    return;
} elseif (preg_match('/^remnapicker_(.+)$/', $datain, $rwm)) {
    $rwId = $rwm[1];
    $rwInv = select("invoice", "*", "id_invoice", $rwId, "select");
    if (!is_array($rwInv) || (string)($rwInv['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        return;
    }
    $rwPanel = select("marzban_panel", "*", "name_panel", $rwInv['Service_location'], "select");
    if (!is_array($rwPanel) || ($rwPanel['remna_server_picker'] ?? 'off') !== 'on') {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_unavailable_short'] ?? "❌ این قابلیت در دسترس نیست.", null, 'html');
        return;
    }
    $rwMgr = function_exists('remnawave_manager_for') ? remnawave_manager_for($rwInv['Service_location']) : null;
    $rwSq = $rwMgr ? $rwMgr->getInternalSquads($pdo) : ['ok' => false];
    if (empty($rwSq['ok']) || !is_array($rwSq['data'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_server_list_failed'] ?? "❌ دریافت لیست سرورها ناموفق بود.", null, 'html');
        return;
    }
    $rwList = is_array($rwSq['data']['internalSquads'] ?? null) ? $rwSq['data']['internalSquads'] : (isset($rwSq['data'][0]) ? $rwSq['data'] : []);
    $rwKb = ['inline_keyboard' => []];
    foreach ($rwList as $sq) {
        if (!is_array($sq) || empty($sq['uuid'])) {
            continue;
        }
        $rwKb['inline_keyboard'][] = [['text' => "🌍 " . (string)($sq['name'] ?? $sq['uuid']), 'callback_data' => "remnasetsquad_{$rwInv['id_invoice']}_{$sq['uuid']}"]];
    }
    $rwKb['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_{$rwInv['id_invoice']}"]];
    Editmessagetext($from_id, $message_id, "🌍 یک سرور (Squad) انتخاب کنید:", json_encode($rwKb));
    return;
} elseif (preg_match('/changestatus_(\w+)/', $datain, $dataget)) {
    $rxChangeStatusInvoice = select("invoice", "*", "id_invoice", $dataget[1], "select");
    if (is_array($rxChangeStatusInvoice) && !panel_feature_enabled($rxChangeStatusInvoice['Service_location'], 'changeservice')) {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changestatus on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changestatus',
            ]);
        }
        return;
    }
    if ($nameloc['Status'] == "disablebyadmin") {
        sendmessage($from_id, $datatextbot['dyn_errors_feature_currently_unavailable_nodot'] ?? "❌ این قابلیت درحال حاضر در دسترس نیست", null, 'html');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;

    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $changeStatusPanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($DataUserOut['status'] == "on_hold" || ($DataUserOut['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($changeStatusPanel) ? $changeStatusPanel : null, $DataUserOut))) {
        sendmessage($from_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlinePanel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '♻️ تمدید سرویس', 'callback_data' => 'extend_' . $nameloc['id_invoice']], ['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کانفیگ', 'callback_data' => "confirmaccountdisable_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisable_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmaccountdisable on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmaccountdisable',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $currentStatusData = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (is_array($currentStatusData) && $currentStatusData['status'] === 'active' && !$ManagePanel->HasLiveConnectionHistory(is_array($marzban_list_get) ? $marzban_list_get : null, $currentStatusData)) {
        Editmessagetext($from_id, $message_id, "ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.", $bakinfos);
        return;
    }
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        if (($dataoutput['code'] ?? '') === 'not_connected') {
            Editmessagetext($from_id, $message_id, $dataoutput['msg'], $bakinfos);
            return;
        }
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extend on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'extend',
            ]);
        }
        return;
    }
    if ($nameloc == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    if (function_exists('nmMaybeShowStockInvoiceDetails') && nmMaybeShowStockInvoiceDetails($from_id, $message_id ?? null, $nameloc)) { step('home', $from_id); return; }
    if (function_exists('nmStopIfServicePanelBlocked') && nmStopIfServicePanelBlocked($nameloc, $from_id, null)) return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $offlineKeyboard = json_encode(['inline_keyboard' => [
            [['text' => '🛒 خرید سرویس جدید', 'callback_data' => 'buy_service']],
            [['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => 'backorder']]
        ]]);
        sendmessage($from_id, $datatextbot['dyn_errors_panel_connection_error'] ?? "❌ سامانه استعلام سرویس مورد نظر درحال حاضر در دسترس نیست.", $offlineKeyboard, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $stmt->execute([
        ':service_location' => $marzban_list_get['name_panel'],
        ':agent' => $user['agent'],
    ]);
    $product = $stmt->rowCount();
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    if ($product == 0) {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        deletemessage($from_id, $message_id);
        step('gettimecustomvolomforextend', $from_id);
        return;
    }
    if (!panel_feature_enabled($nameloc['Service_location'], 'categorytime')) {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
        ]);
        $productextend = ['inline_keyboard' => []];
        $statusshowprice = panel_feature_enabled($nameloc['Service_location'], 'showprice') ? "onshowprice" : "offshowprice";
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hide_panel = json_decode($result['hide_panel'], true);
            if (is_array($hide_panel) && in_array($nameloc['Service_location'], $hide_panel)) {
                error_log("Product {$result['code_product']} is marked hidden for {$nameloc['Service_location']} but was kept visible for extend.");
            }
            $result = fx_apply_to_product($result, $marzban_list_get);
            if (intval($user['pricediscount']) != 0) {
                $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                $result['price_product'] = $result['price_product'] - $resultper;
            }
            $result['price_product'] = fx_finalize_amount($result['price_product'], $marzban_list_get, 'product');
            if ($statusshowprice == "offshowprice") {
                $namekeyboard = $result['name_product'];
            } else {
                $result['price_product'] = number_format($result['price_product']);
                $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
            }
            $productextend['inline_keyboard'][] = [
                ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
            ];
        }
        $productextend['inline_keyboard'][] = [
            ['text' => "♻️ تمدید پلن فعلی", 'callback_data' => "exntedagei"]
        ];
        $productextend['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
        ];

        $json_list_product_lists = json_encode($productextend);
        $textselectservice = function_exists('faoxima_render_text')
            ? faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
            : $textbotlang['users']['extend']['selectservice'];
        Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
    } else {
        $monthkeyboard = keyboardTimeCategory($nameloc['Service_location'], $user['agent'], "productextendmonths_", "product_$id_invoice", false, true);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif ($user['step'] == "gettimecustomvolomforextend") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextend', $from_id);
} elseif (preg_match('/productextendmonths_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND Service_time = :monthe AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $stmt->execute([
        ':service_location' => $nameloc['Service_location'],
        'monthe' => $monthenumber,
        ':agent' => $user['agent'],
    ]);
    $productextend = ['inline_keyboard' => []];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $statusshowprice = panel_feature_enabled($marzban_list_get, 'showprice') ? "onshowprice" : "offshowprice";
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = fx_apply_to_product($result, $marzban_list_get);
        if (intval($user['pricediscount']) != 0) {
            $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        $result['price_product'] = fx_finalize_amount($result['price_product'], $marzban_list_get, 'product');
        if ($statusshowprice == "offshowprice") {
            $namekeyboard = $result['name_product'];
        } else {
            $result['price_product'] = number_format($result['price_product']);
            $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . "تومان";
        }
        $productextend['inline_keyboard'][] = [
            ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product']]
        ];
    }
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $productextend['inline_keyboard'][] = [
            ['text' => "📍 انتخاب سرویس فعلی", 'callback_data' => "serviceextendselect_pre"]
        ];
    }
    $productextend['inline_keyboard'][] = [
        ['text' => "🏠 بازگشت به اطلاعات سرویس", 'callback_data' => "product_" . $nameloc['id_invoice']]
    ];

    $json_list_product_lists = json_encode($productextend);
    $textselectservice = function_exists('faoxima_render_text')
        ? faoxima_render_text($textbotlang['users']['extend']['selectservice'], ['panel' => $nameloc['Service_location']])
        : $textbotlang['users']['extend']['selectservice'];
    Editmessagetext($from_id, $message_id, $textselectservice, $json_list_product_lists);
} elseif (preg_match('/^serviceextendselect_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserforextend" || $datain == "exntedagei") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($user['step'] == "getvolumecustomuserforextend") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
    } elseif ($datain == "exntedagei") {
        $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
        $stmt->execute([
            ':username' => $nameloc['username'],
        ]);
        if ($stmt->rowCount() == 0) {
            $codeproduct = select("product", "*", "name_product", $nameloc['name_product']);
        } else {
            $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true)))) {
                sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
                return;
            }
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "code_product", "code_product", $service_other['code_product'], "select");
        }
        if ($codeproduct == false) {
            sendmessage($from_id, $datatextbot['dyn_errors_extend_current_plan_unavailable'] ?? "❌ امکان تمدید با پلن فعلی وجود ندارد  مراحل را از اول طی کرده و یک پلن دیگر انتخاب نمایید.", $keyboard, 'HTML');
            return;
        }
        $codeproduct = $codeproduct['code_product'];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    } else {
        $codeproduct = $dataget[1];
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    if ($user['step'] == "getvolumecustomuserforextend") {
        $product['name_product'] = $nameloc['name_product'];
        $product['code_product'] = "customvolume";
        $product['note'] = "";
        $product['price_product'] = (intval($userdate['volume']) * $custompricevalue) + ($text * $customtimevalueprice);
        $product['Service_time'] = $text;
        $product['Volume_constraint'] = $userdate['volume'];
        step("home", $from_id);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent'],
        ]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_restart_process'] ?? "❌ خطایی رخ داده است مراحل تمدید را از اول انجام دهید.", $keyboard, 'HTML');
        return;
    }
    $fxRenewKinds = $product['code_product'] === "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($product['code_product'] !== "customvolume") {
        $product = fx_apply_to_product($product, $marzban_list_get);
    }
    $product['price_product'] = fx_finalize_amount($product['price_product'], $marzban_list_get, $fxRenewKinds);
    savedata("save", "time", $product['Service_time']);
    savedata("save", "data_limit", $product['Volume_constraint']);
    savedata("save", "price_product", $product['price_product']);
    savedata("save", "code_product", $product['code_product']);
    $__discEligibleExtend = MiniDiscount::hasEligible('extend', (string)($product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($product['category'] ?? ''), $user);
    $__extendConfirmRow = [['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"]];
    if ($__discEligibleExtend) {
        $__extendConfirmRow[] = ['text' => $textbotlang['users']['extend']['discount'], 'callback_data' => "discountextend"];
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            $__extendConfirmRow,
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ]
    ]);
    if (intval($user['pricediscount']) != 0) {
        $result = ($product['price_product'] * $user['pricediscount']) / 100;
        $pricelastextend = number_format(round(fx_finalize_amount($product['price_product'] - $result, $marzban_list_get, $fxRenewKinds), 0));
    } else {
        $pricelastextend = number_format($product['price_product']);
    }
    fx_quote_store($from_id, fx_quote_context_key('renew', (string) $nameloc['id_invoice'] . '|' . (string) $product['code_product']), fx_finalize_amount(fx_apply_user_discount($product['price_product'], $user['pricediscount']), $marzban_list_get, $fxRenewKinds), $marzban_list_get, $fxRenewKinds);
    $volumeextend = intval($product['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $product['Volume_constraint'] . ' گیگ';
    $rxReservationUi = rxReservationUiEnabled($marzban_list_get);
    $rxRenewPreview = rxRenewalPreviewForService($ManagePanel, $marzban_list_get, (string) $nameloc['username'], $product['Volume_constraint'], $product['Service_time'], ['id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_preview']);
    if ($rxReservationUi && empty($rxRenewPreview['blocked'])) {
        $rxRenewPreview['text'] = '';
    }
    if (!empty($rxRenewPreview['blocked'])) {
        $keyboardextend = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]
                ]
            ]
        ]);
    }
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$product['name_product']}
💸 مبلغ تمدید : $pricelastextend تومان
⏱ مدت زمان تمدید :{$product['Service_time']} روز
🔋 حجم تمدید :$volumeextend
✍️ توضیحات : {$product['note']}
💸 موجودی کیف پول : " . rxFormatToman($user['Balance']) . "
" . ($rxRenewPreview['text'] !== '' ? "\n" . $rxRenewPreview['text'] . "\n\n" : '') . (empty($rxRenewPreview['blocked']) ? "✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید" : '');
    if ($user['step'] == "getvolumecustomuserforextend") {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif (in_array($datain, ['rsvt', 'rsvp', 'rsvu', 'rsvd'], true) || strpos((string) $datain, 'rsvc:') === 0) {
    if (!empty($callback_query_id)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => rxRenewalText('button_outdated'), 'show_alert' => true]);
    }
} elseif ($user['step'] == 'rsv_get_date') {
    step('home', $from_id);
    sendmessage($from_id, rxRenewalText('button_outdated'), $keyboard, 'HTML');
} elseif ($datain == "discountextend") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountextend', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountextend") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_exclusive_discount_conflict'] ?? "❌ شما تخفیف اختصاصی دارید و امکان استفاده از کد تخفیف وجود ندارد.", $backuser, 'HTML');
        return;
    }
    $__extendProductRow = select("product", "*", "code_product", $userdate['code_product'] ?? '', "select");
    $__extendCategory = is_array($__extendProductRow) ? (string)($__extendProductRow['category'] ?? '') : '';
    $__dv = MiniDiscount::validateSell($text, 'extend', (string)($userdate['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), $__extendCategory, $user);
    if (empty($__dv['ok'])) {
        sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
        return;
    }
    $__dvt = $__dv['value_type'];
    $__dval = (float)$__dv['value'];
    $__dlabel = $__dv['label'];
    $rxDiscountAppliedTpl = $datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.";
    sendmessage($from_id, sprintf($rxDiscountAppliedTpl, $__dlabel), $keyboard, 'HTML');
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || rxRenewalIsCustomCode($userdate['code_product'] ?? '')) {
        $info_product['code_product'] = "pre";
        $info_product['name_product'] = $nameloc['name_product'];
        $info_product['price_product'] = ($userdate['data_limit'] * $custompricevalue) + ($userdate['time'] * $customtimevalueprice);
        $info_product['Service_time'] = $userdate['time'];
        $info_product['Volume_constraint'] = $userdate['data_limit'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
        $stmt->bindParam(':code_product', $userdate['code_product'], PDO::PARAM_STR);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel'], PDO::PARAM_STR);
        $stmt->bindParam(':agent', $user['agent'], PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $fxRenewCustom = ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || rxRenewalIsCustomCode($userdate['code_product'] ?? ''));
    $fxRenewKinds = $fxRenewCustom ? ['custom_volume', 'custom_time'] : 'product';
    if (!$fxRenewCustom && is_array($info_product)) {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    if (is_array($info_product) && isset($info_product['price_product'])) {
        $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxRenewKinds);
    }
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    $rxRenewPreview = rxRenewalPreviewForService($ManagePanel, $marzban_list_get, (string) $nameloc['username'], $info_product['Volume_constraint'], $info_product['Service_time'], ['reservation' => rxReservationUiEnabled($marzban_list_get) ? rxReservationPanelSelection($marzban_list_get) : null, 'id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_preview']);
    if (rxReservationUiEnabled($marzban_list_get) && empty($rxRenewPreview['blocked'])) {
        $rxRenewPreview['text'] = '';
    }
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxRenewKinds);
    $rxFmtExtendInfoProductPrice = rxFormatToman($info_product['price_product']);
    $rxFmtExtendUserBalance = rxFormatToman($user['Balance']);
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.

🛍 نام محصول :{$info_product['name_product']}
💸 مبلغ تمدید :{$rxFmtExtendInfoProductPrice}
⏱ مدت زمان تمدید :{$info_product['Service_time']} روز
🔋 حجم تمدید :{$info_product['Volume_constraint']} گیگ
✍️ توضیحات : {$info_product['note']}
💸 موجودی کیف پول : {$rxFmtExtendUserBalance}
" . ($rxRenewPreview['text'] !== '' ? "\n" . $rxRenewPreview['text'] . "\n" : '') . (empty($rxRenewPreview['blocked']) ? "\n✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید" : '');
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"],
            ]
        ]
    ]);
    sendmessage($from_id, $textextend, empty($rxRenewPreview['blocked']) ? $keyboardextend : null, 'HTML');
    $parametrsendvalue = "dis_" . $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    fx_quote_store($from_id, fx_quote_context_key('renew_discount', (string) $nameloc['id_invoice'] . '|' . (string) ($userdate['code_product'] ?? '')), $info_product['price_product'], $marzban_list_get, $fxRenewKinds);
    step("home", $from_id);
} elseif ($datain == "confirmserivce" || $datain == "confirmserdiscount") {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $partsdic = explode("_", $user['Processing_value_four']);
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    $randomString = bin2hex(random_bytes(2));
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || rxRenewalIsCustomCode($userdata['code_product'] ?? '')) {
        $prodcut['code_product'] = "custom_volume";
        $prodcut['name_product'] = $nameloc['name_product'];
        $prodcut['price_product'] = ($userdata['data_limit'] * $custompricevalue) + ($userdata['time'] * $customtimevalueprice);
        $prodcut['Service_time'] = $userdata['time'];
        $prodcut['Volume_constraint'] = $userdata['data_limit'];
        $prodcut['inbounds'] = $marzban_list_get['inboundid'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':code_product' => $userdata['code_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $pricelastextend = $prodcut['price_product'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $fxRenewCustom = ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || rxRenewalIsCustomCode($userdata['code_product'] ?? ''));
    $fxRenewKinds = $fxRenewCustom ? ['custom_volume', 'custom_time'] : 'product';
    if (!$fxRenewCustom) {
        $prodcut = fx_apply_to_product($prodcut, $marzban_list_get);
    }
    $prodcut['price_product'] = fx_finalize_amount($prodcut['price_product'], $marzban_list_get, $fxRenewKinds);
    $pricelastextend = $prodcut['price_product'];
    $fxRenewRef = (string) $nameloc['id_invoice'] . '|' . (string) ($userdata['code_product'] ?? '');
    $fxRenewDiscounted = false;
    if ($datain == "confirmserdiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($SellDiscountlimit != false) {
            $pricelastextend = $partsdic[2];
            $fxRenewDiscounted = true;
            $fxRenewDiscountKey = fx_quote_context_key('renew_discount', $fxRenewRef);
            if (!fx_quote_check($from_id, $fxRenewDiscountKey, $pricelastextend, $marzban_list_get, $fxRenewKinds)) {
                $fxRecheck = MiniDiscount::validateSell((string) $partsdic[1], 'extend', (string) ($userdata['code_product'] ?? ''), (string) ($marzban_list_get['code_panel'] ?? ''), (string) ($prodcut['category'] ?? ''), $user);
                if (empty($fxRecheck['ok'])) {
                    sendmessage($from_id, (string) ($fxRecheck['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                    return;
                }
                $fxNewDiscounted = fx_finalize_amount(fx_apply_discount_rule($prodcut['price_product'], (string) $fxRecheck['value_type'], (float) $fxRecheck['value']), $marzban_list_get, $fxRenewKinds);
                update("user", "Processing_value_four", "dis_" . $partsdic[1] . "_" . $fxNewDiscounted, "id", $from_id);
                fx_quote_store($from_id, $fxRenewDiscountKey, $fxNewDiscounted, $marzban_list_get, $fxRenewKinds);
                sendmessage($from_id, fx_price_changed_text($fxNewDiscounted), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"]]]]), 'HTML');
                return;
            }
        }
    }
    if (!$fxRenewDiscounted) {
        $fxRenewCheckAmount = fx_finalize_amount(fx_apply_user_discount($pricelastextend, $user['pricediscount']), $marzban_list_get, $fxRenewKinds);
        $fxRenewQuoteKey = fx_quote_context_key('renew', $fxRenewRef);
        if (!fx_quote_check($from_id, $fxRenewQuoteKey, $fxRenewCheckAmount, $marzban_list_get, $fxRenewKinds)) {
            fx_quote_store($from_id, $fxRenewQuoteKey, $fxRenewCheckAmount, $marzban_list_get, $fxRenewKinds);
            sendmessage($from_id, fx_price_changed_text($fxRenewCheckAmount), json_encode(['inline_keyboard' => [
                [['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"]],
                [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]],
            ]]), 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($pricelastextend * $user['pricediscount']) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $pricelastextend = fx_finalize_amount($pricelastextend, $marzban_list_get, $fxRenewKinds);
    $fxRenewBasePrice = $fxRenewCustom ? fx_custom_base_price($marzban_list_get, $user['agent'], $userdata['data_limit'] ?? 0, $userdata['time'] ?? 0) : ($prodcut['fx_base_price'] ?? $prodcut['price_product']);
    $fxRenewSnapshot = fx_pricing_snapshot($marzban_list_get, $fxRenewKinds, $fxRenewBasePrice, $pricelastextend, (float) $prodcut['price_product'] - (float) $pricelastextend, ['product' => (string) ($prodcut['code_product'] ?? '')]);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $rxConfirmSnapshot = rx_pf_snapshot_from_datauser($DataUserOut, (string) ($marzban_list_get['type'] ?? ''));
    $rxReservationSelection = rxReservationUiEnabled($marzban_list_get) ? rxReservationPanelSelection($marzban_list_get) : null;
    $rxConfirmPreview = rxRenewalPreviewForService($ManagePanel, $marzban_list_get, (string) $nameloc['username'], $prodcut['Volume_constraint'], $prodcut['Service_time'], ['reservation' => $rxReservationSelection, 'snapshot' => $rxConfirmSnapshot, 'id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_confirm']);
    if (!empty($rxConfirmPreview['blocked'])) {
        sendmessage($from_id, (string) $rxConfirmPreview['text'], null, 'HTML');
        return;
    }
    if (is_array($rxReservationSelection)) {
        $rxSelectionCheck = rxReservationNormalizeSelection($rxReservationSelection, !empty($rxConfirmSnapshot['ok']) && !empty($rxConfirmSnapshot['exists']) ? $rxConfirmSnapshot : null, time());
        if (empty($rxSelectionCheck['ok'])) {
            rx_renewal_log('telegram_confirm_selection', (string) $rxSelectionCheck['reason'], ['panel' => $marzban_list_get, 'username' => (string) $nameloc['username'], 'invoice_id' => (string) $nameloc['id_invoice'], 'invoice_status' => $nameloc['Status'] ?? null, 'method' => (string) ($marzban_list_get['Methodextend'] ?? ''), 'volume_gb' => $prodcut['Volume_constraint'], 'days' => $prodcut['Service_time'], 'snapshot' => $rxConfirmSnapshot, 'detail' => $rxSelectionCheck['detail'] ?? null, 'mutation' => 'none']);
            sendmessage($from_id, rxRenewalReasonText((string) $rxSelectionCheck['reason'], $rxSelectionCheck), null, 'HTML');
            return;
        }
    }
    if (rx_ck_available_for_user($user) < $pricelastextend && $user['agent'] != "n2" && intval($pricelastextend) != 0) {
        $marzbandirectpay = panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
            $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?,?, ?, ?,?,?,?)");
            $dateacc = date('Y/m/d H:i:s');
            $value = json_encode(array(
                "volumebuy" => $prodcut['Volume_constraint'],
                "Service_time" => $prodcut['Service_time'],
                "oldvolume" => $DataUserOut['data_limit'],
                "oldtime" => $DataUserOut['expire'],
                'code_product' => rxRenewalNormalizeProductCode($prodcut['code_product']),
                'id_order' => $randomString,
                'reservation' => $rxReservationSelection
            ));
            $value = fx_value_with_snapshot($value, $fxRenewSnapshot);
            $type = "extend_user";
            $status = "unpaid";
            $extend = '';
            $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend, $status);
            $stmt->execute();
            $stmt->close();
            update("user", "Processing_value_one", "{$nameloc['username']}%$randomString", "id", $from_id);
            update("user", "Processing_value_tow", "getextenduser", "id", $from_id);
            rx_ck_bot_begin($user, [
                'operation' => 'getextenduser',
                'op_key' => "getextenduser|{$nameloc['username']}%$randomString",
                'target' => (string) $nameloc['username'],
                'final_price' => $pricelastextend,
                'message_id' => $message_id,
                'one' => "{$nameloc['username']}%$randomString",
                'tow' => 'getextenduser',
                'four' => '',
            ]);
            return;
        }
    }
    $rxExtendDiscountRow = false;
    if ($datain == "confirmserdiscount") {
        $rxExtendDiscountRow = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
        if ($rxExtendDiscountRow != false) {
            update('invoice', 'discount_code', $partsdic[1], 'id_invoice', $nameloc['id_invoice']);
            update('invoice', 'discount_amount', (string)((float)($prodcut['price_product'] ?? 0) - (float)$pricelastextend), 'id_invoice', $nameloc['id_invoice']);
            update('invoice', 'price_before_discount', (string)($prodcut['price_product'] ?? 0), 'id_invoice', $nameloc['id_invoice']);
        }
    }
    if ($user['agent'] == "f") {
        $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
    } else {
        $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$user['agent']];
    }
    $renewCashbackEligible = !function_exists('rx_shopCashbackEligible')
        || rx_shopCashbackEligible("chashbackextend", $user['register'] ?? null, "getextenduser");
    $rxCashbackDiscount = 0;
    if ($renewCashbackEligible && intval($valurcashbackextend) != 0 and intval($pricelastextend) != 0) {
        $rxCashbackDiscount = ($prodcut['price_product'] * $valurcashbackextend) / 100;
        $pricelastextend = $pricelastextend - $rxCashbackDiscount;
        if ($pricelastextend < 0) {
            $pricelastextend = 0;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $pricelastextend) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $__allowNegEx = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__chargedExUser = false;
    if (intval($pricelastextend) > 0) {
        $__chargeEx = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $pricelastextend, $__allowNegEx) : ['ok' => false];
        if (empty($__chargeEx['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEx['new_balance'];
        $__chargedExUser = true;
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $pricelastextend, 'service_action', 'تمدید سرویس', $randomString, 'invoice', (string)$id_invoice);
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $rxExtendOptions = [
        'source' => 'wallet',
        'ref' => 'wallet:' . $randomString,
        'id_user' => $from_id,
        'price' => $pricelastextend,
        'reservation' => is_array($rxReservationSelection) ? array_merge($rxReservationSelection, ['payment_ref' => 'wallet:' . $from_id . ':' . $randomString]) : null,
    ];
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel'], null, $rxExtendOptions);
    $rxExtendPending = rxRenewalIsPending($extend);
    if ($extend['status'] == false && !$rxExtendPending) {
        if ($__chargedExUser && function_exists('balance_atomic_credit')) {
            $__refundedEx = balance_atomic_credit($from_id, $pricelastextend);
            if ($__refundedEx && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $pricelastextend, 'refund', 'بازگشت وجه تمدید سرویس', $randomString, 'invoice', (string)$id_invoice);
            }
        }
        $rxStockEmpty = ($extend['code'] ?? '') === 'manual_stock_empty';
        $rxQueuedExists = ($extend['code'] ?? '') === 'queued_renewal_exists';
        $extend['msg'] = rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
        $textreports = "خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>کد خطا : " . htmlspecialchars((string) ($extend['code'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
        if ($rxStockEmpty) {
            sendmessage($from_id, $datatextbot['dyn_errors_renewal_stock_empty'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } elseif ($rxQueuedExists) {
            sendmessage($from_id, $datatextbot['dyn_errors_queued_renewal_exists'] ?? "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } else {
            sendmessage($from_id, rxRenewalFailureText($extend, $datatextbot['dyn_errors_renewal_support_error'] ?? "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
        }
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if ($rxCashbackDiscount > 0) {
        $rxCashbackGiftTpl = $datatextbot['dyn_rewards_cashback_gift'] ?? "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ %s تومان حساب شما شارژ گردید";
        sendmessage($from_id, sprintf($rxCashbackGiftTpl, $rxCashbackDiscount), null, 'HTML');
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?, ?, ?,?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => rxRenewalNormalizeProductCode($prodcut['code_product']),
        'id_order' => $randomString,
        'reservation' => $rxReservationSelection
    ));
    $value = fx_value_with_snapshot($value, $fxRenewSnapshot);
    $type = "extend_user";
    $status = "paid";
    $extend_json = json_encode($extend);
    $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'],$extend_json, $status);
    $stmt->execute();
    $stmt->close();
    if (!$rxExtendPending && empty($extend['queued'])) {
        if ($nameloc['name_product'] == "سرویس تست") {
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        } elseif (rxRenewalNormalizeProductCode($prodcut['code_product'] ?? '') !== 'custom_volume' && rxRenewalIsAppliedVerified($extend)) {
            rxRenewalSyncInvoiceName($nameloc['id_invoice'], $prodcut['name_product'] ?? '');
        }
        if (($nameloc['Status'] ?? '') !== 'disablebyadmin') {
            update("invoice", "Status", "active", "id_invoice", $id_invoice);
        }
    }
    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $id_invoice,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'renewal',
        'name_product' => $prodcut['name_product'],
        'amount' => $pricelastextend,
        'source' => 'bot',
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 2), null, 'html');
        $scorenew = $user['score'] + 2;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $keyboardextendfnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $priceproductformat = number_format($pricelastextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $rxFmtProdcutPriceExtend = rxFormatToman($prodcut['price_product']);
    if (!empty($extend['queued'])) {
        $rxQueuedSuccessTpl = (string) ($extend['reservation']['confirmation'] ?? rxRenewalText('reservation_confirmed'));
        $textextend = "$rxQueuedSuccessTpl

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
    } else {
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        $textextend = rxRenewalDecorateSuccess($textextend, $extend, (string) $nameloc['username'], (string) ($marzban_list_get['name_panel'] ?? ''));
    }
    sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}</blockquote>
<blockquote>▫️نام محصول : {$prodcut['name_product']}</blockquote>
<blockquote>▫️حجم محصول : {$prodcut['Volume_constraint']}</blockquote>
<blockquote>▫️زمان محصول : {$prodcut['Service_time']}</blockquote>
<blockquote>▫️مبلغ تمدید : {$rxFmtProdcutPriceExtend} تومان</blockquote>
<blockquote>▫️موجودی قبل از خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد از خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('renewal', [
            'user_id'    => $from_id,
            'amount'     => $prodcut['name_product'],
            'price'      => $priceproductformat,
            'panel_name' => $nameloc['Service_location'] ?? '',
            'category'   => $prodcut['category'] ?? '',
        ], $setting);
    }
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changelink on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changelink',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "disabled" || $DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_link_change_service_disabled'] ?? "❌ سرویس غیرفعال است و امکان تعویض لینک برای سرویس وجود ندارد.", null, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['changelink']['confirm'], 'callback_data' => "confirmchange_" . $nameloc['id_invoice']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['warnchange'], $keyboardextend);
} elseif (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchange on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchange',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, '❌ خطایی در تغییر لینک رخ داده است.', null, 'HTML');
        return;
    }
    $textconfig = "✅ کانفیگ شما با موفقیت بروزرسانی گردید.";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $DataUserOut['subscription_url'];
        $textconfig .= "اشتراک شما : <code>$output_config_link</code>";
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if ($marzban_list_get['config'] == "onconfig") {
        Editmessagetext($from_id, $message_id, $textconfig, keyboard_config($DataUserOut['configs'], $nameloc['id_invoice'], true));
    } else {
        Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات تغییر لینک در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️زمان تغییر لینک : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
        ]);
    }
} elseif (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_volume on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_volume',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_unavailable_panel'] ?? "❌ امکان خرید حجم اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = " ⭕️ مقدار حجمی که میخواهید خریداری کنید را ارسال کنید.
❌ مبلغ را به انگلیسی ارسال نمایید.
        ⚠️ هر گیگ  حجم اضافه " . rxFormatToman($extrapricevalue) . " تومان  است.";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('getvolumeextra', $from_id);
} elseif ($user['step'] == "getvolumeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    $priceextra = $extrapricevalue * $text;
    $priceextra = fx_finalize_amount($priceextra, $marzban_list_get, 'extra_volume');
    fx_quote_store_server($from_id, fx_quote_context_key('extra_volume', (string) $nameloc['id_invoice']), $priceextra, $marzban_list_get, 'extra_volume', ['qty' => (int) $text]);
    $__volSourceProduct = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__volCategory = is_array($__volSourceProduct) ? (string)($__volSourceProduct['category'] ?? '') : '';
    $__discEligibleVolume = MiniDiscount::hasEligible('volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory, $user);
    $__volumeRows = [
        [
            ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $priceextra],
        ],
    ];
    if ($__discEligibleVolume) {
        $__volumeRows[] = [
            ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discountvolume-' . $priceextra],
        ];
    }
    $keyboardsetting = json_encode(['inline_keyboard' => $__volumeRows]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید حجم اضافه برای شما ایجاد شد.

📌 تعرفه هر گیگابایت حجم اضافه : $extrapricevalues تومان
🔋 حجم اضافه درخواستی : $text گیگابایت
💰 مبلغ فاکتور شما : $priceextra تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discountvolume-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "prevol_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountvolume', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountvolume") {
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseVol = (strpos($__pv4, 'prevol_') === 0) ? intval(substr($__pv4, 7)) : 0;
    if ($__baseVol <= 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__volSourceProduct2 = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__volCategory2 = is_array($__volSourceProduct2) ? (string)($__volSourceProduct2['category'] ?? '') : '';
    $dv = MiniDiscount::validateSell($text, 'volume', '', (string)($marzban_list_get['code_panel'] ?? ''), $__volCategory2, $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    if (!fx_quote_server_amount_matches($from_id, fx_quote_context_key('extra_volume', (string) $nameloc['id_invoice']), $__baseVol) || !fx_quote_check($from_id, fx_quote_context_key('extra_volume', (string) $nameloc['id_invoice']), $__baseVol, $marzban_list_get, 'extra_volume')) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $__discounted = (int)round(MiniDiscount::applyToPrice($dv['row'], $__baseVol));
    if ($__discounted < 0) $__discounted = 0;
    $__discounted = fx_finalize_amount($__discounted, $marzban_list_get, 'extra_volume');
    update("user", "Processing_value_four", "disv_" . $text . "_" . $__baseVol . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseVol, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextradiscount-' . $__baseVol],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن حجم، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextra(discount)?-(\w+)/', $datain, $dataget)) {
    $volume = $dataget[2];
    $__volDiscountCode = '';
    $__volCharge = (float)$volume;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'disv_') === 0) {
            $__pp = explode('_', $__pv4);
            $__volDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__volCharge = (float)$__pp[3];
        }
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_failed_restart'] ?? "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    $fxExtraVerify = fx_extra_quote_verify($from_id, fx_quote_context_key('extra_volume', (string) $nameloc['id_invoice']), $extrapricevalue, $volume, $marzban_list_get, 'extra_volume');
    if ($fxExtraVerify['status'] === 'missing') {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_restart_process'] ?? "❌ مراحل خرید حجم اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        return;
    }
    if ($fxExtraVerify['status'] === 'changed') {
        sendmessage($from_id, fx_price_changed_text($fxExtraVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra-' . $fxExtraVerify['amount']]]]]), 'HTML');
        return;
    }
    $fxExtraQty = $fxExtraVerify['qty'];
    if (rx_ck_available_for_user($user) < $__volCharge && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuevolume = $fxExtraQty ?? intval($volume) / intval($extrapricevalue);
            if (intval($user['pricediscount']) != 0) {
                $result = ($__volCharge * $user['pricediscount']) / 100;
                $__volCharge = $__volCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $__volCharge = fx_finalize_amount($__volCharge, $marzban_list_get, 'extra_volume');
            rx_ck_bot_begin($user, [
                'operation' => 'getextravolumeuser',
                'op_key' => "getextravolumeuser|{$nameloc['username']}%{$valuevolume}",
                'target' => (string) $nameloc['username'],
                'final_price' => $__volCharge,
                'message_id' => $message_id,
                'one' => "{$nameloc['username']}%{$valuevolume}",
                'tow' => 'getextravolumeuser',
                'four' => $__volDiscountCode !== '' ? "dvol|" . $__volDiscountCode . "|" . $volume : '',
            ]);
            return;
        }
    }
    deletemessage($from_id, $message_id);
    $volumepricelast = $__volCharge;
    if (intval($user['pricediscount']) != 0) {
        $result = ($__volCharge * $user['pricediscount']) / 100;
        $volumepricelast = $__volCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $volumepricelast = fx_finalize_amount($volumepricelast, $marzban_list_get, 'extra_volume');
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volumepricelast) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $__allowNegVx = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__chargedVxUser = false;
    if ($volumepricelast > 0) {
        $__chargeVx = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $volumepricelast, $__allowNegVx) : ['ok' => false];
        if (empty($__chargeVx['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeVx['new_balance'];
        $__chargedVxUser = true;
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $volumepricelast, 'service_action', 'خرید حجم اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'volume_value' => $fxExtraQty ?? intval($volume) / intval($extrapricevalue),
        'priceـper_gig' => $extrapricevalue,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $data_for_database = fx_value_with_snapshot($data_for_database, fx_pricing_snapshot($marzban_list_get, 'extra_volume', ($fxExtraQty ?? 0) * (fx_panel_raw_unit_price($marzban_list_get, 'priceextravolume', $user['agent']) ?? 0), $volumepricelast, (float) $volume - (float) $volumepricelast, ['quantity' => $fxExtraQty, 'unit_price' => $extrapricevalue]));
    $data_limit = $fxExtraQty ?? intval($volume) / intval($extrapricevalue);
    $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $data_limit);
    if ($extra_volume['status'] == false) {
        if ($__chargedVxUser && function_exists('balance_atomic_credit')) {
            $__refundedVx = balance_atomic_credit($from_id, $volumepricelast);
            if ($__refundedVx && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $volumepricelast, 'refund', 'بازگشت وجه خرید حجم اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
        }
        $extra_volume['msg'] = rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $nameloc['id_invoice'] ?? null,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'volume',
        'name_product' => $nameloc['name_product'] ?? null,
        'amount' => $volumepricelast,
        'source' => 'bot',
    ]);
    if ($__volDiscountCode !== '') {
        MiniDiscount::markSellUsed($__volDiscountCode, $user);
        MiniDiscount::logOrderDiscount([
            'id_user' => $from_id,
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'code' => $__volDiscountCode,
            'kind' => 'sell',
            'price_before' => $volume,
            'discount_amount' => (float)$volume - (float)$volumepricelast,
            'price_after' => $volumepricelast,
            'section' => 'volume',
        ]);
        update("user", "Processing_value_four", "", "id", $from_id);
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volumepricelast,
        ':output' => json_encode($extra_volume),
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($volumepricelast, 0);
    $volumes = $fxExtraQty ?? $volume / $extrapricevalue;
    $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس  : {$nameloc['username']}
▫️حجم اضافه : $volumes گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
    sendmessage($from_id, $textvolume, $keyboardextrafnished, 'HTML');
    $text_report = "⭕️ یک کاربر حجم اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 حجم خریداری شده  : $volumes گیگ</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>
<blockquote>موجودی کاربر قبل خرید : " . rxFormatToman($user['Balance']) . "</blockquote>
";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/changeloc_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    if ($user['limitchangeloc'] > $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_limit_reached'] ?? "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changeloc on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changeloc',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || $DataUserOut['status'] == "disabled") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_userschange);
} elseif (preg_match('/changelocselectlo-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_one", $dataget[1], "id", $from_id);
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $userlimitlast = $limitchangeloc['all'] - $user['limitchangeloc'];
    $userlimitlastfree = $limitchangeloc['free'] - $user['limitchangeloc'];
    if ($userlimitlastfree < 0)
        $userlimitlastfree = 0;
    $Pricechange = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['priceChangeloc'];
    $textchange = "📍 با  تایید کردن انتقال موقعیت سرویس شما در این موقعیت حذف و به موقعیت جدید منتقل خواهد شد.
💰 هزینه انتقال " . rxFormatToman($Pricechange) . " تومان می باشد
📌 محدودیت باقی مانده شما : $userlimitlast عدد (تعداد محدودیت رایگان باقی مانده :‌$userlimitlastfree عدد)

✅ برای تایید انتقال روی دکمه زیر کلیک کنید";
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['change-location']['confirm'], 'callback_data' => "confirmchangeloccha_" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textchange, $keyboardextend);
} elseif (preg_match('/confirmchangeloccha_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchangeloccha on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchangeloccha',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $marzban_list_get_new = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    $limitfree = true;
    if ($user['limitchangeloc'] < $limitchangeloc['free'] and intval($setting['statuslimitchangeloc']) == 1) {
        $limitfree = false;
    }
    if ($user['limitchangeloc'] >= $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_limit_reached'] ?? "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
        return;
    }
    if ($marzban_list_get_new['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
        return;
    }
    if ($marzban_list_get_new == false) {
        sendmessage($from_id, $datatextbot['dyn_errors_generic_restart_process'] ?? "❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید", null, 'html');
        return;
    }
    $Pricechange = $marzban_list_get_new['priceChangeloc'];
    if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
        $prodcut['code_product'] = "🛍 حجم دلخواه";
        $product['inbounds'] = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND name_product = :name_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            'name_product' => $nameloc['name_product'],
            ':agent' => $user['agent'],
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product['inbounds'] != null) {
        $marzban_list_get_new['inboundid'] = $prodcut['inbounds'];
    }
    if ($marzban_list_get_new['type'] == "Manualsale" && $marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        sendmessage($from_id, $datatextbot['dyn_errors_transfer_panel_unavailable'] ?? "❌ امکان انتقال به پنل وجود ندارد.", null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_transfer_unused_config_only'] ?? "❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] != "active") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($limitfree == false) {
        $Pricechange = 0;
    }
    if (rx_ck_available_for_user($user) < $Pricechange && $user['agent'] != "n2" && $limitfree) {
        $marzbandirectpay = panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($Pricechange * $user['pricediscount']) / 100;
                $Pricechange = $Pricechange - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($Pricechange) != 0) {
                $Balance_prim = $Pricechange - rx_ck_available_for_user($user);
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                update("user", "Processing_value_tow", "", "id", $from_id);
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                step('get_step_payment', $from_id);
                return;
            }
        }
    }
    if (intval($user['pricediscount']) != 0 and intval($Pricechange) != 0) {
        $result = ($Pricechange * $user['pricediscount']) / 100;
        $Pricechange = $Pricechange - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $Pricechange) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $value = json_encode(array(
        "old_panel" => $marzban_list_get['code_panel'],
        "new_panel" => $marzban_list_get_new['code_panel'],
        "volume" => $DataUserOut['data_limit'],
        "used_traffic" => $DataUserOut['used_traffic'],
        "expire" => $DataUserOut['expire'],
        "stateus" => $DataUserOut['status']
    ));
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $type = "change_location";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product']);
    $stmt->execute();
    $stmt->close();
    if ($DataUserOut['data_limit'] == 0 || $DataUserOut['data_limit'] == null) {
        $data_limit = 0;
    } else {
        $data_limit = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    }
    $datac = array(
        'expire' => $DataUserOut['expire'],
        'data_limit' => $data_limit,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest',
        'ip_limit' => intval($nameloc['ip_limit'] ?? 0),
    );
    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";
    if ($marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
    } else {
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "خطا هنگام تغییر موقعیت سرویس
<blockquote>دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام پنل مقصد : {$marzban_list_get_new['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    }
    $output_config_link = "";
    if ($marzban_list_get_new['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get_new['config'] == "onconfig") {
        if (is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $configs) {
                $output_config_link .= "\n" . $configs;
            }
        }
    }
    $limitnew = $user['limitchangeloc'] + 1;
    update("user", "limitchangeloc", $limitnew, "id", $from_id);
    $textchangeloc = "✅ کانفیگ شما باموفقیت به سرور ({$marzban_list_get_new['name_panel']}) انتقال یافت.

🖥 نام سرویس : {$nameloc['username']}
💠 حجم سرویس : $RemainingVolume
⏳ زمان انقضا :  $expirationDate | $day

🔗 لینک اشتراک شما:

<code>$output_config_link</code>";
    if (intval($Pricechange) != 0) {
        $__allowNegPc = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargePc = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $Pricechange, $__allowNegPc) : ['ok' => false];
        if (empty($__chargePc['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargePc['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $Pricechange, 'service_action', 'تغییر موقعیت سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    }
    update("invoice", "Service_location", $marzban_list_get_new['name_panel'], "username", $nameloc['username']);
    if ($marzban_list_get_new['inboundid'] != null) {
        update("invoice", "inboundid", $marzban_list_get_new['inboundid'], "username", $nameloc['username']);
    }
    Editmessagetext($from_id, $message_id, $textchangeloc, $keyboardextend);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $format_byte = formatBytes($data_limit);
    $textreport = "
تغییر موقعیت سرویس

<blockquote>🔻آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>🔻نام کاربری : @$username</blockquote>
<blockquote>🔻نام پنل قدیم : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>🔻نام پنل جدید : {$marzban_list_get_new['name_panel']}</blockquote>
<blockquote>🔻 نام کاربری مشتری در پنل  :{$nameloc['username']}</blockquote>
<blockquote>🔻حجم نهایی سرویس : $format_byte</blockquote>
<blockquote>🔻موجودی کاربر : $balanceformatsell تومان</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/disorder-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler disorder on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'disorder',
            ]);
        }
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ علت اختلال خود را بنویسید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textdisorder, $keyboarddisorder);
    step("getdesdisorder", $from_id);
} elseif ($user['step'] == "getdesdisorder") {
    update("user", "Processing_value", $text, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $textdisorder = "❓ آیا از ارسال گزارش اختلال اطمینان دارید

🔹 قبل از اینکه گزارشی ارسال بکنید آموزش های اتصال را مشاهده کنید. ( /help )";
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ ارسال گزارش اختلال", 'callback_data' => "confirmdisorders-" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    sendmessage($from_id, $textdisorder, $keyboarddisorder, 'html');
    step("home", $from_id);
} elseif (preg_match('/confirmdisorders-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmdisorders on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmdisorders',
            ]);
        }
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    $textdisorder = "
    ⚠️ یک کاربر با اطلاعات زیر یک گزارش اختلال در سرویس ثبت کرده است .

- نام کاربری : @$username
- آیدی عددی : $from_id
- نام کاربری کانفیگ : {$nameloc['username']}
- نام پلن تهیه شده : {$nameloc['name_product']}
- موقعیت سرویس : {$nameloc['Service_location']}
- توضیحات اختلال : {$user['Processing_value']}";
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);

    $status = $DataUserOut['status'];
    $bExp3 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl3 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt3 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp3 > 0 && $bExp3 <= time()) {
        $status = 'expired';
    } elseif ($bDl3 > 0.0 && $bUt3 >= $bDl3) {
        $status = 'limited';
    }
    $status_map3 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map3[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $textdisorder .= "

 وضعیت سرویس : $status_var

🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر :
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
    foreach ($admin_ids as $admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        sendmessage($admin, $textdisorder, $Response, 'html');
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_request_thanks'] ?? "✅ با تشکر از ثبت درخواست ،درخواست شما  ارسال شده و درحال بررسی توسط پشتیبانی می باشد.", $bakinfos, 'html');
} elseif (preg_match('/Extra_time_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler Extra_time on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'Extra_time',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_unavailable_panel'] ?? "❌ امکان خرید زمان اضافه در این پنل وجود ندارد", null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $datatextbot['dyn_errors_not_connected_extend'] ?? "❌ هنوز به سرویس متصل نشده اید برای تمدید سرویس ابتدا به سرویس متصل شوید سپس اقدام به تمدید کنید", null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = "📆 تعداد روز اضافه مورد نظر را وارد کنید ( برحسب روز ) :

📌 تعرفه هر روز:  $extratimepricevalue";
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('gettimeextra', $from_id);
} elseif ($user['step'] == "gettimeextra") {
    if (!ctype_digit($text) || $text < 1) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    $priceextratime = $extratimepricevalue * $text;
    $priceextratime = fx_finalize_amount($priceextratime, $marzban_list_get, 'extra_time');
    fx_quote_store_server($from_id, fx_quote_context_key('extra_time', (string) $nameloc['id_invoice']), $priceextratime, $marzban_list_get, 'extra_time', ['qty' => (int) $text]);
    $__timeSourceProduct = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__timeCategory = is_array($__timeSourceProduct) ? (string)($__timeSourceProduct['category'] ?? '') : '';
    $__discEligibleTime = MiniDiscount::hasEligible('time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory, $user);
    $__timeRows = [
        [
            ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $priceextratime],
        ],
    ];
    if ($__discEligibleTime) {
        $__timeRows[] = [
            ['text' => "🎁 ثبت کد تخفیف", 'callback_data' => 'discounttime-' . $priceextratime],
        ];
    }
    $keyboardsetting = json_encode(['inline_keyboard' => $__timeRows]);
    $priceextratime = number_format($priceextratime, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = "📜 فاکتور خرید زمان اضافه برای شما ایجاد شد.

📌 تعرفه هر روز زمان اضافه : $extratimepricevalue تومان
📆 تعداد روز اضافه درخواستی : $text روز
💰 مبلغ فاکتور شما : $priceextratime تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/discounttime-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_four", "pretime_" . $dataget[1], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscounttime', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscounttime") {
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $__pv4 = (string)$user['Processing_value_four'];
    $__baseTime = (strpos($__pv4, 'pretime_') === 0) ? intval(substr($__pv4, 8)) : 0;
    if ($__baseTime <= 0) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__timeSourceProduct2 = select("product", "*", "name_product", $nameloc['name_product'] ?? '', "select");
    $__timeCategory2 = is_array($__timeSourceProduct2) ? (string)($__timeSourceProduct2['category'] ?? '') : '';
    $dv = MiniDiscount::validateSell($text, 'time', '', (string)($marzban_list_get['code_panel'] ?? ''), $__timeCategory2, $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    if (!fx_quote_server_amount_matches($from_id, fx_quote_context_key('extra_time', (string) $nameloc['id_invoice']), $__baseTime) || !fx_quote_check($from_id, fx_quote_context_key('extra_time', (string) $nameloc['id_invoice']), $__baseTime, $marzban_list_get, 'extra_time')) {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $__discounted = (int)round(MiniDiscount::applyToPrice($dv['row'], $__baseTime));
    if ($__discounted < 0) $__discounted = 0;
    $__discounted = fx_finalize_amount($__discounted, $marzban_list_get, 'extra_time');
    update("user", "Processing_value_four", "distime_" . $text . "_" . $__baseTime . "_" . $__discounted, "id", $from_id);
    $__basefmt = number_format($__baseTime, 0);
    $__disfmt  = number_format($__discounted, 0);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratimediscount-' . $__baseTime],
            ]
        ]
    ]);
    $textextra = "🤩 کد تخفیف {$dv['label']} روی فاکتور شما اعمال شد.

💰 مبلغ قبل از تخفیف : {$__basefmt} تومان
💸 مبلغ قابل پرداخت : {$__disfmt} تومان

✅ جهت پرداخت و اضافه شدن زمان، روی دکمه زیر کلیک کنید";
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextratime(discount)?-(\w+)/', $datain, $dataget)) {
    $tmieextra = $dataget[2];
    $__timeDiscountCode = '';
    $__timeCharge = (float)$tmieextra;
    if (($dataget[1] ?? '') === 'discount') {
        $__pv4 = (string)$user['Processing_value_four'];
        if (strpos($__pv4, 'distime_') === 0) {
            $__pp = explode('_', $__pv4);
            $__timeDiscountCode = $__pp[1] ?? '';
            if (isset($__pp[3])) $__timeCharge = (float)$__pp[3];
        }
    }
    $pricelasttime = $__timeCharge;
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_failed_restart'] ?? "❌ خرید با خطا مواجه گردید مراحل را مجدد انجام  دهید.", null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_time');
    $fxTimeVerify = fx_extra_quote_verify($from_id, fx_quote_context_key('extra_time', (string) $nameloc['id_invoice']), $extratimepricevalue, $tmieextra, $marzban_list_get, 'extra_time');
    if ($fxTimeVerify['status'] === 'missing') {
        sendmessage($from_id, $datatextbot['dyn_errors_extra_time_restart_process'] ?? "❌ مراحل خرید زمان اضافه را مجددا انجام دهید", $keyboard, 'HTML');
        return;
    }
    if ($fxTimeVerify['status'] === 'changed') {
        sendmessage($from_id, fx_price_changed_text($fxTimeVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_time']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $fxTimeVerify['amount']]]]]), 'HTML');
        return;
    }
    $fxTimeQty = $fxTimeVerify['qty'];
    if (rx_ck_available_for_user($user) < $__timeCharge && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuetime = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
            if (intval($user['pricediscount']) != 0) {
                $result = ($__timeCharge * $user['pricediscount']) / 100;
                $pricelasttime = $__timeCharge - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $pricelasttime = fx_finalize_amount($pricelasttime, $marzban_list_get, 'extra_time');
            if (intval($pricelasttime) != 0) {
                rx_ck_bot_begin($user, [
                    'operation' => 'getextratimeuser',
                    'op_key' => "getextratimeuser|{$nameloc['username']}%{$valuetime}",
                    'target' => (string) $nameloc['username'],
                    'final_price' => $pricelasttime,
                    'message_id' => $message_id,
                    'one' => "{$nameloc['username']}%{$valuetime}",
                    'tow' => 'getextratimeuser',
                    'four' => $__timeDiscountCode !== '' ? "dtime|" . $__timeDiscountCode . "|" . $tmieextra : '',
                ]);
                return;
            }
        }
    }
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0 and intval($pricelasttime) != 0) {
        $result = ($__timeCharge * $user['pricediscount']) / 100;
        $pricelasttime = $__timeCharge - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $pricelasttime = fx_finalize_amount($pricelasttime, $marzban_list_get, 'extra_time');
    $Balance_Low_user = $user['Balance'] - $pricelasttime;
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if ($Balance_Low_user < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }

    $__allowNegEt = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    if ($pricelasttime > 0) {
        $__chargeEt = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $pricelasttime, $__allowNegEt) : ['ok' => false];
        if (empty($__chargeEt['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEt['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $pricelasttime, 'service_action', 'خرید زمان اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'];
    }
    $__chargedEtUser = true;
    update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
    $extratimeday = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'day' => $extratimeday,
        'priceـper_day' => $extratimeday,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $data_for_database = fx_value_with_snapshot($data_for_database, fx_pricing_snapshot($marzban_list_get, 'extra_time', ($fxTimeQty ?? 0) * (fx_panel_raw_unit_price($marzban_list_get, 'priceextratime', $user['agent']) ?? 0), $pricelasttime, (float) $tmieextra - (float) $pricelasttime, ['quantity' => $fxTimeQty, 'unit_price' => $extratimepricevalue]));
    $timeservice = $DataUserOut['expire'] - time();
    $day = floor($timeservice / 86400);
    $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $extratimeday);
    if ($extra_time['status'] == false) {
        $extra_time['msg'] = rx_panel_error_text($extra_time['msg'] ?? null, $extra_time['detail'] ?? null);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_time['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }

        if (!empty($__chargedEtUser) && function_exists('balance_atomic_credit')) {
            $__refundedEt = balance_atomic_credit($from_id, $pricelasttime);
            if ($__refundedEt && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $pricelasttime, 'refund', 'بازگشت وجه زمان اضافه', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
            }
        }
        return;
    }

    MiniDiscount::logSale([
        'id_user' => $from_id,
        'id_invoice' => $nameloc['id_invoice'] ?? null,
        'Service_location' => $nameloc['Service_location'],
        'kind' => 'time',
        'name_product' => $nameloc['name_product'] ?? null,
        'amount' => $pricelasttime,
        'source' => 'bot',
    ]);
    if ($__timeDiscountCode !== '') {
        MiniDiscount::markSellUsed($__timeDiscountCode, $user);
        MiniDiscount::logOrderDiscount([
            'id_user' => $from_id,
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'code' => $__timeDiscountCode,
            'kind' => 'sell',
            'price_before' => $tmieextra,
            'discount_amount' => (float)$tmieextra - (float)$pricelasttime,
            'price_after' => $pricelasttime,
            'section' => 'time',
        ]);
        update("user", "Processing_value_four", "", "id", $from_id);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_time_user";
    $output = json_encode($extra_time);
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $pricelasttime,
        ':output' => $output,
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($tmieextra);
    $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$nameloc['username']}
▫️زمان اضافه : $extratimeday روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
    sendmessage($from_id, $textextratime, $keyboardextrafnished, 'HTML');
    $volumes = $fxTimeQty ?? $tmieextra / $extratimepricevalue;
    $text_report = "⭕️ یک کاربر زمان اضافه خریده است

اطلاعات کاربر :
<blockquote>🪪 آیدی عددی : $from_id</blockquote>
<blockquote>🛍 زمان خریداری شده  : $volumes روز</blockquote>
<blockquote>💰 مبلغ پرداختی : $volumesformat تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ : {$nameloc['username']}</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('time_extra', [
            'user_id' => $from_id,
            'amount'  => $volumes,
            'price'   => $volumesformat,
        ], $setting);
    }
} elseif (preg_match('/deletelist-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (is_array($nameloc) && (string) $nameloc['id_user'] === (string) $from_id) {
        if (function_exists('deleteInvoiceFromList')) {
            deleteInvoiceFromList($id_invoice, $from_id);
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :invoice_id AND id_user = :user_id");
            $stmtDelete->bindParam(':invoice_id', $id_invoice);
            $stmtDelete->bindParam(':user_id', $from_id);
            $stmtDelete->execute();
        }
        sendmessage($from_id, $datatextbot['dyn_errors_service_removed'] ?? "📌 سرویس از لیست شما حذف شد", null, 'html');
    } else {
        sendmessage($from_id, $datatextbot['dyn_errors_service_removal_unavailable'] ?? "❌ امکان حذف سرویس وجود ندارد.", null, 'html');
    }
} elseif (preg_match('/removeserviceuser_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler removeserviceuser on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'removeserviceuser',
            ]);
        }
        return;
    }
    savedata("clear", "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_errors_removal_reason_prompt'] ?? "📌 دلیل حذف سرویس خود را ارسال کنید.", $bakinfos);
    step("getdisdeleteconfig", $from_id);
} elseif ($user['step'] == "getdisdeleteconfig") {
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    savedata("save", "descritionsremove", $text);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorusertest'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        step("home", $from_id);
        return;
    }
    $requestcheck = select("cancel_service", "*", "username", $nameloc['username'], "count");
    if ($requestcheck != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['errorexits'], null, 'html');
        return;
    }
    $confirmremove = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅  درخواست حذف سرویس را دارم", 'callback_data' => "confirmremoveservices-$id_invoice"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['stateus']['descriptions_removeservice'], $confirmremove, "html");
    step("home", $from_id);
} elseif (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user = :from_id AND status = 'waiting'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $checkcancelservicecount = $stmt->rowCount();
    if ($checkcancelservicecount != 0) {
        sendmessage($from_id, $textbotlang['users']['stateus']['exitsrequsts'], null, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmremoveservices on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmremoveservices',
            ]);
        }
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $stmt = $connect->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
    $descriptions = "0";
    $Status = "waiting";
    $stmt->bind_param("ssss", $from_id, $nameloc['username'], $descriptions, $Status);
    $stmt->execute();
    $stmt->close();
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['panelNotConnected'], null, 'html');
        step('home', $from_id);
        return;
    }

    $lastonline = formatOnlineAtLabel($DataUserOut['online_at'] ?? null, $DataUserOut['is_online'] ?? null);
    $status = $DataUserOut['status'];
    $bExp4 = is_numeric($DataUserOut['expire'] ?? null) ? (int)$DataUserOut['expire'] : 0;
    $bDl4 = is_numeric($DataUserOut['data_limit'] ?? null) ? (float)$DataUserOut['data_limit'] : 0.0;
    $bUt4 = is_numeric($DataUserOut['used_traffic'] ?? null) ? (float)$DataUserOut['used_traffic'] : 0.0;
    if ($bExp4 > 0 && $bExp4 <= time()) {
        $status = 'expired';
    } elseif ($bDl4 > 0.0 && $bUt4 >= $bDl4) {
        $status = 'limited';
    }
    $status_map4 = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'end_of_volume' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'end_of_time' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'send_on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown']
    ];
    $status_var = $status_map4[$status] ?? ($textbotlang['users']['stateus']['active'] ?? 'فعال');

    $expirationDate = $DataUserOut['expire']
        ? jdate('Y/m/d', $DataUserOut['expire'])
        : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);

    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];

    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";

    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];

    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];

    $textinfoadmin = "سلام ادمین 👋

📌 یک درخواست حذف سرویس  توسط کاربر برای شما ارسال شده است. لطفا بررسی کرده و در صورت درست بودن و موافقت تایید کنید.

📊 اطلاعات سرویس کاربر :
آیدی عددی کاربر : $from_id
نام کاربری کاربر : @$username
نام کاربری کانفیگ : {$nameloc['username']}
وضعیت سرویس : $status_var
موقعیت سرویس : {$nameloc['Service_location']}
کد سرویس:{$nameloc['id_invoice']}

🟢 اخرین زمان اتصال شما : $lastonline

📥 حجم مصرفی : $usedTrafficGb
♾ حجم سرویس : $LastTraffic
🪫 حجم باقی مانده : $RemainingVolume
📅 فعال تا تاریخ : $expirationDate ($day)

<b>❌ ادمین گرامی توجه داشته باشید دکمه حذف سرویس که میزنید ربات خودکار حساب میکند و احتمال اشتباه وجود دارد پیشنهاد می شود از  حذف دستی  استفاده نمایید</b>

دلیل حذف سرویس : {$userdata['descritionsremove']}";
    $confirmremoveadmin = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌حذف دستی", 'callback_data' => "remoceserviceadminmanual-{$nameloc['id_invoice']}"],
                ['text' => "❌حذف سرویس", 'callback_data' => "remoceserviceadmin-{$nameloc['id_invoice']}"],
                ['text' => "❌لغو حذف", 'callback_data' => "rejectremoceserviceadmin-{$nameloc['id_invoice']}"],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
        step("home", $admin);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['stateus']['sendrequestsremove'], $keyboard, 'html');
} elseif (preg_match('/transfer_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler transfer on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'transfer',
            ]);
        }
        return;
    }
    if ($nameloc['name_product'] == "سرویس تست") {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['transfornotvalid'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['discription'], $bakinfos);
    step("getidfortransfer", $from_id);
    update("user", "Processing_value_one", $nameloc['username'], "id", $from_id);
    update("user", "Processing_value_tow", $nameloc['id_invoice'], "id", $from_id);
} elseif ($user['step'] == "getidfortransfer") {
    if (!userExists($text)) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notusertrns'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $confirmtransfer = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید انتقال سرویس", 'callback_data' => "confrimtransfers_{$user['Processing_value_tow']}"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['transfor']['confirm'], $confirmtransfer, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/confrimtransfers_(\w+)/', $datain, $dataget)) {
    if ($from_id == $user['Processing_value_one']) {
        sendmessage($from_id, $textbotlang['Admin']['transfor']['notsendserviceyou'], $keyboard, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");

    if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confrimtransfers on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confrimtransfers',
            ]);
        }
        return;
    }
    update("invoice", "id_user", $user['Processing_value_one'], "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "backorder"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['transfor']['confirmed'], $bakinfos);
    $texttransfer = "✅ کاربر گرامی  سرویس با نام کاربری {$nameloc['username']} از طرف کاربر با شناسه کاربری $from_id  به حساب کاربری شما منتقل گردید.";
    sendmessage($user['Processing_value_one'], $texttransfer, $keyboard, 'HTML');
    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $value = $user['Processing_value_one'];
    $dateacc = date('Y/m/d H:i:s');
    $type = "transfertouser";
    $price = "0";
    $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $price);
    $stmt->execute();
    $stmt->close();
} elseif (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest") {
    if (!check_active_btn($setting['keyboardmain'], "text_usertest")) {
        sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $rxTestEntryUser = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    if (!is_array($rxTestEntryUser) || empty($rxTestEntryUser)) {
        $rxTestEntryUser = $user;
    }
    $rxTestEntryAdmin = in_array($from_id, $admin_ids);
    $rxTestEntryOverview = rx_test_overview($rxTestEntryUser, $rxTestEntryAdmin);
    $locationproduct = count($rxTestEntryOverview['panels']);
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($locationproduct != 1) {
        $rxTestEntryGate = rx_test_verification_gate($rxTestEntryUser, $rxTestEntryAdmin);
        if ($rxTestEntryGate === 'phone') {
            if ($user['step'] != "get_number") {
                sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
                step('get_number', $from_id);
            }
            return;
        }
        if ($rxTestEntryGate === 'verify') {
            sendmessage($from_id, $datatextbot['dyn_testaccount_verify_required'] ?? "⚠️ حساب شما هنوز احراز هویت نشده است.", null, 'HTML');
            return;
        }
        if (!$rxTestEntryOverview['any_available']) {
            sendmessage($from_id, $rxTestEntryOverview['reason'] === 'audience_restricted' ? rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
            return;
        }
        $rxTestEntryKb = ['inline_keyboard' => []];
        foreach ($rxTestEntryOverview['panels'] as $rxTestEntryItem) {
            if ($rxTestEntryItem['quota']['can_create']) {
                $rxTestEntryKb['inline_keyboard'][] = [['text' => $rxTestEntryItem['panel']['name_panel'], 'callback_data' => "locationtest_{$rxTestEntryItem['panel']['code_panel']}"]];
            }
        }
        $rxTestEntryKb['inline_keyboard'][] = [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]];
        rx_section_media_enter('test', $from_id, $datain, $message_id, ['usertestbtn']);
        sendmessage($from_id, $datatextbot['textselectlocation'], json_encode($rxTestEntryKb), 'html');
    }
}

if (!defined('RX_MIN_TRANSFER_AMOUNT')) {
    define('RX_MIN_TRANSFER_AMOUNT', 1000);
}

if (!function_exists('tk_miniapp_ticket_mode_active')) {
    function tk_miniapp_ticket_mode_active()
    {
        global $setting;
        return (string)($setting['miniapp_ticket_mode'] ?? '0') === '1';
    }
}

if (!function_exists('tk_send_miniapp_redirect')) {
    function tk_send_miniapp_redirect($from_id, $message_id, $user)
    {
        global $datatextbot, $domainhosts;
        $text = trim((string)($datatextbot['miniapp_ticket_suggest'] ?? ''));
        if ($text === '') { $text = '🎫 ارسال و پیگیری تیکت‌های پشتیبانی فقط از طریق مینی‌اپ امکان‌پذیر است.'; }
        $first = trim((string)($user['first_name'] ?? ''));
        $text = str_replace('{first_name}', $first !== '' ? $first : 'کاربر', $text);
        $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
        $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => '🚀 باز کردن مینی‌اپ', 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets']]]]], JSON_UNESCAPED_UNICODE) : null;
        if (intval($message_id) > 0) {
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        } else {
            sendmessage($from_id, $text, $kb, 'HTML');
        }
    }
}

if (!function_exists('tk_album_send')) {
    function tk_album_send($chat, $topic, $media)
    {
        if (!is_array($media) || count($media) === 0) return;
        if (count($media) === 1) {
            $m = $media[0];
            $p = ['chat_id' => $chat, $m['type'] => $m['file_id']];
            if ($topic) $p['message_thread_id'] = $topic;
            telegram($m['type'] === 'video' ? 'sendvideo' : 'sendphoto', $p);
            return;
        }
        $arr = [];
        foreach ($media as $m) { $arr[] = ['type' => $m['type'], 'media' => $m['file_id']]; }
        $p = ['chat_id' => $chat, 'media' => $arr];
        if ($topic) $p['message_thread_id'] = $topic;
        telegram('sendMediaGroup', $p);
    }
}

$tkCancelKb = json_encode(['inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE);

if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || (($text !== '' && $text == $datatextbot['text_usertest']) || $datain == "usertestbtn" || $text == "usertest")) {
    $rxTestIsAdmin = in_array($from_id, $admin_ids);
    $userlimit = select("user", "*", "id", $from_id, "select", ['cache' => false]);
    if (!is_array($userlimit) || empty($userlimit)) {
        $userlimit = $user;
    }
    if (!rx_test_feature_enabled()) {
        sendmessage($from_id, $datatextbot['dyn_errors_test_service_unavailable'] ?? "📌 سرویس تست در حال حاضر در دسترس نیست .", null, 'HTML');
        return;
    }
    $rxTestGate = rx_test_verification_gate($userlimit, $rxTestIsAdmin);
    if ($rxTestGate === 'phone') {
        if ($user['step'] != "get_number") {
            sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
            step('get_number', $from_id);
        }
        return;
    }
    if ($rxTestGate === 'verify') {
        sendmessage($from_id, $datatextbot['dyn_testaccount_verify_required'] ?? "⚠️ حساب شما هنوز احراز هویت نشده است.", null, 'HTML');
        return;
    }
    $rxTestCode = null;
    if (preg_match('/^locationtest_(.+)$/', (string) $datain, $rxTestMatch)) {
        $rxTestCode = $rxTestMatch[1];
    } elseif ($user['step'] == "createusertest") {
        $rxTestCode = (string) $user['Processing_value_one'];
    }
    $rxTestEligible = rx_test_eligible_panels($userlimit);
    if ($rxTestCode === null && count($rxTestEligible) !== 1) {
        return;
    }
    $marzban_list_get = rx_test_resolve_panel($userlimit, $rxTestCode, $rxTestEligible);
    if ($marzban_list_get === null) {
        if ($user['step'] == "createusertest") {
            step('home', $from_id);
        }
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    $rxTestSettings = rx_test_panel_settings($marzban_list_get);
    $rxTestQuota = rx_test_quota_state($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin);
    if (!$rxTestQuota['can_create']) {
        if ($user['step'] == "createusertest") {
            step('home', $from_id);
        }
        sendmessage($from_id, $rxTestQuota['reason'] === 'audience_restricted' ? rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    if ($user['step'] != "createusertest" && strpos((string) $datain, 'locationtest_') !== 0) {
        rx_section_media_enter('test', $from_id, $datain, $message_id, ['usertestbtn']);
    }
    $location = $marzban_list_get['code_panel'];
    $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        if ($user['step'] != "createusertest") {
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $location, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_usernameKb, 'html');
            return;
        }
    }
    if ($user['step'] == "createusertest") {
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
    } else {
        deletemessage($from_id, $message_id);
    }
    if (!rx_test_manual_stock_available($marzban_list_get)) {
        step('home', $from_id);
        sendmessage($from_id, faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
        return;
    }
    $rxTestReserveError = '';
    $rxTestReservation = rx_test_reserve($userlimit, $marzban_list_get, $rxTestSettings, $rxTestIsAdmin, 'bot', $rxTestReserveError);
    if ($rxTestReservation === null) {
        step('home', $from_id);
        if ($rxTestReserveError === 'panel_unavailable') {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
            return;
        }
        sendmessage($from_id, $rxTestReserveError === 'audience_restricted' ? rx_test_audience_denied_text() : $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    $randomString = bin2hex(random_bytes(4));
    $text = strtolower((string) $text);
    try {
        $username_ac = rx_test_build_username($userlimit, $marzban_list_get, $text, $randomString);
    } catch (Throwable $rxTestErr) {
        rx_test_reservation_update($rxTestReservation, 'failed');
        rx_test_release($rxTestReservation);
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        return;
    }
    $rxTestProvision = rx_test_provision($userlimit, $marzban_list_get, $rxTestSettings, $rxTestReservation, $username_ac, $randomString);
    if (empty($rxTestProvision['ok'])) {
        step('home', $from_id);
        if ($rxTestProvision['error'] === 'stock_empty') {
            sendmessage($from_id, faoxima_textbot_get('dyn_testaccount_manualsale_stock_depleted', "❌ موجودی این سرویس به پایان رسیده."), null, 'HTML');
            return;
        }
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $rxTestErrReason = htmlspecialchars((string) $rxTestProvision['msg'], ENT_QUOTES, 'UTF-8');
        $texterros = "
⭕️ یک کاربر قصد دریافت اکانت  تست داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
<blockquote>✍️ دلیل خطا :
{$rxTestErrReason}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $dataoutput = $rxTestProvision['output'];
    $username_ac = (string) ($rxTestProvision['username'] ?? $username_ac);
    rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
    $output_config_link = "";
    $config = "";
    $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }

    $usertestinfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    if ($marzban_list_get['type'] == "WGDashboard" || trim((string) ($datatextbot['textaftertext'] ?? '')) === "") {
        $datatextbot['textaftertext'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  ساعت
🗜 حجم سرویس:  {volume} مگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
    }
    $usertest_day = rx_test_display_time($rxTestSettings);
    $usertest_volume = rx_test_display_volume($rxTestSettings);
    $textcreatuser = str_replace('{username}', guardDisplayUsername($dataoutput['username'], $marzban_list_get), $datatextbot['textaftertext']);
    $textcreatuser = str_replace('{name_service}', "تست", $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $usertest_day, $textcreatuser);
    $textcreatuser = preg_replace('/\{volume\}[ \t\x{200c}]*(?:گیگابایت|گیگ|GB|مگابایت|مگ|MB)?/iu', $usertest_volume, $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    $rxTestDelivered = true;
    try {
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString);
    } catch (Throwable $rxTestErr) {
        $rxTestDelivered = false;
        error_log('[usertest] delivery failed: ' . $rxTestErr->getMessage());
    }
    rx_test_mark_delivery($rxTestReservation, $rxTestDelivered);
    if (!$rxTestDelivered) {
        sendmessage($from_id, $datatextbot['dyn_testaccount_delivery_failed_saved'] ?? "⚠️ اکانت تست ساخته شد اما ارسال جزئیات آن ناموفق بود. اطلاعات سرویس در بخش «سرویس‌های من» در دسترس است.", null, 'HTML');
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    step('home', $from_id);
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $rxTestReportTime = !empty($rxTestSettings['time_unlimited']) ? rx_test_unlimited_label() : rx_test_stored_hours($rxTestSettings) . " ساعت";
    $rxTestReportVolume = !empty($rxTestSettings['volume_unlimited']) ? rx_test_unlimited_label() : rx_test_stored_mb($rxTestSettings) . " MB";
    $text_report = "📣 جزئیات ساخت اکانت تست در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>▫️زمان خریداری شده : {$rxTestReportTime}</blockquote>
<blockquote>▫️حجم خریداری شده : {$rxTestReportVolume}</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif ($datain === 'tpc_list') {
    if ($user['step'] == "tpc_receipt") {
        step('home', $from_id);
    }
    $tpcListView = tonpayCustomBotListView($from_id);
    if (function_exists('atlaspayCustomBotListMerge')) {
        $tpcListView = atlaspayCustomBotListMerge($from_id, $tpcListView);
    }
    Editmessagetext($from_id, $message_id, $tpcListView['text'], $tpcListView['keyboard'], 'HTML');
    tonpayCustomBotAnswer($callback_query_id);
} elseif (preg_match('/^apc_(chk|can|cny|view)_(\w+)$/', (string) $datain, $apcMatch)) {
    $apcAction = $apcMatch[1];
    $apcOrder = $apcMatch[2];
    $apcReport = atlaspayCustomFetchReport($apcOrder);
    if ($apcReport === null || (string) $apcReport['id_user'] !== (string) $from_id) {
        atlaspayCustomBotAnswer($callback_query_id, '❌ این فاکتور پیدا نشد.', true);
        return;
    }
    if ($apcAction == 'view') {
        atlaspayCustomPollThrottled($apcOrder, $from_id);
        atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
        updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $apcOrder);
        atlaspayCustomBotAnswer($callback_query_id);
    } elseif ($apcAction == 'chk') {
        atlaspayCustomPollThrottled($apcOrder, $from_id);
        atlaspayCustomBotRefreshOriginal($from_id, $apcOrder, $message_id);
        atlaspayCustomBotAnswer($callback_query_id, '🔄 وضعیت به‌روزرسانی شد');
    } elseif ($apcAction == 'can') {
        $apcCancelState = atlaspayCustomBuildState($apcReport);
        if (!$apcCancelState['can_cancel']) {
            atlaspayCustomBotAnswer($callback_query_id, atlaspayCustomCancelBlockedMessage($apcCancelState), true);
            return;
        }
        $apcConfirmView = atlaspayCustomBotCancelConfirmView($apcOrder, 'apc_view_' . $apcOrder);
        Editmessagetext($from_id, $message_id, $apcConfirmView['text'], $apcConfirmView['keyboard'], 'HTML');
        atlaspayCustomBotAnswer($callback_query_id);
    } else {
        $apcResult = atlaspayCustomCancel($apcOrder, $from_id);
        if (empty($apcResult['ok'])) {
            atlaspayCustomBotAnswer($callback_query_id, (string) ($apcResult['message'] ?? 'لغو فاکتور انجام نشد.'), true);
            atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
            return;
        }
        atlaspayCustomBotRender($from_id, $message_id, $apcOrder);
        updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $apcOrder);
        atlaspayCustomBotAnswer($callback_query_id, '✅ تراکنش لغو شد');
    }
} elseif (preg_match('/^tpc_(chk|chg|rcp|can|cny|view)_(\w+)$/', (string) $datain, $tpcMatch)) {
    $tpcAction = $tpcMatch[1];
    $tpcOrder = $tpcMatch[2];
    $tpcReport = tonpayCustomFetchReport($tpcOrder);
    if ($tpcReport === null || (string) $tpcReport['id_user'] !== (string) $from_id) {
        tonpayCustomBotAnswer($callback_query_id, '❌ این فاکتور پیدا نشد.', true);
        return;
    }
    if ($user['step'] == "tpc_receipt") {
        step('home', $from_id);
    }
    if ($tpcAction == 'view') {
        tonpayCustomPollThrottled($tpcOrder, $from_id);
        tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
        updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $tpcOrder);
        tonpayCustomBotAnswer($callback_query_id);
    } elseif ($tpcAction == 'cny') {
        $tpcResult = tonpayCustomCancel($tpcOrder, $from_id);
        if (empty($tpcResult['ok'])) {
            tonpayCustomBotAnswer($callback_query_id, (string) ($tpcResult['message'] ?? 'لغو فاکتور انجام نشد.'), true);
            tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
            return;
        }
        tonpayCustomBotRender($from_id, $message_id, $tpcOrder);
        updatePaymentMessageId(['ok' => true, 'result' => ['message_id' => $message_id]], $tpcOrder);
        tonpayCustomBotAnswer($callback_query_id, '✅ تراکنش لغو شد');
    } elseif ($tpcAction == 'chk') {
        tonpayCustomPollThrottled($tpcOrder, $from_id);
        tonpayCustomBotRefreshOriginal($from_id, $tpcOrder, $message_id);
        tonpayCustomBotAnswer($callback_query_id, '🔄 وضعیت به‌روزرسانی شد');
    } elseif ($tpcAction == 'chg') {
        $tpcResult = tonpayCustomChangeCard($tpcOrder, $from_id);
        if (empty($tpcResult['ok'])) {
            tonpayCustomBotAnswer($callback_query_id, (string) ($tpcResult['message'] ?? 'تعویض کارت انجام نشد.'), true);
            return;
        }
        tonpayCustomBotRender($from_id, $message_id, $tpcOrder, $tpcResult['report']);
        tonpayCustomBotAnswer($callback_query_id, '✅ کارت تعویض شد');
    } elseif ($tpcAction == 'can') {
        $tpcCancelState = tonpayCustomBuildState($tpcReport);
        if (!$tpcCancelState['can_cancel']) {
            tonpayCustomBotAnswer($callback_query_id, tonpayCustomCancelBlockedMessage($tpcCancelState), true);
            return;
        }
        $tpcConfirmView = tonpayCustomBotCancelConfirmView($tpcOrder, 'tpc_view_' . $tpcOrder);
        Editmessagetext($from_id, $message_id, $tpcConfirmView['text'], $tpcConfirmView['keyboard'], 'HTML');
        tonpayCustomBotAnswer($callback_query_id);
    } else {
        $tpcReport = tonpayCustomPollThrottled($tpcOrder, $from_id) ?? $tpcReport;
        $tpcGate = tonpayCustomReceiptGate(tonpayCustomBuildState($tpcReport));
        if (!$tpcGate['ok']) {
            tonpayCustomBotAnswer($callback_query_id, $tpcGate['message'], true);
            return;
        }
        step('tpc_receipt', $from_id);
        update("user", "Processing_value", $tpcOrder, "id", $from_id);
        tonpayCustomBotAnswer($callback_query_id);
        sendmessage($from_id, tonpayCustomReceiptPromptText(), json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به وضعیت پرداخت', 'callback_data' => 'tpc_chk_' . $tpcOrder]]]], JSON_UNESCAPED_UNICODE), 'HTML');
    }
} elseif ($user['step'] == "tpc_receipt" && $datain === '' && isset($update['message'])) {
    global $update;
    $tpcOrder = (string) $user['Processing_value'];
    $tpcBackKb = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به وضعیت پرداخت', 'callback_data' => 'tpc_chk_' . $tpcOrder]]]], JSON_UNESCAPED_UNICODE);
    $tpcResult = tonpayCustomBotHandleReceiptUpload($from_id, $tpcOrder, $update['message']);
    if (empty($tpcResult['ok'])) {
        sendmessage($from_id, '❌ ' . ($tpcResult['message'] ?? 'ارسال رسید ناموفق بود.'), $tpcBackKb, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, tonpayCustomReceiptSubmittedText(), null, 'HTML');
    tonpayCustomBotRefreshOriginal($from_id, $tpcOrder, 0);
} elseif (preg_match('/^ckw_(pick|ok|menu|cancel)_/', (string) $datain)) {
    rx_ck_bot_callback($user, (string) $datain, $message_id, $callback_query_id ?? null);
} elseif (($text !== '' && $text == $datatextbot['text_help']) || $datain == "helpbtn" || $datain == "helpbtns" || $datain == "helpmenubtn" || $text == "/help" || $text == "help") {
    if (!check_active_btn($setting['keyboardmain'], "text_help")) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    rx_section_media_enter('help', $from_id, $datain, $message_id, ['helpmenubtn'], ['helpmenubtn']);
    if ($setting['categoryhelp'] == "1") {
        $rxSelectCategoryTxt = $datatextbot['dyn_tickets_select_category'] ?? "📌 یک دسته را انتخاب نمایید";
        if ($datain == "helpbtns" || $datain == "helpmenubtn") {
            Editmessagetext($from_id, $message_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
        } else {
            sendmessage($from_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
        }
    } else {
        $helplist = select("help", "*", null, null, "fetchAll");
        $helpidos = ['inline_keyboard' => []];
        foreach ($helplist as $result) {
            $helpidos['inline_keyboard'][] = [
                ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
            ];
        }
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        $helpidos['inline_keyboard'][] = [
            rx_kb_style(['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s),
        ];
        $json_list_help = json_encode($helpidos);
        if ($datain == "helpbtns" || $datain == "helpmenubtn") {
            Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        } else {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
        }
    }
} elseif (preg_match('/^helpctgoryـ(.*)/', $datain, $dataget)) {
    $helplist = select("help", "*", "category", $dataget[1], "fetchAll");
    $helpidos = ['inline_keyboard' => []];
    foreach ($helplist as $result) {
        $helpidos['inline_keyboard'][] = [
            ['text' => $result['name_os'], 'callback_data' => "helpos_{$result['id']}"]
        ];
    }
    $helpidos['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "helpbtns"],
    ];
    $json_list_help = json_encode($helpidos);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
} elseif (preg_match('/^helpos_(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns"],
            ]
        ]
    ]);
    $helpid = $dataget[1];
    $helpdata = select("help", "*", "id", $helpid, "select");
    if ($helpdata !== false) {
        $helpAppLinkRow = [];
        if (!empty($helpdata['app_link'])) {
            $helpAppLinkRow[] = [
                ['text' => $helpdata['app_title'] ?: 'دانلود برنامه', 'url' => $helpdata['app_link']],
            ];
        }
        if (strlen($helpdata['Media_os']) != 0) {
            if ($helpdata['type_Media_os'] == "video") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendvideo', [
                    'chat_id' => $from_id,
                    'video' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "document") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendDocument', [
                    'chat_id' => $from_id,
                    'document' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            } elseif ($helpdata['type_Media_os'] == "photo") {
                $backinfoss = json_encode([
                    'inline_keyboard' => array_merge($helpAppLinkRow, [
                        [
                            ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn"],
                        ]
                    ])
                ]);
                telegram('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => $helpdata['Media_os'],
                    'caption' => $helpdata['Description_os'],
                    'reply_markup' => $backinfoss,
                    'parse_mode' => "HTML"
                ]);
            }
        } else {
            $backinfoss = json_encode([
                'inline_keyboard' => array_merge($helpAppLinkRow, [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns"],
                    ]
                ])
            ]);
            sendmessage($from_id, $helpdata['Description_os'], $backinfoss, 'HTML');
        }
    }
} elseif (($text !== '' && $text == $datatextbot['text_support']) || $datain == "supportbtns" || $text == "/support") {
    if (!check_active_btn($setting['keyboardmain'], "text_support")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    rx_section_media_enter('support', $from_id, $datain, $message_id, ['supportbtns'], ['supportbtns']);
    if ($datain == "supportbtns") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $supportoption);
    } else {
        sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $supportoption, 'HTML');
    }
} elseif ($datain == "support" || $datain == "supporttickets") {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $myt = $pdo->prepare("SELECT Tracking, name_departman, MAX(id) lid, MAX(time) lt, MAX(status) st FROM support_message WHERE iduser = ? GROUP BY Tracking, name_departman ORDER BY lid DESC LIMIT 20");
    $myt->execute([$from_id]);
    $ticketmenu = ['inline_keyboard' => []];
    foreach ($myt->fetchAll(PDO::FETCH_ASSOC) as $rowt) {
        $stt = $rowt['st'] == 'close' ? '🔒' : '🟢';
        $ticketmenu['inline_keyboard'][] = [['text' => $stt . ' ' . $rowt['name_departman'] . ' | #' . $rowt['Tracking'], 'callback_data' => 'ticketopen_' . $rowt['Tracking']]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $ticketmenu['inline_keyboard'][] = [['text' => '➕ تیکت جدید', 'callback_data' => 'ticketnew']];
    $ticketmenu['inline_keyboard'][] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)];
    $ticketmenu = json_encode($ticketmenu, JSON_UNESCAPED_UNICODE);
    $tickethead = "🎫 تیکت‌های پشتیبانی شما\n\nیک تیکت را برای ادامه انتخاب کنید یا تیکت جدید بسازید.";
    if (!empty($datain) && intval($message_id) > 0) {
        Editmessagetext($from_id, $message_id, $tickethead, $ticketmenu);
    } else {
        sendmessage($from_id, $tickethead, $ticketmenu, 'HTML');
    }
} elseif ($datain == "ticketnew") {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_select_department'] ?? "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.", $list_departman, 'HTML');
} elseif (preg_match('/^departman_(.*)/', $datain, $dataget)) {
    $iddeparteman = $dataget[1];
    savedata("clear", "iddeparteman", $iddeparteman);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_subject'] ?? "📝 موضوع تیکت را وارد نمایید (مثلاً: مشکل در اتصال):", $backuser, 'HTML');
    step("getticketsubj", $from_id);
} elseif ($datain == "tk_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    step("home", $from_id);
    $rxTkCanceledTxt = $datatextbot['dyn_tickets_operation_canceled'] ?? "❌ عملیات لغو شد.";
    if (intval($message_id) > 0) {
        $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
        Editmessagetext($from_id, $message_id, $rxTkCanceledTxt, json_encode(['inline_keyboard' => [[rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
    } else {
        sendmessage($from_id, $rxTkCanceledTxt, $keyboard, 'HTML');
    }
} elseif ($user['step'] == "getticketsubj" && (string)$text !== '') {
    if (!isset($update['message']) && empty($text)) { return; }
    savedata("save", "subject", mb_substr(trim((string)$text), 0, 120, 'UTF-8'));
    savedata("save", "ctx", "create");
    savedata("save", "media", []);
    step("tk_media_choice", $from_id);
    $rxTkMediaYes = $datatextbot['dyn_tickets_media_yes'] ?? "🖼 بله";
    $rxTkMediaNo = $datatextbot['dyn_tickets_media_no'] ?? "✏️ فقط متن";
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_new'] ?? "📎 می‌خواهید همراه تیکت عکس/ویدیو هم بفرستید؟", json_encode(['inline_keyboard' => [[['text' => $rxTkMediaYes, 'callback_data' => 'tk_media_yes'], ['text' => $rxTkMediaNo, 'callback_data' => 'tk_media_no']], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif ($user['step'] == "getticketsubj") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_subject_text'] ?? "❌ لطفاً موضوع تیکت را به‌صورت متن وارد کنید.", null, 'HTML');
    return;
} elseif ($datain == "tk_media_no" && $user['step'] == "tk_media_choice") {
    if (!isset($update['callback_query'])) { return; }
    step("tk_desc", $from_id);
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_body_text_short'] ?? "✍️ متن پیام خود را بنویسید:", $tkCancelKb);
} elseif ($datain == "tk_media_yes" && $user['step'] == "tk_media_choice") {
    if (!isset($update['callback_query'])) { return; }
    step("tk_media_count", $from_id);
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_media_count'] ?? "🔢 چند رسانه می‌خواهید بفرستید؟ (۱ تا ۵)", json_encode(['inline_keyboard' => [[
        ['text' => '۱', 'callback_data' => 'tk_cnt_1'],
        ['text' => '۲', 'callback_data' => 'tk_cnt_2'],
        ['text' => '۳', 'callback_data' => 'tk_cnt_3'],
        ['text' => '۴', 'callback_data' => 'tk_cnt_4'],
        ['text' => '۵', 'callback_data' => 'tk_cnt_5'],
    ], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE));
} elseif (preg_match('/^tk_cnt_([1-5])$/', $datain, $dataget) && $user['step'] == "tk_media_count") {
    if (!isset($update['callback_query'])) { return; }
    savedata("save", "target", (int)$dataget[1]);
    savedata("save", "media", []);
    step("tk_collect", $from_id);
    $rxTkUploadTpl = $datatextbot['dyn_tickets_ask_media_upload'] ?? "📤 رسانهٔ ۱ از %s را بفرستید (فقط عکس یا ویدیو).";
    Editmessagetext($from_id, $message_id, sprintf($rxTkUploadTpl, $dataget[1]), $tkCancelKb);
} elseif (($user['step'] == "tk_media_choice" || $user['step'] == "tk_media_count") && empty($datain)) {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_choice'] ?? "❌ لطفاً از دکمه‌های بالا انتخاب کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
    return;
} elseif ($user['step'] == "tk_collect") {
    global $update;
    $mm = $update['message'] ?? [];
    $badMedia = isset($mm['animation']) || isset($mm['sticker']) || isset($mm['document']) || isset($mm['voice']) || isset($mm['audio']) || isset($mm['forward_date']) || isset($mm['forward_origin']) || isset($mm['forward_from']);
    if ($badMedia || (!$photo && !$video)) {
        sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_type'] ?? "❌ فقط عکس یا ویدیوی معمولی مجاز است (گیف/استیکر/فوروارد/فایل/ویس رد می‌شود). دوباره بفرستید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
        return;
    }
    $ud = json_decode($user['Processing_value'], true);
    if (!is_array($ud)) $ud = [];
    $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
    $media[] = $photo ? ['type' => 'photo', 'file_id' => $photoid] : ['type' => 'video', 'file_id' => $videoid];
    savedata("save", "media", $media);
    $target = (int)($ud['target'] ?? 1);
    if (count($media) < $target) {
        $nx = count($media) + 1;
        $rxTkReceivedTpl = $datatextbot['dyn_tickets_media_received'] ?? "✅ دریافت شد (%s از %s). رسانهٔ بعدی را بفرستید.";
        sendmessage($from_id, sprintf($rxTkReceivedTpl, $nx, $target), $tkCancelKb, 'HTML');
        return;
    }
    step("tk_desc", $from_id);
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_body_text'] ?? "✍️ حالا متن پیام خود را بنویسید:", $tkCancelKb, 'HTML');
} elseif ($user['step'] == "tk_desc" && ((string)$text !== '' || $photo || $video)) {
    if (!isset($update['message']) && empty($text)) { return; }
    $ud = json_decode($user['Processing_value'], true);
    if (!is_array($ud)) $ud = [];
    $ctx = (string)($ud['ctx'] ?? 'create');
    $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
    $desc = (string)$text !== '' ? (string)$text : (string)$caption;
    $mediaTokens = '';
    foreach ($media as $mone) { $mediaTokens .= "\n[[{$mone['type']}:{$mone['file_id']}]]"; }
    $time = date('Y/m/d H:i:s');
    $timejalali = jdate('Y/m/d H:i:s');
    $reportTopic = select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
    $channel = (string)($setting['Channel_Report'] ?? '');
    $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
    if ($ctx === 'reply') {
        $idtraking = (string)($ud['t'] ?? '');
        $tkdetail = select("support_message", "*", "Tracking", $idtraking);
        if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
            sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
            update("user", "Processing_value", "0", "id", $from_id);
            step("home", $from_id);
            return;
        }
        $body = trim($desc . $mediaTokens);
        $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$idtraking, $tkdetail['idsupport'], $from_id, $tkdetail['name_departman'], $body, 'user', $time, 'Customerresponse']);
        update("support_message", "status", "Customerresponse", "Tracking", $idtraking);
        $textreport = "💬 پاسخ جدید کاربر در تیکت\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$tkdetail['name_departman']}\nکد پیگیری : <code>$idtraking</code>\nزمان : $timejalali\n\nمتن : $desc";
        $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $idtraking]]]], JSON_UNESCAPED_UNICODE);
        $pvTargets = [];
        $isp = (string)($tkdetail['idsupport'] ?? '');
        if ($isp !== '' && $isp !== '0') $pvTargets[$isp] = true;
        if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
        foreach (array_keys($pvTargets) as $tg) {
            sendmessage($tg, $textreport, $adminReplyKb, 'HTML');
            tk_album_send($tg, null, $media);
        }
        if ($channel !== '') {
            telegram('sendmessage', ['chat_id' => $channel, 'message_thread_id' => $reportTopic, 'text' => $textreport, 'parse_mode' => 'HTML']);
            tk_album_send($channel, $reportTopic, $media);
        }
        sendmessage($from_id, $datatextbot['dyn_tickets_reply_recorded'] ?? "✅ پیام شما ثبت شد و پس از بررسی پاسخ داده می‌شود.", $keyboard, 'HTML');
    } else {
        $departeman = select("departman", "*", "id", $ud['iddeparteman'] ?? 0, "select");
        if (!is_array($departeman)) $departeman = select("departman", "*", null, null, "select");
        $subject = trim((string)($ud['subject'] ?? ''));
        $randomString = bin2hex(random_bytes(4));
        $subjToken = $subject !== '' ? "[[subj:$subject]]\n" : "";
        $body = trim($subjToken . $desc . $mediaTokens);
        $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$randomString, $departeman['idsupport'], $from_id, $departeman['name_departman'], $body, 'user', $time, 'Unseen']);
        $textreport = "🎫 تیکت جدید پشتیبانی\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$departeman['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nکد پیگیری : <code>$randomString</code>\nزمان : $timejalali\n\nمتن : $desc";
        $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $randomString]]]], JSON_UNESCAPED_UNICODE);
        $pvTargets = [];
        $isp = (string)($departeman['idsupport'] ?? '');
        if ($isp !== '' && $isp !== '0') $pvTargets[$isp] = true;
        if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
        foreach (array_keys($pvTargets) as $tg) {
            sendmessage($tg, $textreport, $adminReplyKb, 'HTML');
            tk_album_send($tg, null, $media);
        }
        if ($channel !== '') {
            telegram('sendmessage', ['chat_id' => $channel, 'message_thread_id' => $reportTopic, 'text' => $textreport, 'parse_mode' => 'HTML']);
            tk_album_send($channel, $reportTopic, $media);
        }
        $rxTkCreatedTpl = $datatextbot['dyn_tickets_created'] ?? "✅ تیکت شما با کد پیگیری <code>%s</code> ثبت شد و پس از بررسی پاسخ داده می‌شود.";
        sendmessage($from_id, sprintf($rxTkCreatedTpl, $randomString), $keyboard, 'HTML');
    }
    update("user", "Processing_value", "0", "id", $from_id);
    step("home", $from_id);
} elseif ($user['step'] == "tk_desc") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $datatextbot['dyn_tickets_invalid_body_text'] ?? "❌ لطفاً متن پیام را وارد کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
    return;
} elseif (preg_match('/^ticketopen_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    $msgs = $pdo->prepare("SELECT * FROM support_message WHERE Tracking = ? ORDER BY id ASC");
    $msgs->execute([$idtraking]);
    $allmsgs = $msgs->fetchAll(PDO::FETCH_ASSOC);
    $subject = '';
    if (isset($allmsgs[0]['text']) && preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$allmsgs[0]['text'], $sm)) {
        $subject = trim($sm[1]);
    }
    $lines = '';
    $hasMedia = false;
    foreach ($allmsgs as $mrow) {
        $who = ($mrow['result'] === 'admin') ? '👨‍💻 پشتیبانی' : '👤 شما';
        if (preg_match('/\[\[(photo|video):[^\]]+\]\]/', (string)$mrow['text'])) $hasMedia = true;
        $bodyline = trim(preg_replace(['/\[\[(photo|video):[^\]]+\]\]/', '/\[\[subj:[^\]]*\]\]/u'], ['🖼 رسانه', ''], (string)$mrow['text']));
        if ($bodyline === '') { $bodyline = '—'; }
        $lines .= "$who:\n$bodyline\n— — —\n";
    }
    $isclosed = $tkdetail['status'] == 'close';
    $statelabel = $isclosed ? '🔒 بسته' : '🟢 باز';
    $tickethead = "🎫 تیکت <code>$idtraking</code>\nدپارتمان : {$tkdetail['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nوضعیت : $statelabel\n\n$lines";
    $kbrows = [];
    if ($hasMedia) {
        $kbrows[] = [['text' => '📎 مشاهده رسانه‌ها', 'callback_data' => 'tk_album_' . $idtraking]];
    }
    if (!$isclosed) {
        $kbrows[] = [['text' => '✍️ ارسال پیام', 'callback_data' => 'ticketmsg_' . $idtraking]];
        $kbrows[] = [['text' => '🔒 بستن تیکت', 'callback_data' => 'ticketclose_' . $idtraking]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $kbrows[] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)];
    Editmessagetext($from_id, $message_id, $tickethead, json_encode(['inline_keyboard' => $kbrows], JSON_UNESCAPED_UNICODE));
} elseif (preg_match('/^(?:ticketmsg|ticketreply)_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    if ($tkdetail['status'] == 'close') {
        sendmessage($from_id, $datatextbot['dyn_tickets_closed_locked'] ?? "🔒 این تیکت بسته است؛ تا زمانی که پشتیبانی پاسخ ندهد یا آن را باز نکند نمی‌توانید پیام ارسال کنید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value", json_encode(['ctx' => 'reply', 't' => $idtraking, 'media' => []], JSON_UNESCAPED_UNICODE), "id", $from_id);
    step("tk_media_choice", $from_id);
    $rxTkMediaYes = $datatextbot['dyn_tickets_media_yes'] ?? "🖼 بله";
    $rxTkMediaNo = $datatextbot['dyn_tickets_media_no'] ?? "✏️ فقط متن";
    $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
    sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_reply'] ?? "📎 می‌خواهید همراه پاسخ عکس/ویدیو هم بفرستید؟", json_encode(['inline_keyboard' => [[['text' => $rxTkMediaYes, 'callback_data' => 'tk_media_yes'], ['text' => $rxTkMediaNo, 'callback_data' => 'tk_media_no']], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif (preg_match('/^tk_album_(\w+)$/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $tkd = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkd) || (string)($tkd['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    $rws = $pdo->prepare("SELECT text FROM support_message WHERE Tracking = ? ORDER BY id ASC");
    $rws->execute([$idtraking]);
    $allMedia = [];
    foreach ($rws->fetchAll(PDO::FETCH_ASSOC) as $rr) {
        if (preg_match_all('/\[\[(photo|video):([^\]]+)\]\]/', (string)$rr['text'], $mmm, PREG_SET_ORDER)) {
            foreach ($mmm as $one) { $allMedia[] = ['type' => $one[1], 'file_id' => $one[2]]; }
        }
    }
    if (count($allMedia) === 0) {
        sendmessage($from_id, $datatextbot['dyn_tickets_no_media'] ?? "🖼 رسانه‌ای در این تیکت نیست.", null, 'HTML');
        return;
    }
    foreach (array_chunk($allMedia, 10) as $chunk) { tk_album_send($from_id, null, $chunk); }
} elseif (preg_match('/^ticketclose_(\w+)$/', $datain, $dataget)) {
    if (tk_miniapp_ticket_mode_active()) {
        tk_send_miniapp_redirect($from_id, $message_id, $user);
        return;
    }
    $idtraking = $dataget[1];
    $tkdetail = select("support_message", "*", "Tracking", $idtraking);
    if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
        return;
    }
    update("support_message", "status", "close", "Tracking", $idtraking);
    $reportTopic = select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
    $closetext = "🔒 کاربر <a href=\"tg://user?id=$from_id\">$from_id</a> تیکت <code>$idtraking</code> را بست.";
    $adminpv = (string)($tkdetail['idsupport'] ?? '');
    $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
    $pvTargets = [];
    if ($adminpv !== '' && $adminpv !== '0') $pvTargets[$adminpv] = true;
    if ($mainadmin !== '' && $mainadmin !== '0') $pvTargets[$mainadmin] = true;
    foreach (array_keys($pvTargets) as $tg) {
        sendmessage($tg, $closetext, null, 'HTML');
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $reportTopic, 'text' => $closetext, 'parse_mode' => 'HTML']);
    }
    $rxTkClosedTpl = $datatextbot['dyn_tickets_closed_confirm'] ?? "🔒 تیکت <code>%s</code> بسته شد. برای ارسال پیام جدید باید پشتیبانی پاسخ دهد یا آن را باز کند.";
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    Editmessagetext($from_id, $message_id, sprintf($rxTkClosedTpl, $idtraking), json_encode(['inline_keyboard' => [[rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
} elseif ($datain == "my_miniapp_pending") {
    $stmtMp = $pdo->prepare(
        "SELECT id_order, price FROM Payment_report
          WHERE id_user = :uid
            AND payment_Status = 'waiting'
            AND Payment_Method = 'cart to cart'
            AND source = 'miniapp'
            AND dec_not_confirmed = 'receipt-submitted'
          ORDER BY id DESC LIMIT 5"
    );
    $stmtMp->execute([':uid' => $from_id]);
    $mpRows = $stmtMp->fetchAll();
    if (empty($mpRows)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ هیچ رسید تایید نشده‌ای از مینی‌اپ ندارید.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
        return;
    }
    $mpText = "⏳ <b>رسیدهای در انتظار تأیید از مینی‌اپ</b>\n\n";
    foreach ($mpRows as $mp) {
        $mpText .= "🛒 کد پیگیری: <code>{$mp['id_order']}</code>\n";
        $mpText .= "💰 مبلغ: " . number_format((int)$mp['price']) . " تومان\n";
        $mpText .= "📌 وضعیت: در انتظار بررسی ادمین\n\n";
    }
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $mpKb = json_encode(['inline_keyboard' => [[rx_kb_style(['text' => '🔙 بازگشت', 'callback_data' => 'account'], 'backinfo', $_rx_acc_s)]]], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, $mpText, $mpKb);
} elseif ($datain == "fqQuestions") {
    sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['accountwallet']) || $datain == "account" || $datain == "accountbtn" || $text == "/wallet") {
    rx_section_media_enter('wallet', $from_id, $datain, $message_id, ['accountbtn'], ['accountbtn']);
    $dateacc = jdate('Y/m/d');
    $current_time = time();
    $timeacc = jdate('H:i:s', $current_time);
    if (!is_string($user['codeInvitation']) || trim($user['codeInvitation']) === '') {
        $user['codeInvitation'] = ensureUserInvitationCode($from_id, $user['codeInvitation'] ?? null);
    }
    $first_name = htmlspecialchars($first_name);
    $Balanceuser = number_format($user['Balance'], 0);
    if ($user['number'] == "none") {
        $numberphone = "🔴 ارسال نشده است 🔴";
    } else {
        $numberphone = $user['number'];
    }
    if ($user['number'] == "confrim number by admin") {
        $numberphone = "✅ تایید شده توسط ادمین";
    } else {
        $numberphone = $numberphone;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND name_product != 'سرویس تست' AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->execute([
        ':id_user' => $from_id
    ]);
    $countorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $countpayment = $stmt->rowCount();
    $groupuser = [
        'f' => "عادی",
        'n' => "نماینده",
        'n2' => "نمایندگی پیشرفته",
    ][$user['agent']];
    $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    if (intval($setting['scorestatus']) == 1) {
        $textscore = "🥅 امتیاز حساب کاربری شما : {$user['score']}";
    } else {
        $textscore = "";
    }
    $textinvite = "";
    if ($setting['verifybucodeuser'] == "onverify" and $setting['verifystart'] == "onverify") {
        $textscore = "

🔗 لینک ریفرال جهت احراز زیر مجموعه :
https://t.me/$usernamebot?start={$user['codeInvitation']}";
    }
    $text_account_tpl = $datatextbot['text_account_info'] ?? "
🗂 اطلاعات حساب کاربری شما :

🪪 شناسه کاربری: <code>{shanase}</code>
👤 نام: <code>{name}</code>
👨‍👩‍👦 کد معرف شما : <code>{referral_code}</code>
📱 شماره تماس :{phone}
⌚️زمان ثبت نام : {register_date}
💰 موجودی: {balance} تومان
🛒 تعداد سرویس های خریداری شده : {order_count} عدد
📑 تعداد فاکتور های پرداخت شده :  : {payment_count} عدد
🤝 تعداد زیر مجموعه های شما : {affiliate_count} نفر
🔖 گروه کاربری : {group}

📆 {date_now} → ⏰ {time_now}
                    ";
    $text_account_replacements = [
        '{shanase}' => $from_id,
        '{name}' => $first_name,
        '{referral_code}' => $user['codeInvitation'],
        '{phone}' => $numberphone,
        '{register_date}' => $userjoin,
        '{balance}' => $Balanceuser,
        '{order_count}' => $countorder,
        '{payment_count}' => $countpayment,
        '{affiliate_count}' => $user['affiliatescount'],
        '{group}' => $groupuser,
        '{date_now}' => $dateacc,
        '{time_now}' => $timeacc,
    ];
    $text_account = strtr($text_account_tpl, $text_account_replacements) . "\n$textscore\n$textinvite";
    $activeKeyboard = rx_account_keyboard_json($from_id);
    if ($datain == "account" || $datain == "accountbtn") {
        Editmessagetext($from_id, $message_id, $text_account, $activeKeyboard);
    } else {
        sendmessage($from_id, $text_account, $activeKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif ($datain == "MyTransactions") {
    $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
    $txRows = [
        [
            rx_kb_style(['text' => '🕐 ۲۴ ساعت گذشته', 'callback_data' => 'tx_24h'], 'tx_24h', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '📅 ۳ روز گذشته', 'callback_data' => 'tx_3d'], 'tx_3d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '🗓 ۷ روز گذشته', 'callback_data' => 'tx_7d'], 'tx_7d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => '📆 ۳۰ روز گذشته', 'callback_data' => 'tx_30d'], 'tx_30d', $_rx_tx_s),
        ],
        [
            rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account'], 'tx_back', $_rx_tx_s),
        ],
    ];
    $txKeyboard = json_encode(['inline_keyboard' => $txRows], JSON_UNESCAPED_UNICODE);
    $txPromptText = "📑 تراکنش‌های من\n\nبازه‌ی زمانی مورد نظر را برای مشاهده‌ی تراکنش‌های کیف پول خود انتخاب کنید:";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $txPromptText, $txKeyboard, 'HTML');
    } else {
        sendmessage($from_id, $txPromptText, $txKeyboard, 'HTML');
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^tx_(24h|3d|7d|30d)(?:_(\d+))?$/', (string)$datain, $txRangeMatch)) {
    $txRangeSeconds = [
        '24h' => 24 * 60 * 60,
        '3d'  => 3 * 24 * 60 * 60,
        '7d'  => 7 * 24 * 60 * 60,
        '30d' => 30 * 24 * 60 * 60,
    ][$txRangeMatch[1]];
    $txRangeLabel = [
        '24h' => '۲۴ ساعت گذشته',
        '3d'  => '۳ روز گذشته',
        '7d'  => '۷ روز گذشته',
        '30d' => '۳۰ روز گذشته',
    ][$txRangeMatch[1]];
    $txCategoryLabels = [
        'topup_card'           => 'شارژ کارت به کارت',
        'topup_crypto'         => 'شارژ ارز دیجیتال',
        'topup_gateway'        => 'شارژ درگاه پرداخت',
        'admin_credit'         => 'افزایش موجودی توسط ادمین',
        'admin_debit'          => 'کاهش موجودی توسط ادمین',
        'purchase'             => 'خرید سرویس',
        'refund'               => 'بازگشت وجه',
        'affiliate_commission' => 'پورسانت زیرمجموعه',
        'gift_code'            => 'کد هدیه',
        'lottery'              => 'جایزه قرعه‌کشی',
        'cashback'             => 'هدیه بازگشت وجه',
        'service_action'       => 'عملیات سرویس',
        'transfer_out'         => 'انتقال موجودی به کاربر دیگر',
        'transfer_in'          => 'انتقال موجودی از کاربر دیگر',
        'referral_gift'        => 'هدیه عضویت زیرمجموعه',
    ];
    $txRangeKey = $txRangeMatch[1];
    $txLimit = 5;
    $txPage = isset($txRangeMatch[2]) && $txRangeMatch[2] !== '' ? max(1, (int)$txRangeMatch[2]) : 1;
    $txOffset = ($txPage - 1) * $txLimit;
    $txCutoff = time() - $txRangeSeconds;

    $txCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM wallet_ledger WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)"
    );
    $txCountStmt->execute([':u' => (string)$from_id, ':cutoff' => $txCutoff]);
    $txTotal = (int)$txCountStmt->fetchColumn();
    $txTotalPages = $txTotal > 0 ? (int)ceil($txTotal / $txLimit) : 1;
    if ($txPage > $txTotalPages) {
        $txPage = $txTotalPages;
        $txOffset = ($txPage - 1) * $txLimit;
    }

    $txStmt = $pdo->prepare(
        "SELECT direction, amount, category, description, created_at
           FROM wallet_ledger
          WHERE id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)
          ORDER BY id DESC
          LIMIT :limit OFFSET :offset"
    );
    $txStmt->bindValue(':u', (string)$from_id);
    $txStmt->bindValue(':cutoff', $txCutoff);
    $txStmt->bindValue(':limit', $txLimit, PDO::PARAM_INT);
    $txStmt->bindValue(':offset', $txOffset, PDO::PARAM_INT);
    $txStmt->execute();
    $txRowsData = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($txRowsData)) {
        $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\nدر این بازه‌ی زمانی، تراکنشی یافت نشد.";
    } else {
        $txLines = [];
        foreach ($txRowsData as $txRow) {
            $txSign = $txRow['direction'] === 'credit' ? '➕' : '➖';
            $txLabel = $txCategoryLabels[$txRow['category']] ?? $txRow['category'];
            $txAmountFmt = number_format((int)$txRow['amount']);
            $txDateFmt = jdate('Y/m/d H:i', strtotime($txRow['created_at']));
            $txLines[] = "{$txSign} {$txAmountFmt} تومان — {$txLabel}\n🕐 {$txDateFmt}";
        }
        $txPageInfo = $txTotalPages > 1 ? "\n\n📄 صفحه {$txPage} از {$txTotalPages}" : '';
        $txText = "📑 تراکنش‌های من ({$txRangeLabel})\n\n" . implode("\n\n", $txLines) . $txPageInfo;
    }

    $_rx_tx_s = (isset($GLOBALS['_rx_tx_styles']) && is_array($GLOBALS['_rx_tx_styles'])) ? $GLOBALS['_rx_tx_styles'] : [];
    $txKbRows = [];
    if ($txTotalPages > 1) {
        $txNavRow = [];
        if ($txPage > 1) {
            $txNavRow[] = rx_kb_style(['text' => '◀️ قبلی', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage - 1)], 'tx_prev', $_rx_tx_s);
        }
        if ($txPage < $txTotalPages) {
            $txNavRow[] = rx_kb_style(['text' => 'بعدی ▶️', 'callback_data' => "tx_{$txRangeKey}_" . ($txPage + 1)], 'tx_next', $_rx_tx_s);
        }
        if (!empty($txNavRow)) {
            $txKbRows[] = $txNavRow;
        }
    }
    $txKbRows[] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'MyTransactions'], 'tx_back', $_rx_tx_s)];
    $txBackKeyboard = json_encode(['inline_keyboard' => $txKbRows], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, $txText, $txBackKeyboard, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "TransferBalance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $trBackKb = json_encode(['inline_keyboard' => [
        [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'account'], 'backuser', $_rx_acc_s)],
    ]], JSON_UNESCAPED_UNICODE);
    $trPromptText = "🔄 انتقال موجودی\n\nآیدی عددی تلگرام کاربر مقصد را ارسال کنید:";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $trPromptText, $trBackKb, 'HTML');
    } else {
        sendmessage($from_id, $trPromptText, $trBackKb, 'HTML');
    }
    step('getTransferRecipient', $from_id);
    return;
} elseif ($user['step'] == "getTransferRecipient") {
    if (!isset($update['message']) && empty($text)) { return; }
    $trRecipientId = trim((string)$text);
    if (!ctype_digit($trRecipientId)) {
        sendmessage($from_id, "❌ آیدی عددی نامعتبر است. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return;
    }
    if ($trRecipientId === (string)$from_id) {
        sendmessage($from_id, "❌ نمی‌توانید به خودتان موجودی انتقال دهید. آیدی عددی دیگری ارسال کنید:", null, 'HTML');
        return;
    }
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربری با این آیدی یافت نشد. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $trRecipientId, "id", $from_id);
    sendmessage($from_id, sprintf("💸 مبلغ مورد نظر برای انتقال را به تومان وارد کنید:\n✅ حداقل مبلغ %s تومان می‌باشد", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
    step('getTransferAmount', $from_id);
} elseif ($user['step'] == "getTransferAmount") {
    if (!isset($update['message']) && empty($text)) { return; }
    $trAmount = trim((string)$text);
    if (!ctype_digit($trAmount) || (int)$trAmount <= 0) {
        sendmessage($from_id, "❌ مبلغ نامعتبر است. مبلغ را به تومان و به صورت عدد ارسال کنید:", null, 'HTML');
        return;
    }
    $trAmount = (int)$trAmount;
    if ($trAmount < RX_MIN_TRANSFER_AMOUNT) {
        sendmessage($from_id, sprintf("❌ مبلغ باید حداقل %s تومان باشد. مجددا ارسال کنید:", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
        return;
    }
    if ((float)$user['Balance'] < $trAmount) {
        sendmessage($from_id, "❌ موجودی کیف پول شما کافی نیست.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $trRecipientId = $user['Processing_value_one'];
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    update("user", "Processing_value", $trAmount, "id", $from_id);
    $trRecipientLabel = trim((string)($trRecipientUser['username'] ?? '')) !== ''
        ? '@' . $trRecipientUser['username']
        : $trRecipientId;
    $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
    $trConfirmKb = json_encode(['inline_keyboard' => [
        [
            rx_kb_style(['text' => '✅ تایید و انتقال', 'callback_data' => 'transferbal_confirm'], 'transferbal_confirm', $_rx_acc_s),
            rx_kb_style(['text' => '❌ انصراف', 'callback_data' => 'transferbal_cancel'], 'transferbal_cancel', $_rx_acc_s),
        ],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, sprintf("🔄 تایید انتقال موجودی\n\n👤 گیرنده: %s\n💰 مبلغ: %s تومان\n\nآیا از انجام این تراکنش اطمینان دارید؟", $trRecipientLabel, number_format($trAmount)), $trConfirmKb, 'HTML');
    step('getTransferConfirm', $from_id);
} elseif ($datain == "transferbal_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, "❌ انتقال موجودی لغو شد.", null, 'HTML');
    step('home', $from_id);
} elseif ($datain == "transferbal_confirm" && $user['step'] == "getTransferConfirm") {
    $trAmount = (int)$user['Processing_value'];
    $trRecipientId = $user['Processing_value_one'];
    if ($trAmount <= 0 || $trRecipientId === '' || $trRecipientId === null) {
        Editmessagetext($from_id, $message_id, "❌ اطلاعات تراکنش نامعتبر است. مجددا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    $trRecipientUser = select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        Editmessagetext($from_id, $message_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    $trCharge = balance_atomic_charge($from_id, $trAmount, 0);
    if (empty($trCharge['ok'])) {
        Editmessagetext($from_id, $message_id, "❌ موجودی کیف پول شما کافی نیست.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    wallet_ledger_record($from_id, 'debit', $trAmount, 'transfer_out', 'انتقال موجودی به کاربر ' . $trRecipientId, null, 'user', (string)$trRecipientId);
    $trCredited = balance_atomic_credit($trRecipientId, $trAmount);
    if (!$trCredited) {
        $trRefunded = balance_atomic_credit($from_id, $trAmount);
        if ($trRefunded) {
            wallet_ledger_record($from_id, 'credit', $trAmount, 'refund', 'بازگشت وجه انتقال ناموفق', null, 'user', (string)$trRecipientId);
        }
        Editmessagetext($from_id, $message_id, "❌ خطا در واریز به کاربر مقصد. مبلغ به کیف پول شما بازگشت داده شد.", null, 'HTML');
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "", "id", $from_id);
        step('home', $from_id);
        return;
    }
    wallet_ledger_record($trRecipientId, 'credit', $trAmount, 'transfer_in', 'انتقال موجودی از کاربر ' . $from_id, null, 'user', (string)$from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, sprintf("✅ انتقال موجودی با موفقیت انجام شد.\n\n💰 مبلغ: %s تومان\n👤 گیرنده: %s", number_format($trAmount), $trRecipientId), null, 'HTML');
    $trSenderUsername = trim((string)($user['username'] ?? ''));
    $trSenderLines = $trSenderUsername !== '' ? "نام کاربری: @{$trSenderUsername}\n" : '';
    $trSenderLines .= "آیدی عددی: <code>{$from_id}</code>";
    $trNotifyText = sprintf("مبلغ %s تومان به کیف پول شما واریز شد.\n\n<blockquote>%s</blockquote>", number_format($trAmount), $trSenderLines);
    sendmessage($trRecipientId, $trNotifyText, null, 'HTML');
    step('home', $from_id);
} elseif ((($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buyback" || $text == "/buy" || $text == "buy") && $statusnote) {
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($datain != "buyback") {
        rx_section_media_enter('buy', $from_id, $datain, $message_id, ['buy'], ['buy']);
    }
    if ($datain == "buy") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['notestep'], $backuser);
    } elseif ($datain == "buyback") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    }
    step("statusnamecustom", $from_id);
    return;
} elseif (($text !== '' && $text == $datatextbot['text_sell']) || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom") {
    if (!check_active_btn($setting['keyboardmain'], "text_sell")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel  WHERE status = 'active' ORDER BY (position = 0) ASC, position ASC, id ASC");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    if ($user['step'] != "statusnamecustom" && $datain != "buybacktow" && $datain != "buyback") {
        rx_section_media_enter('buy', $from_id, $datain, $message_id, ['buy'], ['buy']);
    }

    if (!isset($datatextbot['textselectlocation']) || trim((string)$datatextbot['textselectlocation']) === '') {
        $datatextbot['textselectlocation'] = '📍 لطفاً لوکیشن (سرور) مورد نظر را انتخاب کنید:';
    }
    if (mysqli_num_rows($locationproduct) == 1) {
        $singlePanelRow = mysqli_fetch_assoc($locationproduct);
        if (function_exists('nmAnyNationalNetEnabled') && nmAnyNationalNetEnabled()) {
            $_rx_buy_isCb = ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback");
            rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, $_rx_buy_isCb);
            return;
        }
        $location = $singlePanelRow['name_panel'];
        $locationproduct = select("marzban_panel", "*", "name_panel", $location, "select");
        if ($locationproduct['hide_user'] != null) {
            $list_user = json_decode($locationproduct['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
                return;
            }
        }
        if (panel_creation_limit_reached($locationproduct)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanelfirst'], null, 'HTML');
            return;
        }
        if ($user['step'] == "statusnamecustom") {
            if (!isset($update['message']) && empty($text)) { return; }
            savedata('clear', "nameconfig", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        if (!panel_feature_enabled($location, 'categorytime')) {
            $marzban_list_get = $locationproduct;
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
            $mainvolume = $mainvolume[$user['agent']];
            $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
            $maxvolume = $maxvolume[$user['agent']];
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
                if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
                    sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
                    return;
                }
                $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
                sendmessage($from_id, $textcustom, $backuser, 'html');
                step('gettimecustomvol', $from_id);
                return;
            }
            if (panel_feature_enabled($location, 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                if ($setting['statusnamecustom'] == 'onnamecustom') {
                    $backuser = "buyback";
                } else {
                    $backuser = "backuser";
                }
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser));
                } else {
                    sendmessage($from_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], $backuser), 'HTML');
                }
            } else {
                $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
                $queryParams = [
                    ':location' => $location,
                    ':agent' => $user['agent']
                ];
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
                if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                    $statuscustom = true;
                } else {
                    $statuscustom = false;
                }
                $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first-no-category'], [
                    'panel' => $marzban_list_get['name_panel'],
                ]);
                if ($datain == "buy") {
                    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
                } else {
                    sendmessage($from_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true), 'HTML');
                }
            }
        } else {
            $productCountParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
            $productCountStmt->execute($productCountParams);
            $nullproduct = (int)$productCountStmt->fetchColumn();
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            $statuscustom = false;
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
                $statuscustom = true;
            if ($statusnote) {
                $back = "buyback";
            } else {
                $back = "backuser";
            }
            $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", $back, $statuscustom, false);
            if ($datain == "buy" || $datain == "buybacktow") {
                Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
            } else {
                sendmessage($from_id, $textbotlang['Admin']['month']['title'], $monthkeyboard, 'HTML');
            }
        }
        return;
    }
    if ($user['step'] == "statusnamecustom") {
        if (!isset($update['message']) && empty($text)) { return; }
        savedata('clear', "nameconfig", $text);
        step("home", $from_id);
    }
    $rx_loc_kb = ['inline_keyboard' => []];
    $rx_loc_q = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' ORDER BY (position = 0) ASC, position ASC, id ASC");
    $rx_loc_q->execute();
    while ($rx_p = $rx_loc_q->fetch(PDO::FETCH_ASSOC)) {
        $rx_hide = json_decode((string)($rx_p['hide_user'] ?? ''), true);
        if (is_array($rx_hide) && in_array($from_id, $rx_hide)) continue;
        if (($rx_p['type'] ?? '') == "Manualsale") {
            $rx_ms = $pdo->prepare("SELECT 1 FROM manualsell WHERE codepanel = :c AND status = 'active'");
            $rx_ms->execute([':c' => $rx_p['code_panel']]);
            if ($rx_ms->rowCount() == 0) continue;
        }
        $rx_loc_kb['inline_keyboard'][] = [['text' => (string)$rx_p['name_panel'], 'callback_data' => "location_{$rx_p['code_panel']}"]];
    }
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $_rx_loc_back_key = $statusnote ? "buyback" : "backuser";
    $rx_loc_kb['inline_keyboard'][] = [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => $_rx_loc_back_key], $_rx_loc_back_key, $_rx_nav_s)];
    $list_marzban_panel_user = json_encode($rx_loc_kb, JSON_UNESCAPED_UNICODE);
    rx_send_buy_first_step($from_id, $message_id, $datain, $datatextbot['textselectlocation'], $list_marzban_panel_user, ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback"));
} elseif (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") {
    $userdate = json_decode($user['Processing_value'], true);
    if ($datain != "backproduct") {
        $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    } else {
        $location = $userdate['name_panel'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $locationproductcount = select("marzban_panel", "*", "name_panel", $location, "count");
    if (panel_creation_limit_reached($marzban_list_get)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['limitedpanel'], null, 'HTML');
        return;
    }
    if ($statusnote) {
        savedata('save', "name_panel", $location);
    } else {
        savedata('clear', "name_panel", $location);
    }
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
        $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
        $mainvolume = $mainvolume[$user['agent']];
        $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
        $maxvolume = $maxvolume[$user['agent']];
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']] ?? '0';
        if ($statuscustomvolume != "1" || $marzban_list_get['type'] == "Manualsale") {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'] ?? '❌ محصولی یافت نشد.', null, 'HTML');
            return;
        }
        $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
        sendmessage($from_id, $textcustom, $backuser, 'html');
        step('gettimecustomvol', $from_id);
        return;
    }
    if (!panel_feature_enabled($location, 'categorytime')) {
        if (panel_feature_enabled($location, 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($location, $user['agent']))) {
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($location, $user['agent'], "buybacktow"));
        } else {
            $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
            $queryParams = [
                ':location' => $location,
                ':agent' => $user['agent']
            ];
            $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
            if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
                $datakeyboard = "prodcutservices_";
            } else {
                $datakeyboard = "prodcutservice_";
            }
            if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
                $statuscustom = true;
            } else {
                $statuscustom = false;
            }
            if (isset($userdate['nameconfig'])) {
                $back = "buybacktow";
            } else {
                $back = "buyback";
            }
            $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
                'panel' => $marzban_list_get['name_panel'],
            ]);
            Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, "customsellvolume", $user['agent'], $queryParams, true));
        }
    } else {
        $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $productCountStmt->execute($productCountParams);
        $nullproduct = (int)$productCountStmt->fetchColumn();
        if ($nullproduct == 0) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
            return;
        }
        $statuscustom = false;
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale")
            $statuscustom = true;
        $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", "buybacktow", $statuscustom, false);
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['month']['title'], $monthkeyboard);
    }
} elseif (preg_match('/^categorynames_(.*)/', $datain, $dataget)) {
    $categorynames = $dataget[1];
    $categoryId = $categorynames;
    $categoryRow = select("category", "*", "id", $categoryId, "select");
    $categorynames = is_array($categoryRow) && isset($categoryRow['remark']) ? $categoryRow['remark'] : $categoryId;
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");


    $catValues = function_exists('nmCategoryLookupValues') ? nmCategoryLookupValues($categorynames) : [];
    foreach ([$categorynames, $categoryId] as $extra) { if (trim((string)$extra) !== '') $catValues[] = (string)$extra; }
    $catValues = array_values(array_unique(array_filter(array_map('strval', $catValues), static function ($v) { return trim($v) !== ''; })));
    if (!$catValues) $catValues = [(string)$categorynames];
    [$catClause, $catParams] = nmBuildFindInSetClause('category', $catValues, 'catv');
    if ($catClause === '') $catClause = '1=0';
    if (isset($userdate['monthproduct'])) {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':service_time' => $userdate['monthproduct'],
            ':agent' => $user['agent']
        ], $catParams);
    } else {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND {$catClause} AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = array_merge([
            ':location' => $userdate['name_panel'],
            ':agent' => $user['agent']
        ], $catParams);
    }
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $datakeyboard = "prodcutservices_";
    } else {
        $datakeyboard = "prodcutservice_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first'], [
        'category' => $categorynames,
        'panel' => $marzban_list_get['name_panel'],
    ]);
    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
} elseif (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (panel_feature_enabled($userdate['name_panel'], 'categorygeneral') && (!function_exists('nmHasSellableCategories') || nmHasSellableCategories($userdate['name_panel'], $user['agent']))) {
        savedata("save", "monthproduct", $monthenumber);
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active'");
        $stmt->execute();
        $count_panel = $stmt->rowCount();
        if ($count_panel == 1) {
            $back = "buybacktow";
        } else {
            $back = "location_{$marzban_list_get['code_panel']}";
        }
        Editmessagetext($from_id, $message_id, "📌 دسته بندی خود را انتخاب نمایید!", KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], $back));
    } else {
        $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND Service_time = :service_time AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $queryParams = [
            ':location' => $userdate['name_panel'],
            ':service_time' => $monthenumber,
            ':agent' => $user['agent']
        ];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
        if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
            $datakeyboard = "prodcutservices_";
        } else {
            $datakeyboard = "prodcutservice_";
        }
        if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
            $statuscustom = true;
        } else {
            $statuscustom = false;
        }
        $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-first-no-category'], [
            'panel' => $marzban_list_get['name_panel'],
        ]);
        Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolume", $user['agent'], $queryParams, true));
    }
} elseif ($datain == "customsellvolume") {
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.
🔔قیمت هر گیگ حجم " . rxFormatToman($custompricevalue) . " تومان می باشد.
🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvol', $from_id);
} elseif ($user['step'] == "gettimecustomvol") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if (!ctype_digit((string) $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ((int) $text > (int) $maxvolume || (int) $text < (int) $mainvolume) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        step('getvolumecustomusername', $from_id);
    } else {
        step('getvolumecustomuser', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusername" || preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($user['step'] == "getvolumecustomusername") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusers', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuser', $from_id);
        deletemessage($from_id, $message_id);
    }
    $_rx_selectusername_kb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
} elseif ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuser") {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($user['step'] == "getvolumecustomuser") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusers" || $user['step'] == "endstepuser") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($loc, $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase preview failed: product not found for code=' . $loc . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($parts[0] != "customvolume") {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    if (intval($user['pricediscount']) != 0) {
        $resultper = ($info_product['price_product'] * $user['pricediscount']) / 100;
        $info_product['price_product'] = $info_product['price_product'] - $resultper;
    }
    $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
    fx_quote_store($from_id, fx_quote_context_key('buy', (string) $loc), $info_product['price_product'], $marzban_list_get, $fxBuyKinds);
    $randomString = bin2hex(random_bytes(2));
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $requestedUsername_ac = $username_ac;
    $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
    $random_number = rand(1000000, 9999999);
    $usernameWasRenamed = isset($DataUserOut['username']) || rxTableValueExists('invoice', 'username', $username_ac);
    if ($usernameWasRenamed) {
        $username_ac = $random_number . "-" . $username_ac;
    }
    if (isset($username_ac))
        update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $info_product_price_product = number_format($info_product['price_product']);
    $userBalance = number_format($user['Balance']);
    $replacements = [
        '{username}' => guardDisplayUsername($username_ac, $marzban_list_get),
        '{name_product}' => $info_product['name_product'],
        '{Service_time}' => $info_product['Service_time'],

        '{note}' => $info_product['note'] ?? '',
        '{price}' => $info_product_price_product,
        '{Volume}' => $info_product['Volume_constraint'],
        '{userBalance}' => $userBalance
    ];
    $textin = strtr($datatextbot['text_pishinvoice'], $replacements);
    if ($usernameWasRenamed) {
        $textin = "⚠️ نام کاربری «" . guardDisplayUsername($requestedUsername_ac, $marzban_list_get) . "» قبلاً استفاده شده بود؛ نام کاربری «" . guardDisplayUsername($username_ac, $marzban_list_get) . "» برای شما در نظر گرفته شد.\n\n" . $textin;
    }
    if (intval($info_product['Volume_constraint']) == 0) {
        $textin = str_replace('گیگ', "", $textin);
    }
    $previewSymbolicLimitText = faoxima_symbolic_limit_label($info_product);
    if ($previewSymbolicLimitText !== null) {
        $textin .= "\n👥 تعداد کاربر: $previewSymbolicLimitText";
    } elseif (($marzban_list_get['type'] ?? '') == 'x-ui_single' && (($marzban_list_get['ip_limit_guard'] ?? '') === 'onipguard')) {
        $previewIpLimit = intval($info_product['ip_limit'] ?? 0);
        $previewIpLimitText = $previewIpLimit > 0 ? "{$previewIpLimit} کاربر" : 'نامحدود';
        $textin .= "\n👥 تعداد کاربر: $previewIpLimitText";
    }
    if (in_array($marzban_list_get['type'] ?? '', ['pasarguard', 'remnawave', 'x-ui_single'], true)) {
        $previewHwidLimit = intval($info_product['hwid_limit'] ?? 0);
        if ($previewHwidLimit > 0) {
            $textin .= "\n🔒 محدودیت دستگاه: {$previewHwidLimit} دستگاه";
        }
    }
    $__discEligibleBuy = MiniDiscount::hasEligible('buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
    $__paymentKeyboard = $__discEligibleBuy ? $payment : $paymentom;
    if ($user['step'] != "getvolumecustomuser" && !in_array($marzban_list_get['MethodUsername'], ["نام کاربری دلخواه", "نام کاربری دلخواه + عدد رندوم", "متن دلخواه کاربر + رندوم"])) {
        Editmessagetext($from_id, $message_id, $textin, $__paymentKeyboard);
    } else {
        sendmessage($from_id, $textin, $__paymentKeyboard, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && in_array($datain, ["confirmandgetservice", "confirmandgetserviceDiscount"], true)) {
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_purchase_info_incomplete'] ?? "❌ اطلاعات خرید کامل نیست؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));

    $parts = explode("_", $user['Processing_value_one']);

    $partsdic = explode("_", $user['Processing_value_four']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_not_found'] ?? "❌ پنل انتخاب‌شده یافت نشد؛ لطفا مراحل خرید را مجددا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $datatextbot['dyn_errors_panel_unavailable_choose_other'] ?? "❌ این پنل در دسترس نیست لطفا از پنل دیگری خرید را انجام دهید.", $backuser, 'html');
        step("home", $from_id);
        return;
    }
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } elseif (function_exists('nmProductByCodeForPanel')) {
            $info_product = nmProductByCodeForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }

    if (!array_key_exists('category', $info_product)) {
        $info_product['category'] = '';
    }
    if (!array_key_exists('note', $info_product)) {
        $info_product['note'] = '';
    }
    $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($parts[0] != "customvolume") {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    if ($datain == "confirmandgetserviceDiscount") {
        $discountcode = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "count");
        if ($discountcode == 0) {
            sendmessage($from_id, $datatextbot['dyn_errors_discount_code_not_applicable'] ?? "❌ امکان خرید با این کد کد تخفیف وجود ندارد", null, 'HTML');
            return;
        }
        $priceproduct = $partsdic[1];
        $fxBuyDiscountKey = fx_quote_context_key('buy_discount', (string) $user['Processing_value_one']);
        if (!fx_quote_check($from_id, $fxBuyDiscountKey, $priceproduct, $marzban_list_get, $fxBuyKinds)) {
            $fxRecheck = MiniDiscount::validateSell((string) $partsdic[0], 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
            if (empty($fxRecheck['ok'])) {
                sendmessage($from_id, (string)($fxRecheck['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                return;
            }
            $fxNewDiscounted = fx_apply_discount_rule(fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxBuyKinds), (string) $fxRecheck['value_type'], (float) $fxRecheck['value']);
            $fxNewDiscounted = fx_finalize_amount($fxNewDiscounted, $marzban_list_get, $fxBuyKinds);
            update("user", "Processing_value_four", $partsdic[0] . "_" . $fxNewDiscounted, "id", $from_id);
            fx_quote_store($from_id, $fxBuyDiscountKey, $fxNewDiscounted, $marzban_list_get, $fxBuyKinds);
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            sendmessage($from_id, fx_price_changed_text($fxNewDiscounted), json_encode(['inline_keyboard' => [
                [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetserviceDiscount"]],
                [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s)],
            ]]), 'HTML');
            return;
        }
    } else {
        $priceproduct = $info_product['price_product'];
        $fxBuyCheckAmount = fx_finalize_amount(fx_apply_user_discount($priceproduct, $user['pricediscount']), $marzban_list_get, $fxBuyKinds);
        $fxBuyQuoteKey = fx_quote_context_key('buy', (string) $user['Processing_value_one']);
        if (!fx_quote_check($from_id, $fxBuyQuoteKey, $fxBuyCheckAmount, $marzban_list_get, $fxBuyKinds)) {
            fx_quote_store($from_id, $fxBuyQuoteKey, $fxBuyCheckAmount, $marzban_list_get, $fxBuyKinds);
            sendmessage($from_id, fx_price_changed_text($fxBuyCheckAmount), $paymentom, 'HTML');
            return;
        }
    }
    $fxBuyListPrice = $info_product['price_product'];
    $username_ac = strtolower($user['Processing_value_tow']);
    $DataUserOut = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac) : null;
    if (isset($DataUserOut['username']) || rxTableValueExists('invoice', 'username', $username_ac)) {
        sendmessage($from_id, $datatextbot['dyn_errors_restart_buy_process_short'] ?? "❌ لطفا مراحل خرید را مجددا انجام دهید", null, 'HTML');
        return;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $random_number = rand(1000000, 9999999);
    if (rxTableValueExists('invoice', 'id_invoice', $randomString)) {
        $randomString = $random_number . $randomString;
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $info_product['code_product']);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, $datatextbot['dyn_errors_stock_low_purchase_blocked'] ?? "❌ موجودی این سرویس به پایان رسیده لطفا سرویسی دیگر را خریداری کنید.", null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $priceproduct = fx_finalize_amount($priceproduct, $marzban_list_get, $fxBuyKinds);
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
    $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
    $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
    $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
    $stmt->execute();
    $stmt->close();
    $fxBuyBasePrice = $parts[0] == "customvolume" ? fx_custom_base_price($marzban_list_get, $user['agent'], $parts[2], $parts[1]) : ($info_product['fx_base_price'] ?? $fxBuyListPrice);
    fx_store_invoice_snapshot($randomString, fx_pricing_snapshot($marzban_list_get, $fxBuyKinds, $fxBuyBasePrice, $priceproduct, (float) $fxBuyListPrice - (float) $priceproduct, ['product' => (string)($info_product['code_product'] ?? '')]));
    if ($priceproduct > rx_ck_available_for_user($user) && $user['agent'] != "n2" && intval($priceproduct) != 0) {
        $marzbandirectpay = panel_feature_enabled($marzban_list_get, 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
        } else {
            rx_ck_bot_begin($user, [
                'operation' => 'getconfigafterpay',
                'op_key' => "getconfigafterpay|{$username_ac}",
                'target' => $username_ac,
                'final_price' => $priceproduct,
                'message_id' => $message_id,
                'one' => $username_ac,
                'tow' => 'getconfigafterpay',
                'four' => $datain == "confirmandgetserviceDiscount" ? "dis_{$partsdic[0]}" : '',
            ]);
        }
        return;
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (intval($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    Editmessagetext($from_id, $message_id, "♻️ در حال ساختن سرویس شما...", null);
    $SellDiscountlimit = false;
    if ($datain == "confirmandgetserviceDiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
        if ($SellDiscountlimit != false) {
            update('invoice', 'discount_code', $partsdic[0], 'id_invoice', $randomString);
            update('invoice', 'discount_amount', (string)((float)($info_product['price_product'] ?? 0) - (float)$priceproduct), 'id_invoice', $randomString);
            update('invoice', 'price_before_discount', (string)($info_product['price_product'] ?? 0), 'id_invoice', $randomString);
        }
    }
    $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($info_product['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buy'
    );
    $Shoppinginfo = [
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ];
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (nmStockCompleteBuyFromInventory($from_id, $user, $marzban_list_get, $info_product, $randomString, $username_ac, true, 'national_buy')) {
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. لطفاً محصول دیگری انتخاب کنید یا با پشتیبانی ارتباط بگیرید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac, true);
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        $dataoutput['msg'] = rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
        sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
        $texterros = "⭕️ خطای ساخت اشتراک
<blockquote>✍️ دلیل خطا :
{$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        return;
    }
    $username_ac = rx_adopt_created_username($dataoutput, $username_ac, $randomString);
    rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
    update("invoice", "Status", "active", "username", $username_ac);
    $output_config_link = "";
    $config = "";
    $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        for ($i = 0; $i < count($dataoutput['configs']); ++$i) {
            $config .= "\n" . $dataoutput['configs'][$i];
        }
    }
    $Shoppinginfo = json_encode($Shoppinginfo);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>" . guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    if (intval($priceproduct) != 0) {

        $rxBuyDeductRows = 0;
        if (($user['agent'] ?? '') === 'n2') {
            $stmtBuyDeduct = $pdo->prepare("UPDATE user SET Balance = Balance - :delta WHERE id = :uid");
            $stmtBuyDeduct->bindValue(':delta', (int) $priceproduct, PDO::PARAM_INT);
            $stmtBuyDeduct->bindValue(':uid', $from_id, PDO::PARAM_STR);
            $stmtBuyDeduct->execute();
            $rxBuyDeductRows = $stmtBuyDeduct->rowCount();
        } else {
            $rxBuyDeductCharge = balance_atomic_charge($from_id, (float) (int) $priceproduct, 0);
            $rxBuyDeductRows = !empty($rxBuyDeductCharge['ok']) ? 1 : 0;
        }
        if ($rxBuyDeductRows === 0 && function_exists('rx_log_event')) {
            rx_log_event('PURCHASE_DOUBLE_SPEND_OR_INSUFFICIENT', 'Atomic buy-deduct affected 0 rows after panel account already created', [
                'from_id'   => $from_id,
                'invoice'   => $randomString ?? null,
                'price'     => $priceproduct,
                'agent'     => $user['agent'] ?? null,
            ]);
        }
    }
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if ($affiliatescommission['status_commission'] == "oncommission" && ($user['affiliates'] != null && intval($user['affiliates']) != 0)) {
        if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
            if ($countinvoice == 1) {
                $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
                if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                    $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                    sendmessage($user['affiliates'], sprintf($rxPointsEarnedTpl, 2), null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $user['affiliates']);
                }

                $stmtAffComm1 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
                $stmtAffComm1->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
                $stmtAffComm1->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
                $stmtAffComm1->execute();
                $result = number_format($result);
                $dateacc = date('Y/m/d H:i:s');
                $textadd = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
                $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($user['affiliates'], $textadd, null, 'HTML');
            }
        } else {

            $result = ($priceproduct * $setting['affiliatespercentage']) / 100;
            $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
            if (intval($setting['scorestatus']) == 1 and !in_array($user['affiliates'], $admin_ids)) {
                $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                sendmessage($user['affiliates'], sprintf($rxPointsEarnedTpl, 2), null, 'html');
                $scorenew = $user_Balance['score'] + 2;
                update("user", "score", $scorenew, "id", $user['affiliates']);
            }

            $stmtAffComm2 = $pdo->prepare("UPDATE user SET Balance = Balance + :delta WHERE id = :uid");
            $stmtAffComm2->bindValue(':delta', (int) round($result), PDO::PARAM_INT);
            $stmtAffComm2->bindValue(':uid', $user['affiliates'], PDO::PARAM_STR);
            $stmtAffComm2->execute();
            $result = number_format($result);
            $dateacc = date('Y/m/d H:i:s');
            $textadd = faoxima_render_text(faoxima_textbot_get('dyn_purchase_affiliate_commission_user_tpl', "🎁  پرداخت پورسانت

        مبلغ {amount} تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید"), ['amount' => $result]);
            $textreportport = "
مبلغ $result به کاربر {$user['affiliates']} برای پورسانت از کاربر $from_id واریز گردید
<blockquote>تایم : $dateacc</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($user['affiliates'], $textadd, null, 'HTML');
        }
    }
    if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
        $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
        sendmessage($from_id, sprintf($rxPointsEarnedTpl, 1), null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = faoxima_textbot_get('dyn_purchase_first_buy_flag', "📌 خرید اول کاربر");
    }
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $rxFmtInfoProductPrice = rxFormatToman($info_product['price_product']);
    $rxFmtPriceproduct = rxFormatToman($priceproduct);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت در ربات شما ثبت شد .

$textonebuy
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :" . guardDisplayUsername($username_ac, $marzban_list_get) . "</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$userdate['name_panel']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️دسته بندی محصول : {$info_product['category']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtPriceproduct} تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        rx_sendTopicReport([
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ], ['flow' => 'buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('new_sub', [
            'user_id'    => $from_id,
            'amount'     => $info_product['name_product'],
            'price'      => number_format((float)$priceproduct),
            'panel_name' => $userdate['name_panel'] ?? '',
            'category'   => $info_product['category'] ?? '',
        ], $setting);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "aptdc") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscount', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscount") {
    if (!isset($update['message']) && empty($text)) { return; }
    $userdate = json_decode($user['Processing_value'], true);
    if (!isset($userdate['name_panel'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_restart_process'] ?? "❌ مراحل خرید را مجددا از اول انجام دهید", $keyboard, 'HTML');
        return;
    }
    $parts = explode("_", (string)$user['Processing_value_one']);
    if (($parts[0] ?? '') === "customvolume") {
        $info_product = [
            'code_product' => 'customvolume',
            'name_product' => $textbotlang['users']['customsellvolume']['title'],
            'Volume_constraint' => $parts[2] ?? 0,
            'Service_time' => $parts[1] ?? 0,
        ];
    } elseif (function_exists('rxResolveProductForPanel')) {
        $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:Location, Location) > 0 or Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
        $stmt->execute([
            ':code_product' => $user['Processing_value_one'],
            ':Location' => $userdate['name_panel'],
            ':agent' => $user['agent'],
        ]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($info_product) || !isset($info_product['code_product'])) {
        error_log('Discount code check failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__dv = MiniDiscount::validateSell($text, 'buy', (string)($info_product['code_product'] ?? ''), (string)($marzban_list_get['code_panel'] ?? ''), (string)($info_product['category'] ?? ''), $user);
    if (empty($__dv['ok'])) {
        sendmessage($from_id, (string)($__dv['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
        return;
    }
    $__dvt = $__dv['value_type'];
    $__dval = (float)$__dv['value'];
    $__dlabel = $__dv['label'];
    sendmessage($from_id, sprintf($datatextbot['dyn_errors_discount_applied'] ?? "🤩 کد تخفیف شما درست بود و تخفیف %s روی فاکتور شما اعمال شد.", $__dlabel), null, 'HTML');
    step('payment', $from_id);
    $parts = explode("_", $user['Processing_value_one']);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $userdate['name_panel'], $user['agent'], $userdate['category'] ?? null, $userdate['monthproduct'] ?? null);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $userdate['name_panel'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Discount purchase preview failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($userdate['name_panel'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $fxBuyKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($parts[0] != "customvolume") {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
    $info_productmain = $info_product['price_product'];
    if ($__dvt === 'free') {
        $info_product['price_product'] = 0;
    } elseif ($__dvt === 'amount') {
        $info_product['price_product'] = $info_product['price_product'] - $__dval;
    } else {
        $result = ($__dval / 100) * $info_product['price_product'];
        $info_product['price_product'] = $info_product['price_product'] - $result;
    }
    $info_product['price_product'] = round($info_product['price_product']);
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxBuyKinds);
    $rxFmtInfoProductmain = rxFormatToman($info_productmain);
    $rxFmtInfoProductPriceDiscounted = rxFormatToman($info_product['price_product']);
    $rxFmtUserBalanceInvoicePreview = rxFormatToman($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>{$user['Processing_value_tow']}</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت اصلی : <del>{$rxFmtInfoProductmain} تومان</del>
💶 قیمت با تخفیف: {$rxFmtInfoProductPriceDiscounted}  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : {$rxFmtUserBalanceInvoicePreview}

        💰 سفارش شما آماده پرداخت است.  ";
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $paymentDiscount = json_encode([
        'inline_keyboard' => [
            [['text' => "💰 پرداخت و دریافت سرویس", 'callback_data' => "confirmandgetserviceDiscount"]],
            [rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s)]
        ]
    ]);
    $parametrsendvalue = $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    fx_quote_store($from_id, fx_quote_context_key('buy_discount', (string) $user['Processing_value_one']), $info_product['price_product'], $marzban_list_get, $fxBuyKinds);
    sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
} elseif ($text == "🗂 خرید انبوه" || $datain == "kharidanbuh") {
    if ($setting['bulkbuy'] == "offbulk") {
        sendmessage($from_id, $datatextbot['dyn_errors_section_disabled'] ?? "❌ این بخش در حال غیرفعال می باشد", null, 'HTML');
        return;
    }
    $PaySetting = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shopSetting WHERE Namevalue = 'minbalancebuybulk'"))['value'];
    if ($user['Balance'] < $PaySetting) {
        $rxMinBulkTpl = $datatextbot['dyn_errors_min_bulk_purchase_balance'] ?? "❌ برای خرید انبوه باید حداقل %s تومان موجودی داشته باشید.";
        sendmessage($from_id, sprintf($rxMinBulkTpl, $PaySetting), null, 'HTML');
        return;
    }
    $locationproduct = mysqli_query($connect, "SELECT * FROM marzban_panel ORDER BY (position = 0) ASC, position ASC, id ASC");
    if (mysqli_num_rows($locationproduct) == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;

    if ($datain == "kharidanbuh") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    }
    step('getcountconfig', $from_id);
} elseif ($user['step'] == "getcountconfig") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (intval($text) > 15 || intval($text) < 1)
        return sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backuser, 'HTML');
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    sendmessage($from_id, $datatextbot['textselectlocation'], $list_marzban_panel_userom, 'HTML');
    update("user", "Processing_value_four", $text, "id", $from_id);
    step('home', $from_id);
} elseif (preg_match('/^locationom_(.*)/', $datain, $dataget)) {
    $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1], "select");
    $productCountParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $productCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
    $productCountStmt->execute($productCountParams);
    $nullproduct = (int)$productCountStmt->fetchColumn();
    if ($nullproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        return;
    }
    update("user", "Processing_value", $location, "id", $from_id);
    $statuscustomvolume = json_decode($marzban_list_get['customvolume'], true)[$user['agent']];
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    if ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale") {
        $statuscustom = true;
    } else {
        $statuscustom = false;
    }
    $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
    $textproduct = faoxima_render_text($textbotlang['users']['sell']['Service-select-no-category'], [
        'panel' => $marzban_list_get['name_panel'],
    ]);
    Editmessagetext($from_id, $message_id, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, "customsellvolumeom", $user['agent'], $productCountParams, true));
} elseif ($datain == "customsellvolumeom") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $textcustom = "🔋 لطفا مقدار حجم سرویس مورد نظر را وارد کنید ( برحسب گیگابایت ) :
📌 تعرفه هر گیگ :  " . rxFormatToman($custompricevalue) . "
🔔 حداقل حجم 1 گیگابایت و حداکثر 1000 گیگابایت می باشد.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    deletemessage($from_id, $message_id);
    step('gettimecustomvolom', $from_id);
} elseif ($user['step'] == "gettimecustomvolom") {
    if (!isset($update['message']) && empty($text)) { return; }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    $mainvolume = json_decode($marzban_list_get['mainvolume'], true);
    $mainvolume = $mainvolume[$user['agent']];
    $maxvolume = json_decode($marzban_list_get['maxvolume'], true);
    $maxvolume = $maxvolume[$user['agent']];
    $maintime = json_decode($marzban_list_get['maintime'], true);
    $maintime = $maintime[$user['agent']];
    $maxtime = json_decode($marzban_list_get['maxtime'], true);
    $maxtime = $maxtime[$user['agent']];
    if ($text > intval($maxvolume) || $text < intval($mainvolume)) {
        $texttime = "❌ حجم نامعتبر است.\n🔔 حداقل حجم $mainvolume گیگابایت و حداکثر $maxvolume گیگابایت می باشد";
        sendmessage($from_id, $texttime, $backuser, 'HTML');
        return;
    }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . rxFormatToman($customtimevalueprice) . "  تومان
⚠️ حداقل زمان $maintime روز  و حداکثر $maxtime روز  می توانید تهیه کنید";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        step('getvolumecustomusernameom', $from_id);
    } else {
        step('getvolumecustomuserom', $from_id);
    }
} elseif ($user['step'] == "getvolumecustomusernameom" || preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($user['step'] == "getvolumecustomusernameom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
            return;
        }
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > intval($maxtime) || intval($text) < intval($maintime)) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $customvalue = "customvolume_" . $text . "_" . $user['Processing_value_one'];
        update("user", "Processing_value_one", $customvalue, "id", $from_id);
        step('endstepusersom', $from_id);
    } else {
        update("user", "Processing_value_one", $prodcut, "id", $from_id);
        step('endstepuserom', $from_id);
    }
    $_rx_selectusername_kb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
    sendmessage($from_id, $textbotlang['users']['selectusername'], $_rx_selectusername_kb, 'html');
} elseif ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget) || $user['step'] == "getvolumecustomuserom") {
    if ($user['step'] == "getvolumecustomuserom") {
        if (!ctype_digit($text)) {
            sendmessage($from_id, $textbotlang['Admin']['customvolume']['invalidtime'] ?? '❌ زمان نامعتبر است', $backuser, 'HTML');
            return;
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $maintime = json_decode($marzban_list_get['maintime'], true);
        $maintime = $maintime[$user['agent']];
        $maxtime = json_decode($marzban_list_get['maxtime'], true);
        $maxtime = $maxtime[$user['agent']];
        if (intval($text) > $maxtime || intval($text) < $maintime) {
            $texttime = "❌ زمان ارسال شده نامعتبر است . زمان باید بین $maintime روز تا $maxtime روز باشد";
            sendmessage($from_id, $texttime, $backuser, 'HTML');
            return;
        }
        $prodcut = "customvolume_" . $text . "_" . $user['Processing_value_one'];
    } elseif ($user['step'] == "endstepusersom" || $user['step'] == "endstepuserom") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") {
        $_rx_usernameKb = ($marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم" || $marzban_list_get['MethodUsername'] == "متن دلخواه کاربر + رندوم") ? $usernamePromptKb : $backuser;
        if ($datain === 'gen_random_uname') {
            $text = rx_build_smart_random_username($username ?? '', $from_id);
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
        }
        if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $_rx_usernameKb, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($loc, $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $loc,
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase preview failed: product not found for code=' . ($loc ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $randomString = bin2hex(random_bytes(2));
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ($info_product['Volume_constraint'] == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    $fxBulkKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($parts[0] != "customvolume") {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    $info_product['price_product'] = intval($info_product['price_product']) * intval($user['Processing_value_four']);
    $info_product['price_product'] = fx_finalize_amount($info_product['price_product'], $marzban_list_get, $fxBulkKinds);
    update("user", "Processing_value_price", $info_product['price_product'], "id", $from_id);
    fx_quote_store($from_id, fx_quote_context_key('bulk', (string) $loc . '|' . (string) $user['Processing_value_four']), $info_product['price_product'], $marzban_list_get, $fxBulkKinds);
    $price_product_format = number_format($info_product['price_product']);
    $userbalancepish = number_format($user['Balance']);
    $textin = "
📇 پیش فاکتور شما:
👤 نام کاربری: <code>" . guardDisplayUsername($username_ac, $marzban_list_get) . "</code>
🔐 نام سرویس: {$info_product['name_product']}
📆 مدت اعتبار: {$info_product['Service_time']} روز
💶 قیمت: $price_product_format  تومان
👥 حجم اکانت: {$info_product['Volume_constraint']} گیگ
💵 موجودی کیف پول شما : $userbalancepish
⭕️تعداد کانفیگ : {$user['Processing_value_four']}

💰 سفارش شما آماده پرداخت است.  ";
    sendmessage($from_id, $textin, $paymentom, 'HTML');
    step('payments', $from_id);
} elseif ($user['step'] == "payments" && $datain == "confirmandgetservice") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
    $custompricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
    $customtimevalueprice = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
    $parts = explode("_", $user['Processing_value_one']);
    if ($parts[0] == "customvolume") {
        $info_product['Volume_constraint'] = $parts[2];
        $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
        $info_product['code_product'] = "customvolume";
        $info_product['Service_time'] = $parts[1];
        $info_product['price_product'] = ($parts[2] * $custompricevalue) + ($parts[1] * $customtimevalueprice);
        $info_product['data_limit_reset'] = "no_reset";
    } else {
        if (function_exists('rxResolveProductForPanel')) {
            $info_product = rxResolveProductForPanel($user['Processing_value_one'], $user['Processing_value'], $user['agent']);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1");
            $stmt->execute([
                ':code_product' => $user['Processing_value_one'],
                ':location' => $user['Processing_value'],
                ':agent' => $user['agent']
            ]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!is_array($info_product) || !isset($info_product['price_product'])) {
        error_log('Bulk purchase confirmation failed: product not found for code=' . ($user['Processing_value_one'] ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $datatextbot['dyn_errors_verification_failed'] ?? "❌ خطایی در تایید انجام شده است لطفا مراحل پرداخت را مجددا انجام دهید", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $fxBulkKinds = $parts[0] == "customvolume" ? ['custom_volume', 'custom_time'] : 'product';
    if ($parts[0] != "customvolume") {
        $info_product = fx_apply_to_product($info_product, $marzban_list_get);
    }
    if ($user['Processing_value_price'] !== null && $user['Processing_value_price'] !== '') {
        $priceproduct = (float) $user['Processing_value_price'];
    } else {
        $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
    }
    if (fx_context($marzban_list_get, $fxBulkKinds) !== null) {
        $fxBulkFresh = fx_finalize_amount(intval($info_product['price_product']) * intval($user['Processing_value_four']), $marzban_list_get, $fxBulkKinds);
        $fxBulkQuoteKey = fx_quote_context_key('bulk', (string) $user['Processing_value_one'] . '|' . (string) $user['Processing_value_four']);
        if (!fx_quote_check($from_id, $fxBulkQuoteKey, $fxBulkFresh, $marzban_list_get, $fxBulkKinds) || (int) round($priceproduct) !== (int) $fxBulkFresh) {
            update("user", "Processing_value_price", $fxBulkFresh, "id", $from_id);
            fx_quote_store($from_id, $fxBulkQuoteKey, $fxBulkFresh, $marzban_list_get, $fxBulkKinds);
            Editmessagetext($from_id, $message_id, fx_price_changed_text($fxBulkFresh), $paymentom);
            return;
        }
    }
    Editmessagetext($from_id, $message_id, $text_inline, null);
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $priceproduct = fx_finalize_amount($priceproduct, $marzban_list_get, $fxBulkKinds);
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($user['Processing_value'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $Balance_prim = $priceproduct - $user['Balance'];
            $Balance_prims = $user['Balance'] - $priceproduct;
            if ($Balance_prims <= 1)
                $Balance_prims = 0;
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $datep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
        $value = intval($user['number_username']) + $user['Processing_value_four'];
        update("user", "number_username", $value, "id", $from_id);
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($setting['numbercount']) + $user['Processing_value_four'];
            update("setting", "numbercount", $value);
        }
    }
    if ($info_product['Service_time'] == 0) {
        $datep = 0;
    } else {
        $datep = strtotime(date("Y-m-d H:i:s", $datep));
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", $datep)),
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buyomdh'
    );
    if ($info_product['inbounds'] != null) {
        $marzban_list_get['inboundid'] = $info_product['inbounds'];
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    $__bulkQty = max(1, (int)$user['Processing_value_four']);
    $__bulkUnitCharge = intdiv((int)round($priceproduct), $__bulkQty);
    $__bulkUnitChargeRemainder = ((int)round($priceproduct)) - ($__bulkUnitCharge * $__bulkQty);
    $__bulkChargedTotal = 0;
    $__bulkAllowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    for ($i = 0; $i < $user['Processing_value_four']; $i++) {
        $__bulkItemCharge = $__bulkUnitCharge + (($i == $__bulkQty - 1) ? $__bulkUnitChargeRemainder : 0);
        $random_number = rand(1000000, 9999999);
        $username_acc = $username_ac . "-" . $i;
        if (rxTableValueExists('invoice', 'username', $username_acc)) {
            $username_acc = $random_number . "-" . $username_acc;
        }
        $randomString = bin2hex(random_bytes(4));
        if (rxTableValueExists('invoice', 'id_invoice', $randomString)) {
            $randomString = $random_number . $randomString;
        }


        if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
            $stock = function_exists('nmStockReserveForProduct')
                ? nmStockReserveForProduct($marzban_list_get, $info_product, $from_id, $randomString, 'national_direct_buy')
                : false;
            if (!$stock) {
                sendmessage($from_id, "❌ وضعیت نت ملی فعال است اما موجودی انبار برای این محصول تمام شده است. خرید انجام نشد و مبلغی از کیف پول کسر نشد.", $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
            $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
            $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
            $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
            $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
            $Status = "active";
            $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
            $stmt->execute();
            $stmt->close();
            try {
                update("invoice", "user_info", $stock['content'], "id_invoice", $randomString);
                update("invoice", "source_panel_code", $marzban_list_get['code_panel'] ?? '', "id_invoice", $randomString);
            } catch (Throwable $e) {
                error_log('nm national direct invoice update failed: ' . $e->getMessage());
            }
            $inventoryInvoice = [
                'id_user' => $from_id,
                'id_invoice' => $randomString,
                'username' => $username_acc,
                'Service_location' => $marzban_list_get['name_panel'] ?? '',
                'name_product' => $info_product['name_product'] ?? '',
                'Volume' => $info_product['Volume_constraint'] ?? 0,
                'Service_time' => $info_product['Service_time'] ?? 0,
                'price_product' => $info_product['price_product'] ?? 0,
            ];
            nmStockDeliverConfig($stock, $inventoryInvoice, '✅ وضعیت نت ملی فعال است؛ اشتراک از انبار شبکه‌ملی تحویل شد');
            if (function_exists('balance_atomic_charge')) {
                $__bulkChargeNm = balance_atomic_charge($from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
                if (!empty($__bulkChargeNm['ok']) && function_exists('wallet_ledger_record')) {
                    wallet_ledger_record($from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس (انبار ملی)', (string)$randomString, 'invoice', (string)$randomString);
                }
            }
            $__bulkChargedTotal += $__bulkItemCharge;
            continue;
        }
        $get_username_Check = $marzban_list_get['type'] != "Manualsale" ? $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc) : null;
        if (isset($get_username_Check['username']) || rxTableValueExists('invoice', 'username', $username_acc)) {
            $username_acc = $random_number . "-" . $username_acc;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac, true);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت اکانت در بخش انبوه
<blockquote>✍️ دلیل خطا :
{$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            step('home', $from_id);
            return;
        }
        $username_acc = rx_adopt_created_username($dataoutput, $username_acc);
        rx_notify_username_renamed($from_id, $dataoutput, $marzban_list_get);
        $invoiceIpLimit = (string)(isset($info_product['ip_limit']) ? intval($info_product['ip_limit']) : 0);
        $invoiceHwidLimit = (string)(isset($info_product['hwid_limit']) ? intval($info_product['hwid_limit']) : 0);
        $invoiceSymbolicLimitEnabled = (string)($info_product['symbolic_limit_enabled'] ?? '0');
        $invoiceSymbolicLimitUsers = (string)(isset($info_product['symbolic_limit_users']) ? intval($info_product['symbolic_limit_users']) : 0);
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions,ip_limit,hwid_limit,symbolic_limit_enabled,symbolic_limit_users) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?,?,?,?,?)");
        $Status = "active";
        $stmt->bind_param("sssssssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions, $invoiceIpLimit, $invoiceHwidLimit, $invoiceSymbolicLimitEnabled, $invoiceSymbolicLimitUsers);
        $stmt->execute();
        $stmt->close();
        $config = "";
        $output_config_link = rxShouldShowConnectionLink($marzban_list_get, $dataoutput['file_ext'] ?? null) ? rxResolveConnectionLink($marzban_list_get, $dataoutput['subscription_url'], $dataoutput['file_ext'] ?? null) : "";
        if ($marzban_list_get['config'] == "onconfig") {
            if (is_array($dataoutput['configs'])) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= "\n" . $configs;
                }
            }
        }
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        if ($marzban_list_get['type'] == "WGDashboard") {
            $datatextbot['textafterpay'] = "✅ سرویس با موفقیت ایجاد شد

👤 نام کاربری سرویس : {username}
🌿 نام سرویس:  {name_service}
‏🇺🇳 لوکیشن: {location}
⏳ مدت زمان: {day}  روز
🗜 حجم سرویس:  {volume} گیگابایت

🧑‍🦯 شما میتوانید شیوه اتصال را  با فشردن دکمه زیر و انتخاب سیستم عامل خود را دریافت کنید";
        }
        $textcreatuser = str_replace('{username}', "<code>" . guardDisplayUsername($dataoutput['username'], $marzban_list_get) . "</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
        if (function_exists('balance_atomic_charge')) {
            $__bulkChargeLive = balance_atomic_charge($from_id, (float)$__bulkItemCharge, $__bulkAllowNeg);
            if (!empty($__bulkChargeLive['ok']) && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'debit', $__bulkItemCharge, 'purchase', 'خرید انبوه سرویس', (string)$randomString, 'invoice', (string)$randomString);
            }
        } else {
            update("user", "Balance", select("user", "Balance", "id", $from_id, "select")['Balance'] - $__bulkItemCharge, "id", $from_id);
        }
        $__bulkChargedTotal += $__bulkItemCharge;
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    $__bulkBalanceRow = select("user", "*", "id", $from_id, "select");
    $Balance_prim = (float)($__bulkBalanceRow['Balance'] ?? 0);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $balanceformatsellbefore = number_format($user['Balance'], 0);
    $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
    $count_service = $user['Processing_value_four'];
    $rxFmtBulkInfoProductPrice = rxFormatToman($info_product['price_product']);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = "📣 جزئیات ساخت اکانت انبوه در ربات شما ثبت شد .
<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$username_ac}_0-$count_service</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$user['Processing_value']}</blockquote>
<blockquote>▫️نام محصول :{$info_product['name_product']}</blockquote>
<blockquote>▫️زمان خریداری شده :{$info_product['Service_time']} روز</blockquote>
<blockquote>▫️حجم خریداری شده : {$info_product['Volume_constraint']} GB</blockquote>
<blockquote>▫️موجودی قبل خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️کد پیگیری: $randomString</blockquote>
<blockquote>▫️نوع کاربر : {$user['agent']}</blockquote>
<blockquote>▫️شماره تلفن کاربر : {$user['number']}</blockquote>
<blockquote>▫️قیمت محصول : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️قیمت نهایی : {$rxFmtBulkInfoProductPrice} تومان</blockquote>
<blockquote>▫️تعداد کانفیگ : {$user['Processing_value_four']} عدد</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        rx_sendTopicReport([
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ], ['flow' => 'bulk_buy', 'order_id' => (string) ($randomString ?? ''), 'user_id' => (string) $from_id]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('new_sub', [
            'user_id'    => $from_id,
            'amount'     => $info_product['name_product'],
            'price'      => number_format((float)$pricebulk),
            'panel_name' => $user['Processing_value'] ?? '',
            'category'   => $info_product['category'] ?? '',
        ], $setting);
    }
    step('home', $from_id);
} elseif ($datain == "Add_Balance") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ((($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && $user['step'] != "get_number" && $user['number'] == "none" && !rx_auth_skip_user($user)) {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && (($setting['get_number'] == "onAuthenticationphone") || ($setting['iran_number'] == "onAuthenticationiran")) && !rx_auth_skip_user($user))
        return;
    $randomWalletStatusRow = select("PaySetting", "ValuePay", "NamePay", "randomwallet_status", "select");
    $randomWalletStatusValue = is_array($randomWalletStatusRow) ? (string)($randomWalletStatusRow['ValuePay'] ?? '0') : '0';
    if ($randomWalletStatusValue === '1') {
        $rwAmounts = rx_randomWalletAmounts();
        $_rx_acc_s = (isset($_rx_acc_styles) && is_array($_rx_acc_styles)) ? $_rx_acc_styles : [];
        $rwRows = [];
        for ($rwI = 0; $rwI < count($rwAmounts); $rwI += 2) {
            $rwRow = [rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI]], 'rw_pick', $_rx_acc_s)];
            if (isset($rwAmounts[$rwI + 1])) {
                $rwRow[] = rx_kb_style(['text' => number_format((int)$rwAmounts[$rwI + 1]) . " تومان", 'callback_data' => "rw_pick_" . (int)$rwAmounts[$rwI + 1]], 'rw_pick', $_rx_acc_s);
            }
            $rwRows[] = $rwRow;
        }
        $rwRows[] = [rx_kb_style(['text' => "✍️ رقم دلخواه", 'callback_data' => "rw_custom"], 'rw_custom', $_rx_acc_s)];
        $rwRows[] = [rx_kb_style(['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"], 'backinfo', $_rx_acc_s)];
        $rwKeyboard = json_encode(['inline_keyboard' => $rwRows], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_select_amount'] ?? "💰 مبلغ مورد نظر را برای شارژ کیف پول انتخاب کنید:", $rwKeyboard, 'HTML');
        return;
    }
    rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
} elseif (preg_match('/^rw_pick_(\d+)$/', (string)$datain, $rwPickMatch)) {
    $rwAmount = (int)$rwPickMatch[1];
    deletemessage($from_id, $message_id);
    rx_walletChargeAmountAccepted($from_id, $user, $rwAmount);
} elseif ($datain == "rw_custom") {
    rx_promptWalletChargeCustomAmount($from_id, $message_id, $user);
} elseif ($datain == "rcc_cancel") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null);
    } else {
        sendmessage($from_id, $datatextbot['dyn_wallet_recheck_canceled'] ?? "❌ درخواست بررسی مجدد لغو شد.", null, 'HTML');
    }
} elseif ($datain == "recheckcrypto") {
    if (function_exists('crypto_active_wallets') && empty(crypto_active_wallets())) {
        $rccNoWalletMsg = "⚠️ در حال حاضر هیچ درگاه پرداخت کریپتویی فعال نیست. لطفاً با پشتیبانی تماس بگیرید.";
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $rccNoWalletMsg, null);
        } else {
            sendmessage($from_id, $rccNoWalletMsg, null, 'HTML');
        }
        step('home', $from_id);
        return;
    }
    $rccListRows = [];
    try {
        $rccListStmt = $pdo->prepare(
            "SELECT id_order, crypto_currency, crypto_amount, price, time
               FROM Payment_report
              WHERE id_user = :u
                AND payment_Status = 'reject'
                AND crypto_tx_hash IS NOT NULL
                AND crypto_tx_hash <> ''
              ORDER BY id DESC
              LIMIT 10"
        );
        $rccListStmt->execute([':u' => (string) $from_id]);
        $rccListRows = $rccListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {  }

    if (empty($rccListRows)) {
        $rccEmptyMsg = "📭 <b>هیچ تراکنش رد شده‌ای ندارید</b>\n\n"
            . "تراکنش‌های کریپتویی شما که توسط ربات رد شده باشن، در این بخش نمایش داده میشن.";
        if ($message_id) {
            Editmessagetext($from_id, $message_id, $rccEmptyMsg, null);
        } else {
            sendmessage($from_id, $rccEmptyMsg, null, 'HTML');
        }
        step('home', $from_id);
        return;
    }

    $rccKbRows = [];
    foreach ($rccListRows as $r) {
        $rccLabel = '🪙 ' . (string) ($r['crypto_currency'] ?? '?')
            . ' | ' . number_format((int) ($r['price'] ?? 0)) . ' ت'
            . ' | ' . (string) ($r['id_order'] ?? '');
        $rccKbRows[] = [
            ['text' => $rccLabel, 'callback_data' => 'rcc_view_' . (string) ($r['id_order'] ?? '')],
        ];
    }
    $rccKb = json_encode(['inline_keyboard' => $rccKbRows], JSON_UNESCAPED_UNICODE);
    $rccListMsg = "🔁 <b>تراکنش‌های رد شده شما</b>\n\n"
        . "روی هر تراکنش کلیک کنید تا جزئیاتش رو ببینید و در صورت لزوم برای بررسی دستی ادمین ارسال کنید.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccListMsg, $rccKb);
    } else {
        sendmessage($from_id, $rccListMsg, $rccKb, 'HTML');
    }
    step('home', $from_id);
} elseif (strpos((string) $datain, 'rcc_view_') === 0) {
    $rccVOid = substr((string) $datain, strlen('rcc_view_'));
    $rccVRow = null;
    try {
        $rccVStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccVStm->execute([':o' => $rccVOid, ':u' => (string) $from_id]);
        $rccVRow = $rccVStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccVRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccVExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccVRow['crypto_currency'] ?? ''), (string) ($rccVRow['crypto_tx_hash'] ?? ''))
        : '';
    $rccVReasonRaw = (string) ($rccVRow['crypto_last_error'] ?? ($rccVRow['dec_not_confirmed'] ?? ''));
    $rccVReason = $rccVReasonRaw !== '' ? mb_substr($rccVReasonRaw, 0, 200) : '—';

    $rccVText = "🧾 <b>جزئیات تراکنش رد شده</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccVOid) . "</code>\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccVRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccVRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccVRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccVRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📅 زمان: " . htmlspecialchars((string) ($rccVRow['time'] ?? '-')) . "\n"
        . "📝 دلیل عدم تایید: " . htmlspecialchars($rccVReason)
        . ($rccVExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccVExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

    $rccVKb = json_encode([
        'inline_keyboard' => [
            [['text' => '📨 ارسال مجدد به ادمین', 'callback_data' => 'rcc_resubmit_' . $rccVOid]],
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'recheckcrypto']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccVText, $rccVKb);
    } else {
        sendmessage($from_id, $rccVText, $rccVKb, 'HTML');
    }
} elseif (strpos((string) $datain, 'rcc_resubmit_') === 0) {
    $rccROid = substr((string) $datain, strlen('rcc_resubmit_'));
    $rccRRow = null;
    try {
        $rccRStm = $pdo->prepare(
            "SELECT * FROM Payment_report
              WHERE id_order = :o AND id_user = :u AND payment_Status = 'reject'
              LIMIT 1"
        );
        $rccRStm->execute([':o' => $rccROid, ':u' => (string) $from_id]);
        $rccRRow = $rccRStm->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {  }

    if (!is_array($rccRRow)) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ یافت نشد یا قبلاً پردازش شده',
                'show_alert' => true,
            ]);
        }
        return;
    }

    try {
        $rccRUpd = $pdo->prepare(
            "UPDATE Payment_report SET payment_Status = 'ManualPending', at_updated = :now
              WHERE id_order = :o AND payment_Status = 'reject'"
        );
        $rccRUpd->execute([':now' => date('Y/m/d H:i:s'), ':o' => $rccROid]);
        if ($rccRUpd->rowCount() < 1) {
            if ($callback_query_id && function_exists('telegram')) {
                @telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => '❌ قبلاً ارسال شده',
                    'show_alert' => true,
                ]);
            }
            return;
        }
    } catch (Throwable $e) {
        if ($callback_query_id && function_exists('telegram')) {
            @telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ خطای داخلی',
                'show_alert' => true,
            ]);
        }
        return;
    }

    $rccRExpUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url((string) ($rccRRow['crypto_currency'] ?? ''), (string) ($rccRRow['crypto_tx_hash'] ?? ''))
        : '';
    $rccRUserTag = '@' . ($user['username'] ?? 'none');
    $rccRReasonRaw = (string) ($rccRRow['crypto_last_error'] ?? ($rccRRow['dec_not_confirmed'] ?? ''));
    $rccRReason = $rccRReasonRaw !== '' ? mb_substr($rccRReasonRaw, 0, 200) : '—';
    $rccRAdminCaption = "🔁 <b>درخواست بررسی دستی توسط کاربر</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$rccRUserTag})\n"
        . "💎 ارز: <b>" . htmlspecialchars((string) ($rccRRow['crypto_currency'] ?? '-')) . "</b>\n"
        . "🪙 مقدار: <code>" . htmlspecialchars((string) ($rccRRow['crypto_amount'] ?? '-')) . "</code>\n"
        . "💸 معادل تومانی: " . number_format((int) ($rccRRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars((string) ($rccRRow['crypto_tx_hash'] ?? '-')) . "</code>\n"
        . "📝 دلیل اولیه رد: " . htmlspecialchars($rccRReason)
        . ($rccRExpUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($rccRExpUrl, ENT_QUOTES) . "\">مشاهده در بلاکچین</a>" : '');

    if (function_exists('crypto_lookup_verified_hash')) {
        $rccRExisting = crypto_lookup_verified_hash((string) ($rccRRow['crypto_tx_hash'] ?? ''));
        if (is_array($rccRExisting)) {
            $rccRAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string) $rccRExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string) ($rccRExisting['user_id'] ?? '-')) . "</code>";
        }
    }

    $rccRAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید خودکار', 'callback_data' => 'cmauto_' . $rccROid],
                ['text' => '✏️ تایید دستی',   'callback_data' => 'cmmanual_' . $rccROid],
            ],
            [
                ['text' => '🗑️ لغو و حذف از دیتابیس', 'callback_data' => 'cmdelete_' . $rccROid],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $rccRAdminIds = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($rccRAdminIds)) {
        $rccRAdminIds = [];
    }
    if (function_exists('telegram')) {
        foreach ($rccRAdminIds as $rccRAdminOne) {
            if (!is_numeric($rccRAdminOne)) continue;
            @telegram('sendmessage', [
                'chat_id' => (string)$rccRAdminOne,
                'text' => $rccRAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccRAdminKb,
            ]);
        }
    }

    $rccRUserMsg = "✅ <b>درخواست شما برای بررسی دستی ارسال شد</b>\n\n"
        . "🛒 کد فاکتور: <code>" . htmlspecialchars($rccROid) . "</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام می‌شود.";
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $rccRUserMsg, null);
    } else {
        sendmessage($from_id, $rccRUserMsg, null, 'HTML');
    }
} elseif (preg_match('/^rcc_pick_([A-Za-z0-9_]{2,20})$/', (string) $datain, $rccPick)
    && in_array($rccPick[1], array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency'), true)
) {
    $rccCur = $rccPick[1];
    update("user", "Processing_value_one", $rccCur, "id", $from_id);
    $rccWalletsFa = array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'label', 'currency');
    $rccCurFa = [
        'TRX' => 'ترون (TRX)',
        'TON' => 'تون (TON)',
        'USDT_TRC20' => 'تتر روی ترون (USDT-TRC20)',
        'USDT_TON' => 'تتر روی تون (USDT-TON)',
    ][$rccCur] ?? ($rccWalletsFa[$rccCur] ?? $rccCur);
    $askAmount = "💎 ارز انتخابی: <b>{$rccCurFa}</b>\n\n"
               . "🪙 لطفاً <b>مقدار ارز پرداخت‌شده</b> را وارد کنید (مثلاً <code>0.2</code> یا <code>1.25</code>):";
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    if ($message_id) {
        Editmessagetext($from_id, $message_id, $askAmount, $cancelKb);
    } else {
        sendmessage($from_id, $askAmount, $cancelKb, 'HTML');
    }
    step('rcc_amount', $from_id);
} elseif ($user['step'] == "rcc_amount" && empty($datain)) {
    $coinAmount = trim(str_replace([',', '،'], ['.', '.'], (string) $text));
    if (!is_numeric($coinAmount) || (float) $coinAmount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_amount_not_numeric'] ?? "❌ مقدار وارد شده عددی نیست. لطفاً عددی مثل <code>0.2</code> ارسال کنید.", null, 'HTML');
        return;
    }
    $rccCurForRate = (string) ($user['Processing_value_one'] ?? '');
    $rateForCalc = function_exists('crypto_irt_rate_for') ? crypto_irt_rate_for($rccCurForRate) : null;
    if ($rateForCalc === null || $rateForCalc <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_rate_unavailable'] ?? "❌ نرخ لحظه‌ای ارز در دسترس نیست. لطفاً دقایقی دیگر تلاش کنید.", null, 'HTML');
        return;
    }
    $calcIrr = (int) round(((float) $coinAmount) * $rateForCalc);
    if ($calcIrr <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_calculated_amount_invalid'] ?? "❌ مبلغ محاسبه‌شده معتبر نیست. مقدار ارز را بررسی کنید.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_tow", $coinAmount, "id", $from_id);
    update("user", "Processing_value", (string) $calcIrr, "id", $from_id);
    $cancelKb = json_encode(['inline_keyboard' => [[['text' => '❌ انصراف', 'callback_data' => 'rcc_cancel']]]], JSON_UNESCAPED_UNICODE);
    sendmessage(
        $from_id,
        "💰 <b>محاسبه‌ی خودکار قیمت</b>\n\n"
        . "💎 ارز: <b>{$rccCurForRate}</b>\n"
        . "🪙 مقدار: <code>{$coinAmount}</code>\n"
        . "📈 نرخ لحظه‌ای: <code>" . number_format((float) $rateForCalc) . "</code> تومان\n"
        . "💵 <b>معادل تومانی محاسبه‌شده:</b> " . number_format($calcIrr) . " تومان\n\n"
        . "🔗 حالا <b>هش (TxID) تراکنش</b> را ارسال کنید:\n"
        . "<i>می‌توانید لینک کامل Tonviewer / Tonscan / Tronscan یا هش خام را بفرستید.</i>",
        $cancelKb,
        'HTML'
    );
    step('rcc_hash', $from_id);
} elseif ($user['step'] == "rcc_hash" && empty($datain)) {
    $hashTry = function_exists('crypto_extract_hash') ? crypto_extract_hash((string) $text) : null;
    if ($hashTry === null) {
        sendmessage($from_id, $datatextbot['dyn_wallet_hash_not_found'] ?? "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را بفرستید — نه آدرس کیف‌پول.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_four", $hashTry, "id", $from_id);
    $photoKb = json_encode([
        'inline_keyboard' => [
            [['text' => '⏭ ارسال بدون عکس (رد شدن)', 'callback_data' => 'rcc_skip_photo']],
            [['text' => '❌ انصراف',                    'callback_data' => 'rcc_cancel']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, $datatextbot['dyn_wallet_ask_receipt_photo'] ?? "📸 <b>عکس رسید/اسکرین‌شات تراکنش</b> را ارسال کنید (اختیاری).\n\nاگر عکسی ندارید، روی دکمه «ارسال بدون عکس» بزنید.", $photoKb, 'HTML');
    step('rcc_photo', $from_id);
} elseif ($user['step'] == "rcc_photo" && ($datain == "rcc_skip_photo" || !empty($photoid))) {
    $rccCur     = (string) $user['Processing_value_one'];
    $rccCoin    = (string) $user['Processing_value_tow'];
    $rccIrr     = (int)    $user['Processing_value'];
    $rccHash    = (string) $user['Processing_value_four'];
    $rccPhotoId = $datain === "rcc_skip_photo" ? '' : (string) $photoid;
    $rccKnownCurs = array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency');
    if (!in_array($rccCur, $rccKnownCurs, true) || $rccCoin === '' || $rccIrr <= 0 || $rccHash === '') {
        sendmessage($from_id, $datatextbot['dyn_wallet_incomplete_info'] ?? "❌ اطلاعات ناقص است. لطفاً از ابتدا تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }
    try {
        $dupChk = $pdo->prepare("SELECT id_order FROM Payment_report WHERE crypto_tx_hash = :h LIMIT 1");
        $dupChk->execute([':h' => $rccHash]);
        if ($dupChk->fetch()) {
            sendmessage($from_id, $datatextbot['dyn_wallet_hash_already_used'] ?? "❌ این هش قبلاً برای فاکتور دیگری ثبت شده است. اگر این پرداخت متعلق به شماست، با پشتیبانی تماس بگیرید.", null, 'HTML');
            step('home', $from_id);
            return;
        }
    } catch (Throwable $e) {  }
    $rccOrderId = bin2hex(random_bytes(6));
    $rccNow = date('Y/m/d H:i:s');
    $rccCoinFmt = number_format((float) $rccCoin, 9, '.', '');
    $rccCoinFmt = rtrim(rtrim($rccCoinFmt, '0'), '.');
    $rccManualCurs = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    if (isset($rccManualCurs[$rccCur])) {
        $rccNetwork = (string) $rccManualCurs[$rccCur]['network'];
    } else {
        $rccNetwork = in_array($rccCur, ['TRX', 'USDT_TRC20'], true) ? 'TRON' : 'TON';
    }
    try {
        $rccIns = $connect->prepare(
            "INSERT INTO Payment_report
                (id_user, id_order, time, price, payment_Status, Payment_Method,
                 id_invoice, crypto_currency, crypto_network, crypto_amount, crypto_tx_hash, crypto_hash_at, dec_not_confirmed)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $userIdStr = (string) $from_id;
        $statusManual = 'ManualPending';
        $methodLabel = 'manual crypto recheck';
        $invoiceMeta = '0|0';
        $irrStr = (string) $rccIrr;
        $nowUnix = time();
        $rccIns->bind_param(
            'sssssssssssis',
            $userIdStr, $rccOrderId, $rccNow, $irrStr, $statusManual, $methodLabel,
            $invoiceMeta, $rccCur, $rccNetwork, $rccCoinFmt, $rccHash, $nowUnix, $rccPhotoId
        );
        $rccIns->execute();
        $rccIns->close();
    } catch (Throwable $e) {
        error_log('[recheckcrypto] insert failed: ' . $e->getMessage());
        sendmessage($from_id, $datatextbot['dyn_wallet_internal_error_retry'] ?? "❌ خطای داخلی در ثبت درخواست. لطفاً دوباره تلاش کنید.", null, 'HTML');
        step('home', $from_id);
        return;
    }

    sendmessage(
        $from_id,
        "✅ <b>درخواست بررسی دستی شما ثبت شد.</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "💎 ارز: <b>{$rccCur}</b>\n"
        . "🪙 مقدار: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>" . substr($rccHash, 0, 16) . "…</code>\n\n"
        . "⏰ پس از بررسی توسط ادمین، نتیجه از طریق همین چت اعلام خواهد شد.",
        null,
        'HTML'
    );

    $explorerUrl = function_exists('crypto_explorer_url')
        ? crypto_explorer_url($rccCur, $rccHash)
        : '';
    $userTagLine = '@' . ($user['username'] ?? 'none');
    $rccAdminCaption = "🔁 <b>درخواست بررسی دستی پرداخت کریپتو</b>\n\n"
        . "🛒 کد پیگیری: <code>{$rccOrderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$userTagLine})\n"
        . "💎 ارز: <b>{$rccCur}</b> ({$rccNetwork})\n"
        . "🪙 مقدار ادعاشده: <code>{$rccCoinFmt}</code>\n"
        . "💸 معادل تومانی ادعاشده: " . number_format($rccIrr) . " تومان\n"
        . "🔗 هش: <code>{$rccHash}</code>"
        . ($explorerUrl !== '' ? "\n🔍 <a href=\"" . htmlspecialchars($explorerUrl, ENT_QUOTES) . "\">مشاهده در مرورگر بلاکچین</a>" : '');
    if (function_exists('crypto_lookup_verified_hash')) {
        $rccExisting = crypto_lookup_verified_hash($rccHash);
        if (is_array($rccExisting)) {
            $rccAdminCaption .= "\n\n⚠️ <b>هشدار: این هش قبلاً تایید شده</b>\n"
                . "🛒 فاکتور قبلی: <code>" . htmlspecialchars((string)$rccExisting['order_id']) . "</code>\n"
                . "👤 کاربر قبلی: <code>" . htmlspecialchars((string)($rccExisting['user_id'] ?? '-')) . "</code>\n"
                . "📅 تایید در: " . htmlspecialchars((string)($rccExisting['verified_at'] ?? '-'));
        }
    }
    $rccAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $rccOrderId],
                ['text' => '❌ رد درخواست',           'callback_data' => 'rejectcryptomanual_' . $rccOrderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $admin_ids_local = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($admin_ids_local)) $admin_ids_local = [];
    foreach ($admin_ids_local as $idAdminLocal) {
        if (!is_numeric($idAdminLocal)) continue;
        if ($rccPhotoId !== '') {
            telegram('sendphoto', [
                'chat_id' => $idAdminLocal,
                'photo'   => $rccPhotoId,
                'caption' => $rccAdminCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => $rccAdminKb,
            ]);
        } else {
            sendmessage((string) $idAdminLocal, $rccAdminCaption, $rccAdminKb, 'HTML');
        }
    }
    if (!empty($setting['Channel_Report'])) {
        $payload = [
            'chat_id' => $setting['Channel_Report'],
            'caption' => $rccAdminCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => $rccAdminKb,
        ];
        if (!empty($paymentreports)) {
            $payload['message_thread_id'] = $paymentreports;
        }
        if ($rccPhotoId !== '') {
            $payload['photo'] = $rccPhotoId;
            telegram('sendphoto', $payload);
        } else {
            unset($payload['caption']);
            $payload['text'] = $rccAdminCaption;
            telegram('sendmessage', $payload);
        }
    }
    step('home', $from_id);
} elseif ($user['step'] == "getprice") {
    if (!isset($update['message']) && empty($text)) { return; }
    deletemessage($from_id, $user['Processing_value']);
    rx_walletChargeAmountAccepted($from_id, $user, $text);
} elseif ($datain == "chargenodiscount") {
    update("user", "Processing_value_four", "", "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment);
    step('get_step_payment', $from_id);
} elseif ($datain == "chargehasdiscount") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscountcharge', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscountcharge") {
    if (!isset($update['message']) && empty($text)) { return; }
    $__amount = intval($user['Processing_value']);
    if ($__amount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_invalid_charge_amount'] ?? "❌ مبلغ شارژ نامعتبر است. مجددا تلاش کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!rxTableValueExists('DiscountSell', 'codeDiscount', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $dv = MiniDiscount::validateSell($text, 'charge', '', '', '', $user);
    if (empty($dv['ok'])) {
        sendmessage($from_id, $dv['reason'], $backuser, 'HTML');
        return;
    }
    $__gatewayAmount = (int) round(MiniDiscount::applyToPrice($dv['row'], $__amount));
    if ($__gatewayAmount <= 0) {
        sendmessage($from_id, $datatextbot['dyn_wallet_discount_not_applicable_zero'] ?? "❌ این کد برای شارژ قابل استفاده نیست (مبلغ پرداختی صفر می‌شود).", $backuser, 'HTML');
        return;
    }
    $__bonus = $__amount - $__gatewayAmount;
    if ($__bonus < 0) $__bonus = 0;
    update("user", "Processing_value", $__gatewayAmount, "id", $from_id);
    update("user", "Processing_value_four", "chg|" . $__bonus . "|" . $text . "|" . $__amount, "id", $from_id);
    $__amountfmt  = number_format($__amount, 0);
    $__gatewayfmt = number_format($__gatewayAmount, 0);
    $__txt = "🤩 کد تخفیف {$dv['label']} روی شارژ کیف پول اعمال شد.\n\n💎 مبلغ شارژ کیف پول : {$__amountfmt} تومان\n💸 مبلغ قابل پرداخت : {$__gatewayfmt} تومان\n\nروش پرداخت را انتخاب کنید:";
    sendmessage($from_id, $__txt, $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    if (isset($update['message']) && empty($datain)) { return; }
    if (!preg_match('/^sbv(resume|cancel)_\w+$/', (string) $datain) && !rx_ck_bot_payment_guard($user, $message_id)) { return; }
    $__chargeBonus = function_exists('nm_pending_charge_bonus') ? nm_pending_charge_bonus($user) : 0;
    if ($datain == "cart_to_offline") {
        $from_id_sql = (string) $from_id;

        $_cc_keep_invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $_sbv_reuse = function_exists('rx_sbv_find_reusable') ? rx_sbv_find_reusable($from_id_sql, $_cc_keep_invoice, $user['Processing_value']) : null;
        if ($_sbv_reuse === null && function_exists('rx_sbv_active_payment')) {
            $_sbv_active = rx_sbv_active_payment($from_id_sql);
            if ($_sbv_active !== null) {
                rx_sbv_bot_pending_notice($from_id, $message_id, $_sbv_active);
                return;
            }
        }
        if ($_sbv_reuse === null) {
            $stale_cutoff = date('Y/m/d H:i:s', time() - 15 * 60);
            rxCancelAbandonedCardPaymentsForUser($from_id_sql, 'stale_unpaid', [
                'methods' => ['cart to cart'],
                'created_before' => $stale_cutoff,
                'preserve_invoice_key' => $_cc_keep_invoice,
            ]);
            rxCancelAbandonedCardPaymentsForUser($from_id_sql, 'superseded', [
                'methods' => ['cart to cart'],
                'preserve_invoice_key' => $_cc_keep_invoice,
            ]);

            $_stmt = $connect->prepare("SELECT id FROM Payment_report WHERE id_user = ? AND (payment_Status = 'Unpaid' OR payment_Status = 'waiting' OR payment_Status = 'pending') AND Payment_Method = 'cart to cart' LIMIT 1");
            $_stmt->bind_param("s", $from_id_sql);
            $_stmt->execute();
            $checkpay = $_stmt->get_result();
            $_stmt->close();
            if (mysqli_num_rows($checkpay) != 0) {
                sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['issetpay'], null, 'HTML');
                return;
            }
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancecart", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancecart", "select")['ValuePay'];
        // برای سفارش مستقیم سرویس، محدودیت شارژ کیف پول اعمال نشود؛ این محدودیت فقط برای شارژ عادی است.
        $rxInvoiceOne = trim((string)($user['Processing_value_one'] ?? '0'));
        $rxInvoiceTwo = trim((string)($user['Processing_value_tow'] ?? '0'));
        $rxIsServiceOrderPayment = !in_array($rxInvoiceOne, ['', '0', 'none'], true)
            || !in_array($rxInvoiceTwo, ['', '0', 'none'], true);
        if (!$rxIsServiceOrderPayment && ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance)) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        $_uid = intval($from_id);
        $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = {$_uid})";
        $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
        $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
        $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

        $card_info = null;
        $_card2 = null;

        if ($_cmode == 'direct') {
            $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
            $_dcards = [];
            if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
            $card_info = $_dcards[0] ?? null;
            $_card2 = $_dcards[1] ?? null;
        } else {
            $_ck = "card_cycle_{$_uid}";
            $_ck_esc = mysqli_real_escape_string($connect, $_ck);
            $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
            $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
            $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
            $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
            $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

            $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

            if (!$card_info) {
                $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
                $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
                $_seen = [];
            }

            if ($card_info) {
                $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
                $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
                mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
                if (mysqli_affected_rows($connect) === 0) {
                    mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
                }
            }
        }

        if (!$card_info || empty($card_info['cardnumber']) || empty($card_info['namecard'])) {
            sendmessage($from_id, $datatextbot['dyn_wallet_no_active_card'] ?? "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
            return;
        }

        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        $_sbv_new = false;
        if ($_sbv_reuse !== null) {
            $randomString = (string) $_sbv_reuse['id_order'];
        } else {
            $randomString = bin2hex(random_bytes(5));
            if (function_exists('rx_sbv_enabled') && rx_sbv_enabled()) {
                $_sbv_prep = rx_sbv_prepare($randomString, $from_id_sql, $user['Processing_value']);
                if (empty($_sbv_prep['ok']) && ($_sbv_prep['reason'] ?? '') === 'active_exists') {
                    rx_sbv_bot_pending_notice($from_id, 0, $_sbv_prep['active']);
                    return;
                }
                if (empty($_sbv_prep['ok'])) {
                    $_sbv_err =($_sbv_prep['reason'] ?? '') === 'exhausted'
                        ? "❌ در حال حاضر امکان ساخت مبلغ یکتا برای این مبلغ وجود ندارد. لطفاً چند دقیقه دیگر دوباره تلاش کنید یا روش پرداخت دیگری انتخاب کنید."
                        : faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید');
                    sendmessage($from_id, $_sbv_err, $keyboard, 'HTML');
                    return;
                }
                $_sbv_new = true;
            }
        }
        $_sbv_bot = (function_exists('rx_sbv_bot_payment_view') && ($_sbv_new || $_sbv_reuse !== null)) ? rx_sbv_bot_payment_view($randomString) : null;
        $valueprice = $_sbv_bot !== null ? $_sbv_bot['valueprice'] : number_format($user['Processing_value']);
        $price_copy = $_sbv_bot !== null ? $_sbv_bot['price_copy'] : intval($user['Processing_value'] . "0");

        if ($_cmode == 'direct' && $_card2) {
            $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
        } else {
            $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
            $textcart = strtr($datatextbot['text_cart'], $replacements);
        }
        if ($_sbv_bot !== null) {
            $textcart .= $_sbv_bot['block'];
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $payment_Status = "Unpaid";
        $Payment_Method = "cart to cart";
        $_cc_insert_ok = false;
        if ($_sbv_reuse !== null) {
            $_cc_insert_ok = true;
        } else {
            try {
                $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                if ($stmt) {
                    $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                    $_cc_insert_ok = $stmt->execute() && $stmt->affected_rows === 1;
                    $_cc_insert_err = $stmt->error;
                    $stmt->close();
                } else {
                    $_cc_insert_err = $connect->error;
                }
            } catch (Throwable $_cc_insert_e) {
                $_cc_insert_ok = false;
                $_cc_insert_err = $_cc_insert_e->getMessage();
            }
        }
        if (!$_cc_insert_ok && $_sbv_new) {
            rx_sbv_abandon($randomString);
        }
        if (!$_cc_insert_ok) {
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_INSERT_FAILED', (string) ($_cc_insert_err ?? 'unknown'), [
                    'id_user' => $from_id,
                    'id_order' => $randomString,
                ]);
            }
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        }

        $_cv_active = !in_array($from_id, $admin_ids)
            && ($setting['card_verify_status'] ?? 'offcardverify') === 'oncardverify'
            && intval($user['card_verify_bypass'] ?? 0) !== 1;
        if ($_cv_active) {
            $_cv_min = (int)($setting['card_verify_min_amount'] ?? 0);
            if ($_cv_min > 0 && (int)$user['Processing_value'] < $_cv_min) {
                $_cv_active = false;
            }
        }
        if ($_cv_active) {
            deletemessage($from_id, $message_id);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text'              => '🔒 احراز هویت کارت‌به‌کارت فعال است',
                'show_alert'        => true,
            ]);
            update("user", "Processing_value", $randomString, "id", $from_id);

            $_vc_rows = [];
            $_vc_result = $connect->prepare("SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $_vc_result->bind_param("s", $from_id);
            $_vc_result->execute();
            $_vc_res = $_vc_result->get_result();
            while ($_vc_row = $_vc_res->fetch_assoc()) {
                $_vc_rows[] = $_vc_row['last4'];
            }
            $_vc_result->close();

            if (!empty($_vc_rows)) {
                $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
                $_vc_keyboard_rows = [];
                foreach ($_vc_rows as $_vc_last4) {
                    $_vc_keyboard_rows[] = [rx_kb_style(['text' => "💳 **** **** **** {$_vc_last4}", 'callback_data' => "cv_use_{$_vc_last4}"], 'cv_use', $_prcpt_s)];
                }
                $_vc_keyboard_rows[] = [rx_kb_style(['text' => "📷 پرداخت با کارت جدید", 'callback_data' => "cv_new"], 'cv_new', $_prcpt_s)];
                $_sbv_cancel_row = ($_sbv_bot !== null && function_exists('rx_sbv_cancel_row')) ? rx_sbv_cancel_row((string) $randomString) : null;
                if ($_sbv_cancel_row !== null) {
                    $_vc_keyboard_rows[] = $_sbv_cancel_row;
                }
                $_vc_kb = json_encode(['inline_keyboard' => $_vc_keyboard_rows]);
                step('card_select_step', $from_id);
                sendmessage($from_id, $datatextbot['dyn_wallet_select_verified_card'] ?? "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید یا با کارت جدید پرداخت کنید:", $_vc_kb, 'HTML');
            } else {
                step('card_photo_step', $from_id);
                sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.\n\n🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", ($_sbv_bot !== null && function_exists('rx_sbv_cancel_keyboard') && rx_sbv_cancel_keyboard((string) $randomString) !== null) ? rx_sbv_cancel_keyboard((string) $randomString) : $backuser, 'HTML');
            }
            return;
        }

        deletemessage($from_id, $message_id);
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        $_sbv_receipt_label = $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.";
        if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
            $_kb_rows = [[]];
            if ($setting['statuscopycart'] == "1") {
                $_kb_rows[0][] = ['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num'];
            }
            $_kb_rows[0][] = ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'];
            if ($setting['statuscopycart'] == "1" && isset($_cmode) && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
            }
            $_kb_rows[] = [rx_kb_style(['text' => $_sbv_receipt_label, 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)];
            $_sbv_cancel_row = ($_sbv_bot !== null && function_exists('rx_sbv_cancel_row')) ? rx_sbv_cancel_row((string) $randomString) : null;
            if ($_sbv_cancel_row !== null) {
                $_kb_rows[] = $_sbv_cancel_row;
            }
            $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
        } else {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [
                        rx_kb_style(['text' => $_sbv_receipt_label, 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                    ]
                ]
            ]);
        }
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], $data['text']);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], $data['text']);
            }
        }
        $message_id = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, "html");
        updatePaymentMessageId($message_id, $randomString);
    } elseif (preg_match('/^rxpgw_cancel_(zarinpal|plisio|nowpayment|iranpay2|tonpay|blupal|atlaspay|cubepay|variza|abangateway)_(\w+)$/', (string) $datain, $rxPgwMatch)) {
        $rxPgwGatewayKey = $rxPgwMatch[1];
        $rxPgwOrderId = $rxPgwMatch[2];
        $rxPgwMethodMap = rx_gateway_payment_method_map();
        $rxPgwMethod = $rxPgwMethodMap[$rxPgwGatewayKey] ?? $rxPgwGatewayKey;

        $rxPgwCancelled = cancelPendingGatewayInvoice($from_id, $rxPgwMethod, $rxPgwOrderId);
        if (!$rxPgwCancelled) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '❌ این فاکتور دیگر قابل لغو نیست.',
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            return;
        }

        $rxPgwLabel = rx_gateway_display_name($rxPgwGatewayKey);
        $rxPgwText = "✅ فاکتور قبلی لغو شد.\n\nبرای دریافت فاکتور جدید، دکمه زیر را بزنید.";
        $rxPgwKb = json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 دریافت فاکتور جدید {$rxPgwLabel}", 'callback_data' => $rxPgwGatewayKey]],
                [['text' => "🔙 بازگشت به منوی اصلی", 'callback_data' => 'backuser']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $rxPgwText, $rxPgwKb, 'HTML');
    } elseif ($datain == "zarinpal") {
        if ($user['Processing_value'] < 5000) {
            sendmessage($from_id, $textbotlang['users']['Balance']['zarinpal'], null, 'HTML');
            return;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancezarinpal", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancezarinpal", "select")['ValuePay'];
        // برای سفارش مستقیم سرویس، محدودیت شارژ کیف پول اعمال نشود؛ این محدودیت فقط برای شارژ عادی است.
        $rxInvoiceOne = trim((string)($user['Processing_value_one'] ?? '0'));
        $rxInvoiceTwo = trim((string)($user['Processing_value_tow'] ?? '0'));
        $rxIsServiceOrderPayment = !in_array($rxInvoiceOne, ['', '0', 'none'], true)
            || !in_array($rxInvoiceTwo, ['', '0', 'none'], true);
        if (!$rxIsServiceOrderPayment && ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance)) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'zarinpal');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'zarinpal');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $randomString = bin2hex(random_bytes(5));
        $pay = createPayZarinpal($user['Processing_value'], $randomString);
        if ($pay['data']['code'] != 100) {
            $text_error = json_encode($pay['errors']);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "⭕️ خطا در ساخت لینک زرین پال
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $dateacc = date('Y/m/d H:i:s');
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "zarinpal";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['data']['authority']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => "https://www.zarinpal.com/pg/StartPay/" . $pay['data']['authority']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $textnowpayments = "
✅ فاکتور پرداخت ایجاد شد.

🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان

❌ این تراکنش به مدت ۳۰ دقیقه اعتبار دارد پس از آن امکان پرداخت این تراکنش امکان ندارد.

📌لطفاً پس از پرداخت و موفق بودن تراکنش ، کمی صبر کنید تا پیام پرداخت موفق در سایت ما دریافت کنید. در غیراینصورت اکانت شما شارژ نخواهد شد.

جهت پرداخت از دکمه زیر استفاده کنید👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpzarinpal", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "plisio") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        if ($usdprice <= 1) {
            sendmessage($from_id, $textbotlang['users']['Balance']['nowpayments'], null, 'HTML');
            return;
        }
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceplisio", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceplisio", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'plisio');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'plisio');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = plisio($randomString, $trxprice);
        $Payment_Method = "plisio";
        if (!is_array($pay) || isset($pay['message']) || empty($pay['txn_id']) || empty($pay['invoice_url'])) {
            $text_error = is_array($pay) ? ($pay['message'] ?? json_encode($pay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : 'پاسخ نامعتبر از Plisio';
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['txn_id']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpplisio", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "nowpayment") {
        $rates = requireTronRates(['TRX', 'USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trx = $rates['TRX'];
        $usd = $rates['USD'];
        if (!is_numeric($trx) || (float) $trx <= 0 || !is_numeric($usd) || (float) $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $trxprice = round(((float) $user['Processing_value']) / (float) $trx, 2);
        $usdprice = round(((float) $user['Processing_value']) / (float) $usd, 2);
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalancenowpayment", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalancenowpayment", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'nowpayment');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'nowpayment');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $pay = nowPayments('invoice', $usdprice, $randomString, "order");
        $Payment_Method = "nowpayment";
        if (!is_array($pay) || empty($pay['id']) || empty($pay['invoice_url'])) {
            $text_error = json_encode($pay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت با درگاه ارزی داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $stmt->bind_param("ssssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice, $pay['id']);
        $stmt->execute();
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $pay['invoice_url']],
                ]
            ]
        ]);
        $price_format = number_format($user['Processing_value'], 0);
        $USD = number_format($usd);
        $textnowpayments = "
<b>💲 جهت افزایش اعتبار کیف پول خود از طریق ارز دیجیتال روی دکمه پرداخت در انتهای پیام کلیک کنید</b>

⚠️ توجه:  زمان پرداخت 30 دقیقه می باشد پس از 30 دقیقه تراکنش لغو خواهد شد

🌐 برخی از سایت های داخلی جهت خرید ارز دیجیتال 👇
🔸 nikpardakht.com
🔹 webpurse.org
🔸 bitpin.ir
🔹 sarmayex.com
🔸 ok-ex.io
🔹 nobitex.ir
🔸 bitbarg.com
🔹 cafearz.com
🔸 pay98.app
🔢 شماره فاکتور : $randomString
💰 مبلغ فاکتور : $price_format تومان
📊 قیمت دلار: $USD تومان تا این لحظه

<blockquote>⚠️ پس از پرداخت، در صورتی که مبلغ تراکنش به‌درستی واریز شده باشد، موجودی شما حداکثر تا ۱۵ دقیقه آینده به‌صورت خودکار شارژ خواهد شد.</blockquote>

جهت پرداخت از دکمه زیر استفاده👇🏻";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpnowpayment", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "iranpay2") {
        $mainbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "minbalanceiranpay2", "select")['ValuePay'];
        $maxbalanceplisio = select("PaySetting", "ValuePay", "NamePay", "maxbalanceiranpay2", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceplisio || $user['Processing_value'] > $maxbalanceplisio) {
            $mainbalanceplisio = number_format($mainbalanceplisio);
            $maxbalanceplisio = number_format($maxbalanceplisio);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceplisio, $maxbalanceplisio), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'Currency Rial 2');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'iranpay2');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Currency Rial 2";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = trnado($randomString, (float) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس ترونادو'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['status_code'])) {
                $errorLines[] = "کد وضعیت HTTP: " . $paymentErrorData['status_code'];
            }
            if (isset($paymentErrorData['errno'])) {
                $errorLines[] = "کد خطای cURL: " . $paymentErrorData['errno'];
            }
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (isset($paymentErrorData['raw_response'])) {
                $errorLines[] = "پاسخ خام: " . $paymentErrorData['raw_response'];
            }

            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $paymentToken = function_exists('tronadoExtractPaymentToken') ? tronadoExtractPaymentToken($payment) : (string) ($payment['Token'] ?? '');
        if ($paymentToken === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "dec_not_confirmed", $paymentToken, "id_order", $randomString);
        if (function_exists('tronadoV2Store')) {
            tronadoV2Store($randomString, $payment);
        }
        $paymentFullUrl = trim((string) ($payment['FullPaymentUrl'] ?? ''));
        if ($paymentFullUrl === '') {
            $paymentFullUrl = "https://t.me/tronado_robot/customerpayment?startapp={$paymentToken}";
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentFullUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .
❌ پس از تراکنش 15 تا یک ساعت زمان میبرد تا تراکنش تایید شود

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpiranpay2", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "tonpay") {
        $mainbalancetonpay = select("PaySetting", "ValuePay", "NamePay", "minbalancetonpay", "select")['ValuePay'];
        $maxbalancetonpay = select("PaySetting", "ValuePay", "NamePay", "maxbalancetonpay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancetonpay || $user['Processing_value'] > $maxbalancetonpay) {
            $mainbalancetonpay = number_format($mainbalancetonpay);
            $maxbalancetonpay = number_format($maxbalancetonpay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancetonpay, $maxbalancetonpay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'tonpay');
        if ($rxPendingGw !== null) {
            if (tonpayCustomFetchReport($rxPendingGw['id_order']) !== null) {
                tonpayCustomBotSend($from_id, $rxPendingGw['id_order']);
                return;
            }
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'tonpay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "tonpay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $tonpayCustomMode = tonpayPaymentMode() === 'custom';
        $payment = $tonpayCustomMode
            ? tonpayCustomCreateInvoice($randomString, (int) $user['Processing_value'], (int) $from_id)
            : tonpayCreateInvoice($randomString, (int) $user['Processing_value'], (int) $from_id);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس تون‌پی'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        if ($tonpayCustomMode) {
            if (!tonpayCustomStoreInvoice($randomString, $payment)) {
                update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            tonpayCustomBotSend($from_id, $randomString);
            return;
        }
        $invoiceId = trim((string) ($payment['invoice_id'] ?? ''));
        $invoiceUrl = trim((string) ($payment['invoice_url'] ?? ''));
        if ($invoiceId === '' || $invoiceUrl === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "tonpay_invoice_id", $invoiceId, "id_order", $randomString);
        update("Payment_report", "tonpay_invoice_url", $invoiceUrl, "id_order", $randomString);
        tonpayStoreWebInvoiceUrl($randomString, $payment['web_invoice_url'] ?? '');
        $tonpayPaymentUrl = tonpaySelectPaymentUrl($invoiceUrl, $payment['web_invoice_url'] ?? '', $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $tonpayPaymentUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۲۴ ساعت اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helptonpay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "blupal") {
        $mainbalanceblupal = select("PaySetting", "ValuePay", "NamePay", "minbalanceblupal", "select")['ValuePay'];
        $maxbalanceblupal = select("PaySetting", "ValuePay", "NamePay", "maxbalanceblupal", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceblupal || $user['Processing_value'] > $maxbalanceblupal) {
            $mainbalanceblupal = number_format($mainbalanceblupal);
            $maxbalanceblupal = number_format($maxbalanceblupal);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceblupal, $maxbalanceblupal), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'blupal');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'blupal');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "blupal";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = blupalCreateInvoice($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس بلوپال'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $invoiceId = trim((string) ($payment['invoice_id'] ?? ''));
        $paymentLink = trim((string) ($payment['payment_link'] ?? ''));
        if ($invoiceId === '' || $paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "blupal_invoice_id", $invoiceId, "id_order", $randomString);
        update("Payment_report", "blupal_payment_link", $paymentLink, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpblupal", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "atlaspay") {
        $mainbalanceatlaspay = select("PaySetting", "ValuePay", "NamePay", "minbalanceatlaspay", "select")['ValuePay'];
        $maxbalanceatlaspay = select("PaySetting", "ValuePay", "NamePay", "maxbalanceatlaspay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceatlaspay || $user['Processing_value'] > $maxbalanceatlaspay) {
            $mainbalanceatlaspay = number_format($mainbalanceatlaspay);
            $maxbalanceatlaspay = number_format($maxbalanceatlaspay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceatlaspay, $maxbalanceatlaspay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'atlaspay');
        if ($rxPendingGw !== null) {
            if (atlaspayCustomFetchReport($rxPendingGw['id_order']) !== null) {
                atlaspayCustomBotSend($from_id, $rxPendingGw['id_order']);
                return;
            }
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'atlaspay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "atlaspay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $atlaspayCustomMode = atlaspayPaymentMode() === 'custom';
        $payment = $atlaspayCustomMode
            ? atlaspayCustomCreateOrder($randomString, (int) $user['Processing_value'], (int) $from_id)
            : atlaspayCreateOrder($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['error' => 'پاسخ نامعتبر از سرویس اطلس‌پی'];
        } elseif (isset($payment['success']) && $payment['success'] === false) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['error'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['error'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $atlaspayOrderId = trim((string) ($payment['orderId'] ?? ''));
        $paymentUrl = trim((string) ($payment['customerStartLink'] ?? ''));
        $trackingCode = trim((string) ($payment['trackingCode'] ?? ''));
        if ($atlaspayOrderId === '' || $paymentUrl === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        if ($atlaspayCustomMode) {
            if (!atlaspayCustomStoreOrder($randomString, $payment)) {
                atlaspayCancelOrder($atlaspayOrderId);
                update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
                sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            atlaspayCustomBotSend($from_id, $randomString);
            return;
        }
        $totalAmountToman = isset($payment['totalAmountToman']) ? (int) $payment['totalAmountToman'] : (int) $user['Processing_value'];
        update("Payment_report", "atlaspay_order_id", $atlaspayOrderId, "id_order", $randomString);
        update("Payment_report", "atlaspay_tracking_code", $trackingCode, "id_order", $randomString);
        update("Payment_report", "atlaspay_payment_url", $paymentUrl, "id_order", $randomString);
        update("Payment_report", "atlaspay_total_amount", $totalAmountToman, "id_order", $randomString);
        rxPaymentV2Mark($randomString, ['atlaspay_created_at' => time()]);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentUrl]
                ]
            ]
        ]);
        $pricetoman = number_format($totalAmountToman, 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>" . ($trackingCode !== '' ? $trackingCode : $randomString) . "</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۲۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpatlaspay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "cubepay") {
        $mainbalancecubepay = select("PaySetting", "ValuePay", "NamePay", "minbalancecubepay", "select")['ValuePay'];
        $maxbalancecubepay = select("PaySetting", "ValuePay", "NamePay", "maxbalancecubepay", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancecubepay || $user['Processing_value'] > $maxbalancecubepay) {
            $mainbalancecubepay = number_format($mainbalancecubepay);
            $maxbalancecubepay = number_format($maxbalancecubepay);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancecubepay, $maxbalancecubepay), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'cubepay');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'cubepay');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "cubepay";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = cubepayCreatePayment($randomString, (int) $user['Processing_value'], $from_id);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['message' => 'پاسخ نامعتبر از سرویس کیوب‌پی'];
        } elseif (empty($payment['success'])) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['message'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['message'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $authority = trim((string) ($payment['authority'] ?? ''));
        $paymentLink = trim((string) ($payment['payment_link'] ?? ''));
        if ($paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "cubepay_authority", $authority, "id_order", $randomString);
        update("Payment_report", "cubepay_payment_link", $paymentLink, "id_order", $randomString);
        update("Payment_report", "cubepay_method", (string) ($payment['method'] ?? 'choice'), "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcubepay", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);

        // 🤖 اگر فروشنده در پنل کیوب‌پی گزینه‌ی «نمایش کارت در ربات» را روشن کرده
        // باشد، شماره‌کارت را همین‌جا داخل چت هم می‌فرستیم تا مشتری لازم نباشد از
        // تلگرام بیرون برود. این پیام «کنارِ» پیامِ بالا می‌نشیند، نه جای آن.
        if (!empty($payment['show_card_in_bot']) && !empty($payment['card']['number'])) {
            $cubepayCard = $payment['card'];
            $cubepayAmount = (int) ($payment['pay_amount_toman'] ?? 0);
            $cubepayMinutes = (int) ($payment['expires_in_minutes'] ?? 30);
            $cubepayCardText = "💳 <b>پرداخت کارت‌به‌کارت</b>\n"
                . "━━━━━━━━━━━━━━━\n\n"
                . "🔢 شماره کارت:\n<code>" . htmlspecialchars((string) $cubepayCard['number'], ENT_QUOTES, 'UTF-8') . "</code>\n"
                . (!empty($cubepayCard['holder']) ? "👤 به نام: " . htmlspecialchars((string) $cubepayCard['holder'], ENT_QUOTES, 'UTF-8') . "\n" : '')
                . "\n💰 مبلغ دقیق: <b>" . number_format($cubepayAmount) . "</b> تومان\n"
                . "⏳ مهلت پرداخت: " . $cubepayMinutes . " دقیقه\n\n"
                . "⚠️ مبلغ باید رقم‌به‌رقم دقیق باشد — تاییدِ خودکار فقط با همین عدد انجام می‌شود.";
            sendmessage($from_id, $cubepayCardText, null, 'HTML');
        }
    } elseif ($datain == "variza") {
        $mainbalancevariza = select("PaySetting", "ValuePay", "NamePay", "minbalancevariza", "select")['ValuePay'];
        $maxbalancevariza = select("PaySetting", "ValuePay", "NamePay", "maxbalancevariza", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancevariza || $user['Processing_value'] > $maxbalancevariza) {
            $mainbalancevariza = number_format($mainbalancevariza);
            $maxbalancevariza = number_format($maxbalancevariza);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalancevariza, $maxbalancevariza), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'variza');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'variza');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "variza";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = varizaCreatePayment($randomString, (int) $user['Processing_value']);

        $paymentErrorData = null;
        if (!is_array($payment)) {
            $paymentErrorData = ['message' => 'پاسخ نامعتبر از سرویس واریزا'];
        } elseif (empty($payment['success'])) {
            $paymentErrorData = $payment;
        }

        if ($paymentErrorData !== null) {
            $errorLines = [];
            if (isset($paymentErrorData['message'])) {
                $errorLines[] = "پیام خطا: " . $paymentErrorData['message'];
            }
            if (empty($errorLines)) {
                $errorLines[] = json_encode($paymentErrorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $text_error = implode("\n", $errorLines);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        $slug = trim((string) ($payment['slug'] ?? ''));
        $paymentLink = trim((string) ($payment['pay_url'] ?? ''));
        if ($paymentLink === '') {
            $text_error = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        update("Payment_report", "dec_not_confirmed", $slug, "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $paymentLink]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textnowpayments = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpvariza", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textnowpayments, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "abangateway") {
        $mainbalanceabangateway = select("PaySetting", "ValuePay", "NamePay", "minbalanceabangateway", "select")['ValuePay'];
        $maxbalanceabangateway = select("PaySetting", "ValuePay", "NamePay", "maxbalanceabangateway", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalanceabangateway || $user['Processing_value'] > $maxbalanceabangateway) {
            $mainbalanceabangateway = number_format($mainbalanceabangateway);
            $maxbalanceabangateway = number_format($maxbalanceabangateway);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalanceabangateway, $maxbalanceabangateway), null, 'HTML');
            return;
        }
        $rxPendingGw = findPendingGatewayInvoice($from_id, 'abangateway');
        if ($rxPendingGw !== null) {
            rx_send_pending_gateway_invoice_notice($from_id, $message_id, $rxPendingGw, 'abangateway');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "abangateway";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        $payment = abangatewayCreatePayment($randomString, (int) $user['Processing_value'], $from_id);

        // abangatewayCreatePayment() only reports success with an authority and
        // an https link in hand, so one refusal path covers every failure.
        if (!is_array($payment) || empty($payment['success'])) {
            $text_error = "پیام خطا: " . (is_array($payment) && !empty($payment['message']) ? $payment['message'] : 'پاسخ نامعتبر از آبان گیت وی');
            update("Payment_report", "payment_Status", "reject", "id_order", $randomString);
            update("Payment_report", "dec_not_confirmed", $text_error, "id_order", $randomString);
            $safeErrorText = htmlspecialchars($text_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
                        ⭕️ یک کاربر قصد پرداخت داشت که ساخت لینک پرداخت  با خطا مواجه شده و به کاربر لینک داده نشد
<blockquote>✍️ دلیل خطا : <pre>$safeErrorText</pre></blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        // The callback looks the order up by id and reads the authority from
        // here, the way the other gateways keep their invoice handle.
        update("Payment_report", "dec_not_confirmed", $payment['authority'], "id_order", $randomString);
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $payment['pay_url']]
                ]
            ]
        ]);
        $pricetoman = number_format($user['Processing_value'], 0);
        $textabangatewaypay = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>$randomString</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 صفحه پرداخت شماره کارت و <b>مبلغ دقیق</b> را نشان میدهد. همان عدد را، رقم به رقم، کارت به کارت کنید؛ تایید خودکار با همان عدد انجام میشود.
🔹 مهلت پرداخت روی همان صفحه نمایش داده میشود.
🔹 بعد از واریز لازم نیست رسید بفرستید؛ تایید خودکار است و نتیجه همین جا به شما اطلاع داده میشود.

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpabangateway", "select")['ValuePay'];
        if ($gethelp != 2) {
            $data = json_decode((string) $gethelp, true);
            if (is_array($data)) {
                if (($data['type'] ?? '') == "text") {
                    sendmessage($from_id, $data['text'], null, 'HTML');
                } elseif (($data['type'] ?? '') == "photo") {
                    sendphoto($from_id, $data['photoid'], null);
                } elseif (($data['type'] ?? '') == "video") {
                    sendvideo($from_id, $data['videoid'], null);
                }
            }
        }
        $message_id = sendmessage($from_id, $textabangatewaypay, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    } elseif ($datain == "digitaltron") {

        $mainbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "minbalancedigitaltron", "select")['ValuePay'];
        $maxbalancedigitaltron = select("PaySetting", "ValuePay", "NamePay", "maxbalancedigitaltron", "select")['ValuePay'];
        if ($user['Processing_value'] < $mainbalancedigitaltron || $user['Processing_value'] > $maxbalancedigitaltron) {
            $minF = number_format($mainbalancedigitaltron);
            $maxF = number_format($maxbalancedigitaltron);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $minF, $maxF), null, 'HTML');
            return;
        }

        if (!function_exists('crypto_active_wallets')) {
            sendmessage($from_id, $datatextbot['dyn_wallet_hashchecker_not_loaded'] ?? "❌ ماژول هش‌چکر بارگذاری نشده است. لطفاً مدتی دیگر تلاش کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $available = crypto_active_wallets();
        if (empty($available)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_no_wallet_address_configured'] ?? "❌ آدرس کیف پولی برای هیچ شبکه‌ای توسط ادمین ثبت نشده است. لطفاً با پشتیبانی در تماس باشید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $faShort = [
            'TRX' => '🟥 ترون (TRX)',
            'TON' => '🟦 تون (TON)',
            'USDT_TRC20' => '🟢 تتر روی ترون',
            'USDT_TON'   => '🟢 تتر روی تون',
        ];
        $styleCurrency = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('currency_pick') : null;
        $manualCurListPicker = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        $rows = [];
        $hasAutoInList = false;
        $hasManualInList = false;
        foreach ($available as $w) {
            $cur = (string) $w['currency'];
            $isManualPick = isset($manualCurListPicker[$cur]);
            if ($isManualPick) { $hasManualInList = true; } else { $hasAutoInList = true; }
            $label = $faShort[$cur] ?? $cur;
            if ($isManualPick) { $label .= ' 🛠'; }
            $btn = ['text' => $label, 'callback_data' => 'digitaltron_pay_' . $cur];
            if ($styleCurrency) $btn['style'] = $styleCurrency;
            $rows[] = [$btn];
        }
        $rows[] = [['text' => '❌ بستن', 'callback_data' => 'colselist']];
        $picker = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
        if ($hasAutoInList && $hasManualInList) {
            $hashHint = "ℹ️ برای شبکه‌های <b>خودکار</b> (بدون 🛠)، فقط هش (Hash / TxID) تراکنش کافی است — نیازی به ارسال عکس نیست و ربات ظرف چند دقیقه به‌صورت خودکار تایید می‌کند.\n"
                      . "ℹ️ برای شبکه‌های <b>دستی</b> (🛠)، بعد از هش باید <b>عکس رسید تراکنش</b> نیز ارسال شود و تایید نهایی توسط ادمین انجام می‌شود.";
        } elseif ($hasManualInList) {
            $hashHint = "ℹ️ این شبکه‌ها به‌صورت خودکار بررسی نمی‌شوند. بعد از پرداخت، هش (Hash / TxID) و سپس <b>عکس رسید تراکنش</b> را ارسال می‌کنید و تایید نهایی توسط ادمین انجام می‌شود.";
        } else {
            $hashHint = "ℹ️ پس از پرداخت، فقط هش (Hash / TxID) تراکنش را برای ربات می‌فرستید — نیازی به ارسال عکس نیست. "
                      . "هش هم به‌صورت خام و هم به‌صورت لینک (مثلاً <code>tonviewer.com/transaction/...</code> یا <code>tronscan.org/#/transaction/...</code>) قابل ارسال است.";
        }
        $msg = "💎 <b>پرداخت با ارز دیجیتال</b>\n\n"
             . "شبکه‌ای که می‌خواهید با آن پرداخت کنید را انتخاب کنید. بعد از انتخاب، ربات یک آدرس کیف پول و یک <b>مبلغ دقیق</b> به شما اعلام می‌کند.\n\n"
             . $hashHint;
        sendmessage($from_id, $msg, $picker, 'HTML');
    } elseif (preg_match('/^digitaltron_(?:pay|paymode_(?:ext|ir))_([A-Za-z0-9_]{2,20})$/', (string) $datain, $dpmm)
        && in_array($dpmm[1], array_column(function_exists('crypto_active_wallets') ? crypto_active_wallets() : [], 'currency'), true)
    ) {

        $cur = $dpmm[1];
        $manualCurList = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
        $isManualCur = isset($manualCurList[$cur]);
        $amountIrt = (int) ($user['Processing_value'] ?? 0);
        if ($amountIrt <= 0) {
            sendmessage($from_id, $datatextbot['dyn_wallet_charge_amount_undetectable'] ?? "❌ مبلغ شارژ قابل تشخیص نیست. لطفاً مجدداً از منوی شارژ کیف پول اقدام کنید.", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $invoiceMeta = sprintf('%s|%s', $user['Processing_value_tow'] ?? '', $user['Processing_value_one'] ?? '');
        $result = crypto_create_invoice($from_id, $amountIrt, $cur, $invoiceMeta);
        if (empty($result['ok'])) {
            $errMap = [
                'currency-not-supported' => 'این ارز فعال نیست.',
                'wallet-not-configured'  => 'آدرس کیف پول این ارز توسط ادمین تنظیم نشده است.',
                'below-min'              => 'مبلغ کمتر از حداقل مجاز است.',
                'above-max'              => 'مبلغ بیشتر از حداکثر مجاز است.',
                'rate-unavailable'       => 'دریافت نرخ لحظه‌ای ممکن نبود؛ لطفاً دقایقی دیگر تلاش کنید.',
                'db-write-failed'        => 'خطای داخلی در ثبت فاکتور.',
            ];
            $reason = $result['error'] ?? 'unknown';
            $faMsg = $errMap[$reason] ?? $reason;
            sendmessage($from_id, "❌ {$faMsg}", $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $displayDecimals = function_exists('crypto_display_decimals') ? crypto_display_decimals($cur) : 2;
        $amountStr = number_format((float) $result['amount_coin'], $displayDecimals, '.', '');
        if (strpos($amountStr, '.') !== false) {
            $amountStr = rtrim(rtrim($amountStr, '0'), '.');
        }
        $expireMin = max(1, (int) round(($result['expires_at'] - time()) / 60));
        if ($isManualCur) {
            $explorerHint = '<i>(این شبکه به‌صورت خودکار بررسی نمی‌شود؛ هش تراکنش پس از ارسال توسط ادمین بررسی خواهد شد)</i>';
        } else {
            $explorerHint = ($cur === 'TRX' || $cur === 'USDT_TRC20')
                ? '<i>(لینک‌های Tronscan قابل قبول است)</i>'
                : '<i>(لینک‌های Tonviewer / Tonscan قابل قبول است)</i>';
        }
        $walletEsc = htmlspecialchars((string) $result['wallet']);
        $walletMemo = trim((string) ($result['wallet_memo'] ?? ''));
        $isTonNetwork = in_array($cur, ['TON', 'USDT_TON'], true);

        $finalAmountIrt = (int) ($result['final_amount_irt'] ?? $amountIrt);
        $priceBlock = "💸 مبلغ قابل پرداخت: " . number_format($finalAmountIrt) . " تومان\n";

        $amountNotice = "❗️ <b>دقیقاً همین مقدار</b> را ارسال کنید. تفاوت در ارقام باعث می‌شود ربات تراکنش شما را شناسایی نکند.";

        $memoBlock = "";
        if ($isTonNetwork && $walletMemo !== '') {
            $memoEsc = htmlspecialchars($walletMemo);
            $memoBlock = "🏷 <b>ممو (Memo / Comment) — اجباری:</b>\n<code>{$memoEsc}</code>\n"
                       . "⚠️ <b>بدون ممو، تراکنش شما به فاکتور وصل نمی‌شود.</b>\n\n";
        }

        if ($isManualCur) {
            $invoiceMsg = "🛠 <b>فاکتور پرداخت کریپتو (بررسی دستی)</b>\n\n"
                 . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                 . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                 . $priceBlock . "\n"
                 . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                 . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                 . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                 . $memoBlock
                 . $amountNotice . "\n"
                 . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                 . "⚠️ <b>این شبکه به‌صورت خودکار بررسی نمی‌شود.</b> بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید، "
                 . "هش/لینک تراکنش را ارسال کنید و سپس <b>عکس رسید تراکنش را نیز ارسال کنید</b> — بدون عکس رسید، درخواست شما برای ادمین ثبت نمی‌شود.\n{$explorerHint}";
        } else {
            $invoiceMsg = "💎 <b>فاکتور پرداخت کریپتو</b>\n\n"
                 . "🛒 کد فاکتور: <code>{$result['order_id']}</code>\n"
                 . "💎 ارز: <b>{$cur}</b>  •  🌐 شبکه: <b>{$result['network']}</b>\n"
                 . $priceBlock . "\n"
                 . "🪙 <b>مبلغ دقیقی که باید ارسال کنید:</b>\n<code>{$amountStr}</code>\n"
                 . "(معادل " . number_format($finalAmountIrt) . " تومان)\n\n"
                 . "📥 <b>آدرس کیف پول مقصد:</b>\n<blockquote>{$walletEsc}</blockquote>\n\n"
                 . $memoBlock
                 . $amountNotice . "\n"
                 . "⏰ مدت اعتبار: حدود {$expireMin} دقیقه.\n\n"
                 . "بعد از پرداخت، روی دکمه «✅ پرداخت کردم» بزنید و هش/لینک تراکنش را ارسال کنید.\n{$explorerHint}";
        }

        $styleWallet = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_wallet')  : null;
        $styleAmount = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_amount')  : null;
        $styleMemo   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('copy_memo')    : null;
        $stylePaid   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('paid_submit')  : null;
        $styleBack   = function_exists('crypto_invoice_button_style') ? crypto_invoice_button_style('invoice_back') : null;

        $btnCopyWallet = ['text' => '🔗 کپی آدرس ولت', 'copy_text' => ['text' => (string) $result['wallet']]];
        if ($styleWallet) $btnCopyWallet['style'] = $styleWallet;

        $btnCopyAmount = ['text' => '🪙 کپی مقدار', 'copy_text' => ['text' => $amountStr]];
        if ($styleAmount) $btnCopyAmount['style'] = $styleAmount;

        $btnPaid = ['text' => '✅ پرداخت کردم | ارسال هش (TXID) 🧾', 'callback_data' => 'digitaltron_submit_' . $result['order_id']];
        if ($stylePaid) $btnPaid['style'] = $stylePaid;

        $invoiceKbRows = [
            [$btnCopyWallet, $btnCopyAmount],
        ];
        if ($isTonNetwork && $walletMemo !== '') {
            $btnCopyMemo = ['text' => '🏷 کپی ممو', 'copy_text' => ['text' => $walletMemo]];
            if ($styleMemo) $btnCopyMemo['style'] = $styleMemo;
            $invoiceKbRows[] = [$btnCopyMemo];
        }
        $invoiceKbRows[] = [$btnPaid];
        $btnInvoiceBack = ['text' => '🔙 بازگشت (لغو فاکتور)', 'callback_data' => 'crypto_cancel_' . $result['order_id']];
        if ($styleBack) $btnInvoiceBack['style'] = $styleBack;
        $invoiceKbRows[] = [$btnInvoiceBack];
        $invoiceKb = json_encode(['inline_keyboard' => $invoiceKbRows], JSON_UNESCAPED_UNICODE);

        $resp = null;
        $fancyQrPath = null;
        $fancyOk = false;
        if (!(function_exists('isQrDisabled') && isQrDisabled())) {
            try {
                $rootDir = defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : dirname(__DIR__, 3);
                $autoload = $rootDir . '/vendor/autoload.php';
                if (is_file($autoload)) @require_once $autoload;
                if (class_exists('\\Endroid\\QrCode\\Builder\\Builder') && function_exists('addBackgroundImage') && strlen((string) $result['wallet']) <= 2048) {
                    $builder = new \Endroid\QrCode\Builder\Builder(
                        writer: new \Endroid\QrCode\Writer\PngWriter(),
                        writerOptions: [],
                        data: (string) $result['wallet'],
                        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                        size: 560,
                        margin: 2,
                    );
                    $built = $builder->build();
                    $fancyQrPath = $rootDir . DIRECTORY_SEPARATOR . 'cryptoqr_' . bin2hex(random_bytes(3)) . '.png';
                    @file_put_contents($fancyQrPath, $built->getString());
                    $made = @addBackgroundImage($fancyQrPath, $built, 'images.jpeg');
                    if ($made && is_file($fancyQrPath) && function_exists('telegram')) {
                        $resp = @telegram('sendphoto', [
                            'chat_id'      => (string) $from_id,
                            'photo'        => new \CURLFile($fancyQrPath),
                            'caption'      => $invoiceMsg,
                            'parse_mode'   => 'HTML',
                            'reply_markup' => $invoiceKb,
                        ]);
                        $fancyOk = true;
                    }
                }
            } catch (\Throwable $_) {  }
            if ($fancyQrPath && is_file($fancyQrPath)) { @unlink($fancyQrPath); }
        }

        if (!$fancyOk) {
            $resp = sendmessage($from_id, $invoiceMsg, $invoiceKb, 'HTML');
        }
        updatePaymentMessageId($resp, $result['order_id']);
    } elseif (preg_match('/^crypto_cancel_([A-Za-z0-9_\-]+)$/', (string) $datain, $dcm)) {

        $orderId = $dcm[1];
        if (function_exists('getDatabaseConnection')) {
            $pdo = getDatabaseConnection();
            if ($pdo instanceof \PDO) {
                try {
                    if (function_exists('rx_release_unpaid_discount')) {
                        $rxRep = $pdo->prepare("SELECT time FROM Payment_report WHERE id_order = :o AND id_user = :u AND payment_Status IN ('Unpaid','AwaitingHash') LIMIT 1");
                        $rxRep->execute([':o' => $orderId, ':u' => (string) $from_id]);
                        $rxRepRow = $rxRep->fetch(\PDO::FETCH_ASSOC);
                        $rxRefTime = (is_array($rxRepRow) && !empty($rxRepRow['time'])) ? strtotime(str_replace('/', '-', (string) $rxRepRow['time'])) : null;
                        rx_release_unpaid_discount((string) $from_id, null, $rxRefTime ?: null);
                    }
                    $stmt = $pdo->prepare(
                        "UPDATE Payment_report
                            SET payment_Status = 'expire'
                          WHERE id_order = :o
                            AND id_user = :u
                            AND payment_Status IN ('Unpaid', 'AwaitingHash')"
                    );
                    $stmt->execute([':o' => $orderId, ':u' => (string) $from_id]);
                } catch (\Throwable $_) {  }
            }
        }
        if (!empty($message_id) && function_exists('deletemessage')) {
            @deletemessage((string) $from_id, (int) $message_id);
        }
        sendmessage((string) $from_id, "✅ فاکتور لغو شد.", $keyboard, 'HTML');
    } elseif (preg_match('/^digitaltron_submit_([A-Za-z0-9_\-]+)$/', (string) $datain, $dsm)) {

        $orderId = $dsm[1];
        $reportStmt = $connect->prepare("SELECT id_order, payment_Status FROM Payment_report WHERE id_order = ? AND id_user = ? LIMIT 1");
        $userIdStr = (string) $from_id;
        $reportStmt->bind_param('ss', $orderId, $userIdStr);
        $reportStmt->execute();
        $rowChk = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (!is_array($rowChk)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
            return;
        }
        if (!in_array($rowChk['payment_Status'], ['Unpaid', 'AwaitingHash'], true)) {
            sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_pending'] ?? "❌ این فاکتور دیگر در حالت انتظار پرداخت نیست.", $keyboard, 'HTML');
            return;
        }
        update("user", "Processing_value_four", $orderId, "id", $from_id);
        step('digitaltron_hash_input', $from_id);
        $cancelKb = json_encode([
            'inline_keyboard' => [
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_hash_input']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage(
            $from_id,
            "📨 لطفاً <b>هش (TxID) تراکنش</b> را ارسال کنید.\n\n"
            . "هر دو فرمت قابل قبول است:\n"
            . "• هش خام (مثلاً <code>09d6aaee138447689f92a0ee7a4382dd01fcc88413f8644f2dd8fb772b0c9402</code>)\n"
            . "• لینک کامل از Tonviewer / Tonscan / Tronscan",
            $cancelKb,
            'HTML'
        );
    } elseif ($datain == "startelegrams") {
        $rates = requireTronRates(['USD']);
        if ($rates === null) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $usd = $rates['USD'];
        if (!is_numeric($usd) || $usd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $userAmountUsd = round($user['Processing_value'] / $usd, 2);
        $starPriceSetting = getPaySettingValue('star_price_usd', '0.016');
        if (is_string($starPriceSetting)) {
            $starPriceSetting = str_replace(',', '.', $starPriceSetting);
        }
        $starPriceUsd = is_numeric($starPriceSetting) ? (float) $starPriceSetting : 0.016;
        if ($starPriceUsd <= 0) {
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $starAmount = (int) ceil($userAmountUsd / $starPriceUsd);
        if ($starAmount < 1) {
            $starAmount = 1;
        }
        $mainbalance = select("PaySetting", "ValuePay", "NamePay", "minbalancestar", "select")['ValuePay'];
        $maxbalance = select("PaySetting", "ValuePay", "NamePay", "maxbalancestar", "select")['ValuePay'];
        // برای سفارش مستقیم سرویس، محدودیت شارژ کیف پول اعمال نشود؛ این محدودیت فقط برای شارژ عادی است.
        $rxInvoiceOne = trim((string)($user['Processing_value_one'] ?? '0'));
        $rxInvoiceTwo = trim((string)($user['Processing_value_tow'] ?? '0'));
        $rxIsServiceOrderPayment = !in_array($rxInvoiceOne, ['', '0', 'none'], true)
            || !in_array($rxInvoiceTwo, ['', '0', 'none'], true);
        if (!$rxIsServiceOrderPayment && ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance)) {
            $mainbalance = number_format($mainbalance);
            $maxbalance = number_format($maxbalance);
            sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['Balance']['linkpayments'], $keyboard, 'HTML');
        $dateacc = date('Y/m/d H:i:s');
        $randomString = bin2hex(random_bytes(5));
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
        $payment_Status = "Unpaid";
        $Payment_Method = "Star Telegram";
        $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
        $stmt->execute();
        if (function_exists('rxPaymentV2Mark')) {
            rxPaymentV2Mark($randomString, ['stars_amount' => $starAmount]);
        }
        $affilnecurrency = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
        $invoiceParams = [
            'title' => "Buy for Price {$user['Processing_value']}",
            'description' => "Buy price",
            'payload' => $randomString,
            'currency' => "XTR",
            'prices' => json_encode(array(
                array(
                    'label' => "Price",
                    'amount' => $starAmount
                )
            ))
        ];
        if (($invoiceParams['currency'] ?? null) === 'XTR') {
            unset($invoiceParams['provider'], $invoiceParams['provider_token']);
        }
        $straCreateLink = telegram('createInvoiceLink', $invoiceParams);
        if ($straCreateLink['ok'] == false) {
            $text_error = json_encode($straCreateLink);
            sendmessage($from_id, $textbotlang['users']['Balance']['errorLinkPayment'], $keyboard, 'HTML');
            step('home', $from_id);
            $ErrorsLinkPayment = "
خطا در هنگام ساخت فاکتور استار
<blockquote>✍️ دلیل خطا : $text_error</blockquote>

<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>روش پرداخت : $Payment_Method</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $ErrorsLinkPayment,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $paymentkeyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['payments'], 'url' => $straCreateLink['result']]
                ]
            ]
        ]);
        $formatprice = number_format($user['Processing_value'], 0);
        $approxStarUsd = number_format($starAmount * $starPriceUsd, 2);
        $textstar = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری: <code>$randomString</code>
💲 مبلغ تراکنش: $starAmount ⭐ (حدوداً $approxStarUsd دلار | معادل $formatprice تومان)

📌 لطفاً مبلغ $formatprice تومان را به استار تلگرام تبدیل کرده و واریز نمایید.

💢 نکات مهم قبل از پرداخت: 👇
🔹 هر تراکنش ۱ روز معتبر است؛ بعد از انقضا از واریز خودداری کنید.

✅ در صورت مشکل، با پشتیبانی در ارتباط باشید.";
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpstar", "select")['ValuePay'];
        if (intval($gethelp) != 2) {
            $data = json_decode($gethelp, true);
            if ($data['type'] == "text") {
                sendmessage($from_id, $data['text'], null, 'HTML');
            } elseif ($data['type'] == "photo") {
                sendphoto($from_id, $data['photoid'], null);
            } elseif ($data['type'] == "video") {
                sendvideo($from_id, $data['videoid'], null);
            }
        }
        $message_id = sendmessage($from_id, $textstar, $paymentkeyboard, 'HTML');
        updatePaymentMessageId($message_id, $randomString);
    }

    if (isset($randomString) && $randomString !== '') {
        if ($__chargeBonus > 0) {
            update("Payment_report", "charge_bonus", $__chargeBonus, "id_order", $randomString);
        }
        $__pv4 = (string)($user['Processing_value_four'] ?? '');
        if (strpos($__pv4, 'chg|') === 0) {
            $__pv4Parts = explode('|', $__pv4);
            $__pendingDiscountCode = isset($__pv4Parts[2]) ? trim((string)$__pv4Parts[2]) : '';
            $__pendingPriceBefore  = isset($__pv4Parts[3]) ? (float)$__pv4Parts[3] : 0.0;
            if ($__pendingDiscountCode !== '') {
                update("Payment_report", "discount_code", $__pendingDiscountCode, "id_order", $randomString);
                update("Payment_report", "discount_amount", (string)$__chargeBonus, "id_order", $randomString);
                update("Payment_report", "price_before_discount", (string)$__pendingPriceBefore, "id_order", $randomString);
            }
        }
    }
}

if (preg_match('/Confirmpay_user_(\w+)_(\w+)/', $datain, $dataget)) {
    $id_payment = $dataget[1];
    $id_order = $dataget[2];


    $stmtPay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = :id_order LIMIT 1");
    $stmtPay->bindValue(':id_order', $id_order, PDO::PARAM_STR);
    $stmtPay->execute();
    $Payment_report = $stmtPay->fetch(PDO::FETCH_ASSOC);
    if (!is_array($Payment_report) || (string)($Payment_report['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('PAYMENT_CONFIRM_FORBIDDEN', 'Non-owner attempted Confirmpay_user', [
                'from_id' => $from_id, 'id_order' => $id_order, 'id_payment' => $id_payment,
            ]);
        }
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Confirmpayadmin'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $StatusPayment = StatusPayment($id_payment);
    if ($StatusPayment['payment_status'] == "finished") {
        $rxClaimPaid = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :o AND payment_Status <> 'paid'");
        $rxClaimPaid->execute([':o' => $Payment_report['id_order']]);
        if ($rxClaimPaid->rowCount() < 1) {
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['Admin']['Payment']['reviewedpayment'] ?? 'این پرداخت قبلاً بررسی شده است',
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }
        if (function_exists('clearSelectCache')) clearSelectCache('Payment_report');
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['finished'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        DirectPayment($Payment_report['id_order']);
        $_uid = $Payment_report['id_user'];
        $_stmt = $connect->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
        $_stmt->bind_param("s", $_uid); $_stmt->execute();
        $Balance_id = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        $priceFormatted = rxFormatToman($Payment_report['price']);
        $text_report = "💵 پرداخت جدید

<blockquote>آیدی عددی کاربر : $from_id</blockquote>
<blockquote>مبلغ تراکنش : {$priceFormatted}</blockquote>
<blockquote>روش پرداخت : درگاه ارزی ریالی اول</blockquote>";
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackiranpay2", "select")['ValuePay'];
        $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
            || rx_cashbackEligibleForKey("chashbackiranpay2", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
        if ($cashbackEligible && $pricecashback != "0") {
            $result = (int) floor(($Payment_report['price'] * $pricecashback) / 100);
            if (rx_cashback_credit_once($Payment_report['id_order'], $Payment_report['id_user'], $result, 'chashbackiranpay2', 'هدیه بازگشت وجه درگاه ارزی ریالی') === 'credited') {
                $pricecashback = number_format($pricecashback);
                $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], rxFormatToman($result));
                sendmessage($Payment_report['id_user'], $text_report, null, 'HTML');
            }
        }
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("user", "Processing_value_one", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_tow", "none", "id", $Payment_report['id_user']);
        update("user", "Processing_value_four", "none", "id", $Payment_report['id_user']);
    } elseif ($StatusPayment['payment_status'] == "expired") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['expired'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "refunded") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['refunded'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "waiting") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['waiting'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "sending") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['sending'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } else {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Failed'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    }
}
if (preg_match('/^sbv(resume|cancel)_(\w+)$/', (string) $datain, $_sbvm) && function_exists('rx_sbv_active_payment')) {
    if ($_sbvm[1] === 'cancel') {
        $_sbv_res = rx_sbv_cancel_active($from_id, $_sbvm[2]);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $_sbv_res['message'],
            'show_alert' => empty($_sbv_res['ok']),
            'cache_time' => 0,
        ]);
        if (!empty($_sbv_res['ok']) || ($_sbv_res['code'] ?? '') === 'not_active') {
            if (in_array((string) ($user['step'] ?? ''), ['card_select_step', 'card_photo_step', 'card_last4_step', 'cart_to_cart_user'], true)
                && (string) ($user['Processing_value'] ?? '') === $_sbvm[2]) {
                step('home', $from_id);
            }
            rx_sbv_bot_mark_cancelled($from_id, $message_id, !empty($_sbv_res['ok'])
                ? "❌ این پرداخت لغو شد و دیگر قابل واریز نیست.\n🛒 کد پیگیری: <code>" . htmlspecialchars($_sbvm[2], ENT_QUOTES, 'UTF-8') . "</code>\n\nاکنون می‌توانید پرداخت جدیدی ایجاد کنید."
                : $_sbv_res['message']);
        }
    } elseif (rx_sbv_bot_resume($from_id, $_sbvm[2], $user)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
        deletemessage($from_id, $message_id);
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'این پرداخت دیگر قابل ادامه نیست.',
            'show_alert' => true,
            'cache_time' => 0,
        ]);
    }
    return;
}
if (preg_match('/^sendresidcart-(.*)/', $datain, $dataget)) {
    $timefivemin = time() - 120;
    $timefivemin = date('Y/m/d H:i:s', intval($timefivemin));
    $sql = "SELECT * FROM Payment_report WHERE id_user = :u AND Payment_Method = 'cart to cart' AND at_updated > :t AND COALESCE(payment_Status, '') NOT IN ('cancelled', 'expire')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => (string) $from_id, ':t' => $timefivemin]);
    $paymentcount = $stmt->rowCount();
    if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, "❗ شما در ۲ دقیقه اخیر رسید ارسال کرده اید لطفا ۲ دقیقه دیگر رسید جدید را ارسال نمایید.", null, 'HTML');
        return;
    }
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select", ['cache' => false]);
    if (!is_array($payemntcheck) || (string) ($payemntcheck['id_user'] ?? '') !== (string) $from_id) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire" || $payemntcheck['payment_Status'] == "cancelled") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    if (function_exists('rx_sbv_receipt_gate')) {
        $_sbv_gate = rx_sbv_receipt_gate((string) $payemntcheck['id_order']);
        if (empty($_sbv_gate['ok'])) {
            $_sbv_alert = telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $_sbv_gate['message'],
                'show_alert' => true,
                'cache_time' => 0,
            ]);
            if (!is_array($_sbv_alert) || empty($_sbv_alert['ok'])) {
                sendmessage($from_id, $_sbv_gate['message'], null, 'HTML');
            }
            return;
        }
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "🖼 تصویر رسید خود را ارسال نمایید", $backuser, 'HTML');
    step('cart_to_cart_user', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == 'card_select_step') {
    if (!isset($update['callback_query'])) { return; }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($datain === 'cv_new') {
        step('card_photo_step', $from_id);
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است

📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.

🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", (function_exists('rx_sbv_cancel_keyboard') && rx_sbv_cancel_keyboard((string) $orderId) !== null) ? rx_sbv_cancel_keyboard((string) $orderId) : $backuser, 'HTML');
        return;
    }
    if (preg_match('/^cv_use_(\d{4})$/', $datain, $_cv_m)) {
        $_cv_last4 = $_cv_m[1];
        $_cv_check = $connect->prepare("SELECT id FROM verified_cards WHERE user_id = ? AND last4 = ? LIMIT 1");
        $_cv_check->bind_param("ss", $from_id, $_cv_last4);
        $_cv_check->execute();
        $_cv_check_res = $_cv_check->get_result();
        if ($_cv_check_res->num_rows === 0) {
            sendmessage($from_id, "❌ کارت انتخاب‌شده معتبر نیست. لطفاً دوباره تلاش کنید.", null, 'HTML');
            $_cv_check->close();
            return;
        }
        $_cv_check->close();
        update("Payment_report", "card_last4", $_cv_last4, "id_order", $orderId);
        deletemessage($from_id, $message_id);
        step('home', $from_id);

        $_cv_note = "<blockquote>ℹ️ کاربر <code>{$from_id}</code> از کارت تاییدشده **** **** **** {$_cv_last4} استفاده کرده است.</blockquote>\n<blockquote>🛒 کد پیگیری: {$orderId}</blockquote>\nلطفاً ۴ رقم آخر رسید را بررسی کنید.";
        $_cv_receipt_route = rxReceiptDeliveryRoute('paymentreport');
        $_cv_report_group = trim((string)($_cv_receipt_route['chat_id'] ?? ''));
        if (!empty($_cv_receipt_route['topic_enabled']) && $_cv_report_group !== '') {
            $_cv_topic_payload = ['chat_id' => $_cv_report_group, 'text' => $_cv_note, 'parse_mode' => 'HTML'];
            if (!empty($_cv_receipt_route['thread_id'])) {
                $_cv_topic_payload['message_thread_id'] = (int)$_cv_receipt_route['thread_id'];
            }
            telegram('sendmessage', $_cv_topic_payload);
        } elseif (empty($_cv_receipt_route['topic_enabled'])) {
            foreach ($admin_ids as $_cv_adm_id) {
                sendmessage($_cv_adm_id, $_cv_note, null, 'HTML');
            }
        }

        $payRecord = select("Payment_report", "*", "id_order", $orderId);
        $_uid = intval($from_id);
        $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = {$_uid})";
        $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
        $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
        $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

        $card_info = null;
        $_card2 = null;

        if ($_cmode == 'direct') {
            $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
            $_dcards = [];
            if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
            $card_info = $_dcards[0] ?? null;
            $_card2 = $_dcards[1] ?? null;
        } else {
            $_ck = "card_cycle_{$_uid}";
            $_ck_esc = mysqli_real_escape_string($connect, $_ck);
            $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
            $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
            $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
            $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
            $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

            $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

            if (!$card_info) {
                $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
                $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
                $_seen = [];
            }

            if ($card_info) {
                $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
                $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
                mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
                if (mysqli_affected_rows($connect) === 0) {
                    mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
                }
            }
        }

        if (!$card_info || empty($card_info['cardnumber'])) {
            sendmessage($from_id, "❌ کارت بانکی فعالی یافت نشد. با پشتیبانی تماس بگیرید.", $keyboard, 'HTML');
            return;
        }
        $card_number = $card_info['cardnumber'];
        $PaySettingname = $card_info['namecard'];
        $valueprice = number_format($payRecord['price'] ?? 0);
        $price_copy = intval(($payRecord['price'] ?? 0) . "0");
        $_sbv_bot = function_exists('rx_sbv_bot_payment_view') ? rx_sbv_bot_payment_view((string) $orderId) : null;
        if ($_sbv_bot !== null) {
            $valueprice = $_sbv_bot['valueprice'];
            $price_copy = $_sbv_bot['price_copy'];
        }
        if ($_cmode == 'direct' && $_card2) {
            $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
        } else {
            $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
            $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
        }
        if ($_sbv_bot !== null) {
            $textcart .= $_sbv_bot['block'];
        }
        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
        if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
            $_kb_rows = [[
                ...($setting['statuscopycart'] == "1" ? [['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num']] : []),
                ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'],
            ]];
            if ($setting['statuscopycart'] == "1" && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
            }
            $_kb_rows[] = [rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
            $_sbv_cancel_row = ($_sbv_bot !== null && function_exists('rx_sbv_cancel_row')) ? rx_sbv_cancel_row((string) $orderId) : null;
            if ($_sbv_cancel_row !== null) {
                $_kb_rows[] = $_sbv_cancel_row;
            }
            $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
        } else {
            $sendresidcart = json_encode([
                'inline_keyboard' => [
                    [rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
                ]
            ]);
        }
        $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
        if ($gethelp && $gethelp != 2) {
            $data = json_decode($gethelp, true);
            if (is_array($data)) {
                if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
                elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
                elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
            }
        }
        $cartMsgResult = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
        updatePaymentMessageId($cartMsgResult, $orderId);
        return;
    }
} elseif ($user['step'] == 'card_photo_step') {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!$photo || isset($update['message']['media_group_id'])) {
        sendmessage($from_id, "❌ لطفاً یک تصویر تکی از کارت خود ارسال کنید.", null, 'HTML');
        return;
    }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $payRecord = select("Payment_report", "*", "id_order", $orderId);
    if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_photo_file_id", $photoid, "id_order", $orderId);
    step('card_last4_step', $from_id);
    sendmessage($from_id, "✅ عکس کارت دریافت شد.\n\n🔢 لطفاً ۴ رقم آخر شماره کارت خود را وارد کنید:", null, 'HTML');
} elseif ($user['step'] == 'card_last4_step') {
    if (!isset($update['message']) && empty($text)) { return; }
    $_cl4 = trim((string)($text ?? ''));
    if (!preg_match('/^\d{4}$/', $_cl4)) {
        sendmessage($from_id, "❌ لطفاً دقیقاً ۴ رقم آخر کارت را وارد کنید (مثال: 1234).", null, 'HTML');
        return;
    }
    $orderId = (string)($user['Processing_value'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی. مراحل پرداخت را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $payRecord = select("Payment_report", "*", "id_order", $orderId);
    if ($payRecord === false || (string)($payRecord['id_user'] ?? '') !== (string)$from_id) {
        sendmessage($from_id, "❌ رکورد پرداخت یافت نشد. مراحل را از ابتدا انجام دهید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_last4", $_cl4, "id_order", $orderId);
    step('home', $from_id);
    $_uid = intval($from_id);
    $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid})))";
    $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
    $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
    $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

    $card_info = null;
    $_card2 = null;

    if ($_cmode == 'direct') {
        $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
        $_dcards = [];
        if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
        $card_info = $_dcards[0] ?? null;
        $_card2 = $_dcards[1] ?? null;
    } else {
        $_ck = "card_cycle_{$_uid}";
        $_ck_esc = mysqli_real_escape_string($connect, $_ck);
        $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
        $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
        $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
        $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
        $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

        $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
        $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

        if (!$card_info) {
            $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
            $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
            $_seen = [];
        }

        if ($card_info) {
            $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
            $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
            mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
            if (mysqli_affected_rows($connect) === 0) {
                mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
            }
        }
    }

    if (!$card_info || empty($card_info['cardnumber'])) {
        sendmessage($from_id, "❌ کارت بانکی فعالی یافت نشد. با پشتیبانی تماس بگیرید.", $keyboard, 'HTML');
        return;
    }
    $card_number = $card_info['cardnumber'];
    $PaySettingname = $card_info['namecard'];
    $valueprice = number_format($payRecord['price'] ?? 0);
    $price_copy = intval(($payRecord['price'] ?? 0) . "0");
    $_sbv_bot = function_exists('rx_sbv_bot_payment_view') ? rx_sbv_bot_payment_view((string) $orderId) : null;
    if ($_sbv_bot !== null) {
        $valueprice = $_sbv_bot['valueprice'];
        $price_copy = $_sbv_bot['price_copy'];
    }
    if ($_cmode == 'direct' && $_card2) {
        $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
    } else {
        $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
        $textcart = strtr($datatextbot['text_cart'] ?? "💳 مبلغ {price} تومان ({price}0 ریال) به کارت {card_number} به نام {name_card} واریز کنید.", $replacements);
    }
    if ($_sbv_bot !== null) {
        $textcart .= $_sbv_bot['block'];
    }
    $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
    if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
        $_kb_rows = [[
            ...($setting['statuscopycart'] == "1" ? [['text' => "کپی شماره کارت", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num']] : []),
            ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'],
        ]];
        if ($setting['statuscopycart'] == "1" && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
            $_kb_rows[] = [['text' => "کپی شماره کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
        }
        $_kb_rows[] = [rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)];
        $_sbv_cancel_row = ($_sbv_bot !== null && function_exists('rx_sbv_cancel_row')) ? rx_sbv_cancel_row((string) $orderId) : null;
        if ($_sbv_cancel_row !== null) {
            $_kb_rows[] = $_sbv_cancel_row;
        }
        $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
    } else {
        $sendresidcart = json_encode([
            'inline_keyboard' => [
                [rx_kb_style(['text' => $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.", 'callback_data' => "sendresidcart-{$orderId}"], 'pay_sendreceipt', $_prcpt_s)],
            ]
        ]);
    }
    $gethelp = select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'] ?? null;
    if ($gethelp && $gethelp != 2) {
        $data = json_decode($gethelp, true);
        if (is_array($data)) {
            if ($data['type'] == "text") { sendmessage($from_id, $data['text'], null, 'HTML'); }
            elseif ($data['type'] == "photo") { sendphoto($from_id, $data['photoid'], $data['text']); }
            elseif ($data['type'] == "video") { sendvideo($from_id, $data['videoid'], $data['text']); }
        }
    }
    $cartMsgResult = rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, 'html');
    updatePaymentMessageId($cartMsgResult, $orderId);
} elseif (preg_match('/^sendresidarze-(.*)/', $datain, $dataget) and $text_inline != null) {
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select");
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, "❗️ تراکنش شما توسط ربات تایید گردیده است.", null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire") {
        sendmessage($from_id, "❗زمان این تراکنش به پایان رسیده و امکان پرداخت این تراکنش وجود ندارد.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 تصویر واریزی خود یا لینک تراکنش ترون را ارسال نمایید.", $backuser, 'HTML');
    step('getresidcurrency', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == "digitaltron_hash_input") {


    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        if (!empty($message_id)) {
            @deletemessage($from_id, $message_id);
        }
        sendmessage(
            $from_id,
            "🏠 از حالت ارسال هش خارج شدید. هر زمان خواستید می‌توانید روی دکمه «📨 ارسال هش تراکنش» در فاکتور خودتان کلیک کنید.",
            $keyboard,
            'HTML'
        );
        return;
    }
    if (!isset($update['message']) && empty($text)) { return; }

    $trimmed = trim((string) $text);
    $looksLikeNav = (
        $trimmed === ''
        || mb_strlen($trimmed) < 40
        || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
        || strpos($trimmed, ' ') !== false
    );
    if ($looksLikeNav) {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);


        if ($trimmed !== '') {
            sendmessage($from_id, "🏠 از حالت ارسال هش خارج شدید. اکنون می‌توانید از منو استفاده کنید.", $keyboard, 'HTML');
        }
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_attach_hash')) {
        sendmessage($from_id, "❌ ماژول هش‌چکر بارگذاری نشده است.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!function_exists('crypto_extract_hash') || crypto_extract_hash($text) === null) {
        sendmessage($from_id, "❌ هش معتبر در پیام شما پیدا نشد. لطفاً هش تراکنش (Hash / TxID) یا لینک آن را ارسال کنید — نه آدرس کیف‌پول.", null, 'HTML');
        return;
    }
    $r = crypto_attach_hash($orderId, (string) $text);
    if (empty($r['ok'])) {
        $faMap = [
            'invalid-hash'      => 'هش وارد شده معتبر نیست.',
            'hash-already-used' => 'این هش قبلاً برای فاکتور دیگری ثبت شده است.',
            'order-not-pending' => 'این فاکتور در وضعیت قابل پرداخت نیست.',
            'no-db'             => 'خطای داخلی در پایگاه داده.',
            'db-update-failed'  => 'خطای داخلی در ذخیره‌سازی.',
        ];
        $msg = $faMap[$r['error'] ?? ''] ?? ($r['error'] ?? 'خطای ناشناخته');
        sendmessage($from_id, "❌ {$msg}", null, 'HTML');
        return;
    }
    $hashOrderRow = select("Payment_report", "*", "id_order", $orderId, "select");
    $hashOrderCur = (string) ($hashOrderRow['crypto_currency'] ?? '');
    $manualCurListHash = function_exists('crypto_manual_currencies') ? crypto_manual_currencies() : [];
    $isManualHashCur = isset($manualCurListHash[$hashOrderCur]);

    if ($isManualHashCur) {
        update("user", "Processing_value_four", $orderId, "id", $from_id);
        step('digitaltron_receipt_photo', $from_id);
        sendmessage(
            $from_id,
            "✅ هش تراکنش شما ثبت شد.\n\n"
            . "⚠️ این شبکه به‌صورت خودکار بررسی نمی‌شود. برای ثبت درخواست بررسی توسط ادمین، حالا <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری).\n\n"
            . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
            . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
            null,
            'HTML'
        );
    } else {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        sendmessage(
            $from_id,
            "✅ هش تراکنش شما ثبت شد.\n\n"
            . "ربات هر <b>۱ دقیقه</b> یک بار به‌صورت خودکار شبکه را بررسی می‌کند:\n\n"
            . "✅ اگر همه چیز درست باشد → معمولاً <b>۱ تا ۲ دقیقه</b> طول می‌کشد و موجودی شارژ می‌شود.\n"
            . "❌ اگر مبلغ یا آدرس مقصد اشتباه باشد → <b>در همان دقیقه‌ی اول</b> پیام رد دریافت می‌کنید.\n"
            . "⏰ اگر هش روی شبکه پیدا نشود → پس از <b>۳۰ دقیقه</b> فاکتور لغو می‌شود.\n\n"
            . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
            . "🔗 هش ثبت‌شده: <code>" . htmlspecialchars($r['hash']) . "</code>",
            $keyboard,
            'HTML'
        );
    }
} elseif ($user['step'] == "digitaltron_receipt_photo") {

    if (isset($datain) && $datain === "cancel_hash_input") {
        update("user", "Processing_value_four", "0", "id", $from_id);
        step('home', $from_id);
        sendmessage($from_id, "🏠 از حالت ارسال رسید خارج شدید.", $keyboard, 'HTML');
        return;
    }
    if (empty($photoid)) {
        $cancelKb = json_encode([
            'inline_keyboard' => [
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_hash_input']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, "❌ عکس دریافت نشد. لطفاً <b>عکس رسید تراکنش</b> را ارسال کنید (اجباری — فاکتور بدون عکس رسید برای ادمین ثبت نمی‌شود).", $cancelKb, 'HTML');
        return;
    }
    $orderId = (string) ($user['Processing_value_four'] ?? '');
    if ($orderId === '' || $orderId === '0') {
        sendmessage($from_id, "❌ خطای داخلی، لطفاً مجدداً از منوی پرداخت اقدام کنید.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $receiptRow = select("Payment_report", "*", "id_order", $orderId, "select");
    if (!$receiptRow || (string) ($receiptRow['id_user'] ?? '') !== (string) $from_id) {
        sendmessage($from_id, $datatextbot['dyn_wallet_invoice_not_found'] ?? "❌ فاکتور یافت نشد.", $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    update("Payment_report", "card_photo_file_id", (string) $photoid, "id_order", $orderId);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);

    $receiptCur = (string) ($receiptRow['crypto_currency'] ?? '');
    $receiptHash = (string) ($receiptRow['crypto_tx_hash'] ?? '');
    sendmessage(
        $from_id,
        "✅ عکس رسید ثبت شد.\n\n"
        . "درخواست شما برای ادمین ارسال شد. نتیجه از طریق همین چت اعلام خواهد شد.\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>",
        $keyboard,
        'HTML'
    );

    $receiptUserTagLine = '@' . ($user['username'] ?? 'none');
    $receiptAdminCaption = "🔁 <b>هش + رسید پرداخت کریپتو ثبت شد (شبکه دستی)</b>\n\n"
        . "🛒 کد فاکتور: <code>{$orderId}</code>\n"
        . "👤 کاربر: <code>{$from_id}</code> ({$receiptUserTagLine})\n"
        . "💎 ارز: <b>{$receiptCur}</b>\n"
        . "💸 مبلغ تومانی: " . number_format((int) ($receiptRow['price'] ?? 0)) . " تومان\n"
        . "🔗 هش: <code>" . htmlspecialchars($receiptHash) . "</code>";
    $receiptAdminKb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ تایید و شارژ', 'callback_data' => 'confirmcryptomanual_' . $orderId],
                ['text' => '❌ رد درخواست',   'callback_data' => 'rejectcryptomanual_' . $orderId],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $receiptAdminIds = function_exists('select') ? (select('admin', 'id_admin', null, null, 'FETCH_COLUMN') ?: []) : [];
    if (!is_array($receiptAdminIds)) $receiptAdminIds = [];
    foreach ($receiptAdminIds as $receiptAdminId) {
        if (!is_numeric($receiptAdminId)) continue;
        telegram('sendphoto', [
            'chat_id' => $receiptAdminId,
            'photo' => (string) $photoid,
            'caption' => $receiptAdminCaption,
            'parse_mode' => 'HTML',
            'reply_markup' => $receiptAdminKb,
        ]);
    }
} elseif ($user['step'] == "getresidcurrency") {
    if (!isset($update['message']) && empty($text)) { return; }
    $format_balance = number_format($user['Balance'], 0);
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Paymentusercount = select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
    if ($PaymentReport == false) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $textdiscount = "";
    $format_price_cart = number_format($PaymentReport['price'], 0);
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $user['Processing_value_one'], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\n\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $get_invoice['username'],
            'product_name' => $get_invoice['name_product'],
            'volume' => $get_invoice['Volume'],
            'service_time' => $get_invoice['Service_time'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextenduser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($nameloc['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'product_name' => $prodcut['name_product'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextravolumeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'volumes' => $volumes,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } elseif ($user['Processing_value_tow'] == "getextratimeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'days' => $time,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    } else {

        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💵 تعداد کل پرداختی های کاربر : {payment_count} عدد\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\nتوضیحات: {caption} {text}\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'payment_count' => $Paymentusercount,
            'format_price_cart' => $format_price_cart,
            'caption' => $caption,
            'text' => $text,
        ]);
    }
    $_arze_report_group = trim((string)($setting['Channel_Report'] ?? ''));
    if ($_arze_report_group !== '' && $_arze_report_group !== '0') {
        $_arze_thread = !empty($receiptreport) ? $receiptreport : null;
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $_arze_report_group,
                'message_thread_id' => $_arze_thread,
                'photo' => $photoid,
                'caption' => $textbotlang['users']['Balance']['receiptimage'],
                'parse_mode' => "HTML",
            ]);
        }
        $_arze_report_result = telegram('sendmessage', [
            'chat_id' => $_arze_report_group,
            'message_thread_id' => $_arze_thread,
            'text' => $textsendrasid,
            'reply_markup' => $Confirm_pay,
            'parse_mode' => "HTML",
        ]);
        $_arze_report_msg_id = is_array($_arze_report_result) ? (int) ($_arze_report_result['result']['message_id'] ?? 0) : 0;
        if ($_arze_report_msg_id > 0) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ?")
                ->execute([$_arze_report_group, $_arze_report_msg_id, $_arze_thread, $PaymentReport['id_order']]);
        }
    } else {
        $_arze_private_targets = [];
        foreach ($admin_ids as $id_admin) {
            $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
            if ($adminrulecheck['rule'] == "support")
                continue;
            if ($photo) {
                telegram('sendphoto', [
                    'chat_id' => $id_admin,
                    'photo' => $photoid,
                    'caption' => $textbotlang['users']['Balance']['receiptimage'],
                    'parse_mode' => "HTML",
                ]);
            }
            $_arze_private_result = sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
            $_arze_private_msg_id = is_array($_arze_private_result) ? (int) ($_arze_private_result['result']['message_id'] ?? 0) : 0;
            if ($_arze_private_msg_id > 0) {
                $_arze_private_targets[] = ['admin_id' => $id_admin, 'chat_id' => $id_admin, 'message_id' => $_arze_private_msg_id];
            }
        }
        if (!empty($_arze_private_targets)) {
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ? WHERE id_order = ?")
                ->execute([$_arze_private_targets[0]['chat_id'], $_arze_private_targets[0]['message_id'], $PaymentReport['id_order']]);
            if (function_exists('update')) {
                update("Payment_report", "private_receipt_targets", json_encode($_arze_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
            }
        }
    }
    $dateacc = date('Y/m/d H:i:s');
    $rxReceiptStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = ?, at_updated = ? WHERE id_order = ? AND COALESCE(payment_Status, '') NOT IN ('paid', 'processing', 'reconciling', 'manual_review') AND COALESCE(direct_payment_done, 0) = 0");
    $rxReceiptStmt->execute(["$text $caption", $dateacc, $PaymentReport['id_order']]);
    if ($rxReceiptStmt->rowCount() !== 1) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
        return;
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('Payment_report');
    }
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receiptadnsendconfig'], $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
} elseif ($user['step'] == "cart_to_cart_user") {
    $format_balance = number_format($user['Balance'], 0);
    if (!$photo or isset($update['message']['media_group_id'])) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_single_photo_only', '❌  فقط مجاز به ارسال یک تصویر هستید'), null, 'HTML');
        return;
    }
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value']);
    if ($PaymentReport == false) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    if (function_exists('rx_sbv_receipt_gate')) {
        $_sbv_gate = rx_sbv_receipt_gate((string) $PaymentReport['id_order']);
        if (empty($_sbv_gate['ok'])) {
            sendmessage($from_id, $_sbv_gate['message'], $keyboard, 'HTML');
            return;
        }
    }
    $receiptReserveStmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'pending', dec_not_confirmed = 'receipt-uploading', at_updated = :at_updated WHERE id_order = :id_order AND id_user = :id_user AND payment_Status = 'Unpaid'");
    $receiptReserveStmt->execute([
        ':at_updated' => date('Y/m/d H:i:s'),
        ':id_order' => $PaymentReport['id_order'],
        ':id_user' => $from_id,
    ]);
    if ($receiptReserveStmt->rowCount() !== 1) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price'], 0);
    $rxReceiptUserText = null;
    $split_data = explode('|', $PaymentReport['id_invoice']);
    if ($split_data[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $split_data[1], "select");
        if ($get_invoice == false) {
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                ->execute([':id_order' => $PaymentReport['id_order']]);
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $textdiscount = "";
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_new_purchase_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید سرویس جدید\nنام کاربری سرویس  : {service_username}\nنام محصول : {product_name}\nحجم محصول : {volume} گیگ\nزمان محصول : {service_time} روز\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $get_invoice['username'],
            'product_name' => $get_invoice['name_product'],
            'volume' => $get_invoice['Volume'],
            'service_time' => $get_invoice['Service_time'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        $rxReceiptUserText = $textbotlang['users']['Balance']['Send-receiptadnsendconfig'];
    } elseif ($split_data[0] == "getextenduser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
                ->execute([':id_order' => $PaymentReport['id_order']]);
            sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
        $custompricevalue = $eextraprice[$user['agent']];
        $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
        $customtimevalueprice = $eextraprice[$user['agent']];
        $codeproduct = $service_other['code_product'];
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = ($service_other['volumebuy'] * $custompricevalue) + ($service_other['Service_time'] * $customtimevalueprice);
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $_sloc = $nameloc['Service_location'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND code_product = ?");
            $_stmt->bind_param("ss", $_sloc, $codeproduct); $_stmt->execute();
            $prodcut = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addbalamceuser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => "⚙️ اطلاعات کانفیگ", 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extend_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nتمدید\nنام کاربری سرویس : {service_username}\nنام محصول : {product_name}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'product_name' => $prodcut['name_product'],
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        $rxReceiptUserText = faoxima_textbot_get('dyn_cart_receipt_extend_sent', '🚀 رسید شما ارسال و پس از بررسی سرویس شما تمدید خواهد شد');
    } elseif ($split_data[0] == "getextravolumeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extra_volume_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید حجم اضافه\nنام کاربری سرویس : {service_username}\nحجم خریداری شده  : {volumes}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'volumes' => $volumes,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        $rxReceiptUserText = faoxima_textbot_get('dyn_cart_receipt_extra_volume_sent', '🚀 رسید شما ارسال و پس از بررسی  به سرویس شما حجم اضافه خواهد شد.');
    } elseif ($split_data[0] == "getextratimeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_extra_time_tpl', "⭕️ یک پرداخت جدید انجام شده است .\n\n⭕️⭕️⭕️⭕️⭕️\nخرید زمان اضافه\nنام کاربری سرویس : {service_username}\nتعداد روز خریداری شده  : {days}\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'service_username' => $usernamepanel,
            'days' => $time,
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        $rxReceiptUserText = faoxima_textbot_get('dyn_cart_receipt_extra_time_sent', '🚀 رسید شما ارسال و پس از بررسی به سرویس شما زمان اضافه خواهد شد');
    } else {

        $textsendrasid = faoxima_render_text(faoxima_textbot_get('dyn_admin_notify_cart_wallet_topup_tpl', "⭕️ یک پرداخت جدید انجام شده است .\nافزایش موجودی\n👤 نام اکانت کاربر : {first_name}\n👤 شناسه کاربر:  <a href = \"tg://user?id={from_id}\">{from_id}</a>\n💸 موجودی فعلی کاربر : {format_balance} تومان\n🛒 کد پیگیری پرداخت: {order_id}\n⚜️ نام کاربری: @{username}\n💸 مبلغ پرداختی: {format_price_cart} تومان\n\n✍️ در صورت درست بودن رسید پرداخت را تایید نمایید."), [
            'first_name' => $first_name,
            'from_id' => $from_id,
            'format_balance' => $format_balance,
            'order_id' => $PaymentReport['id_order'],
            'username' => $username,
            'format_price_cart' => $format_price_cart,
        ]);
        $rxReceiptUserText = $textbotlang['users']['Balance']['Send-receipt'];
    }
    if (function_exists('rx_sbv_receipt_admin_note')) {
        $textsendrasid .= rx_sbv_receipt_admin_note((string) $PaymentReport['id_order']);
        $Confirm_pay = rx_sbv_receipt_keyboard($Confirm_pay, (string) $PaymentReport['id_order']);
    }
    $_card_fid = (string)($PaymentReport['card_photo_file_id'] ?? '');
    $_receipt_delivered = false;
    $_receipt_route = rxReceiptDeliveryRoute();
    $_receipt_report_group = trim((string)($_receipt_route['chat_id'] ?? ''));
    if (!empty($_receipt_route['topic_enabled']) && $_receipt_report_group !== '') {
        $_receipt_thread = !empty($_receipt_route['thread_id']) ? (int)$_receipt_route['thread_id'] : null;
        if ($_card_fid !== '') {
            telegram('sendMediaGroup', [
                'chat_id' => $_receipt_report_group,
                'message_thread_id' => $_receipt_thread,
                'media'   => [
                    ['type' => 'photo', 'media' => $_card_fid, 'caption' => faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                    ['type' => 'photo', 'media' => $photoid, 'caption' => faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
                ],
            ]);
        } else {
            telegram('sendphoto', [
                'chat_id'    => $_receipt_report_group,
                'message_thread_id' => $_receipt_thread,
                'photo'      => $photoid,
                'caption'    => $caption,
                'parse_mode' => "HTML",
            ]);
        }
        $_receipt_report_result = telegram('sendmessage', [
            'chat_id' => $_receipt_report_group,
            'message_thread_id' => $_receipt_thread,
            'text' => $textsendrasid,
            'reply_markup' => $Confirm_pay,
            'parse_mode' => "HTML",
        ]);
        $_receipt_report_msg_id = is_array($_receipt_report_result) ? (int) ($_receipt_report_result['result']['message_id'] ?? 0) : 0;
        if ($_receipt_report_msg_id > 0) {
            $_receipt_delivered = true;
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ?, report_thread_id = ? WHERE id_order = ?")
                ->execute([$_receipt_report_group, $_receipt_report_msg_id, $_receipt_thread, $PaymentReport['id_order']]);
        }
    } elseif (empty($_receipt_route['topic_enabled'])) {
        $_receipt_private_targets = [];
        foreach ($admin_ids as $id_admin) {
            $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
            if ($adminrulecheck['rule'] == "support")
                continue;
            if ($_card_fid !== '') {
                telegram('sendMediaGroup', [
                    'chat_id' => $id_admin,
                    'media'   => [
                        ['type' => 'photo', 'media' => $_card_fid, 'caption' => faoxima_textbot_get('dyn_receipt_user_card_caption', '🪪 کارت بانکی کاربر')],
                        ['type' => 'photo', 'media' => $photoid, 'caption' => faoxima_textbot_get('dyn_receipt_payment_receipt_caption', '🧾 رسید پرداخت')],
                    ],
                ]);
            } else {
                telegram('sendphoto', [
                    'chat_id'    => $id_admin,
                    'photo'      => $photoid,
                    'caption'    => $caption,
                    'parse_mode' => "HTML",
                ]);
            }
            $_receipt_private_result = sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
            $_receipt_private_msg_id = is_array($_receipt_private_result) ? (int) ($_receipt_private_result['result']['message_id'] ?? 0) : 0;
            if ($_receipt_private_msg_id > 0) {
                $_receipt_private_targets[] = ['admin_id' => $id_admin, 'chat_id' => $id_admin, 'message_id' => $_receipt_private_msg_id];
            }
        }
        if (!empty($_receipt_private_targets)) {
            $_receipt_delivered = true;
            $pdo->prepare("UPDATE Payment_report SET report_chat_id = ?, report_message_id = ? WHERE id_order = ?")
                ->execute([$_receipt_private_targets[0]['chat_id'], $_receipt_private_targets[0]['message_id'], $PaymentReport['id_order']]);
            if (function_exists('update')) {
                update("Payment_report", "private_receipt_targets", json_encode($_receipt_private_targets, JSON_UNESCAPED_UNICODE), "id_order", $PaymentReport['id_order']);
            }
        }
    }
    if (!$_receipt_delivered) {
        $pdo->prepare("UPDATE Payment_report SET payment_Status = 'Unpaid', dec_not_confirmed = NULL, at_updated = NULL WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'")
            ->execute([':id_order' => $PaymentReport['id_order']]);
        if (function_exists('clearSelectCache')) {
            clearSelectCache('Payment_report');
        }
        if (function_exists('rx_log_event')) {
            rx_log_event('RECEIPT_ADMIN_DELIVERY_FAILED', 'No administrator delivery reference for card receipt', [
                'id_order' => $PaymentReport['id_order'],
                'id_user' => $from_id,
            ]);
        }
        step('cart_to_cart_user', $from_id);
        sendmessage($from_id, faoxima_textbot_get('dyn_receipt_admin_delivery_failed', '❌ ارسال رسید به ادمین ناموفق بود. لطفاً دوباره تلاش کنید.'), $backuser, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'waiting', dec_not_confirmed = 'receipt-submitted', card_photo_file_id = :card_photo_file_id, at_updated = :at_updated WHERE id_order = :id_order AND payment_Status = 'pending' AND dec_not_confirmed = 'receipt-uploading'");
    $stmt->execute([
        ':card_photo_file_id' => $photoid,
        ':at_updated' => $dateacc,
        ':id_order' => $PaymentReport['id_order'],
    ]);
    if ($stmt->rowCount() !== 1) {
        sendmessage($from_id, faoxima_textbot_get('dyn_errors_data_fetch_restart', '❌ خطایی در هنگام دریافت اطلاعات رخ داده است لطفا مراحل را از اول انجام دهید'), $keyboard, 'HTML');
        return;
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('Payment_report');
    }
    if ($rxReceiptUserText !== null) {
        sendmessage($from_id, $rxReceiptUserText, $keyboard, 'HTML');
    }
    $_verifyReceiptCard = select('Payment_report', 'card_photo_file_id', 'id_order', $PaymentReport['id_order'], 'select', ['cache' => false]);
    if (empty($_verifyReceiptCard['card_photo_file_id']) && function_exists('rx_log_event')) {
        rx_log_event('RECEIPT_PHOTO_SAVE_VERIFY_FAILED', 'card_photo_file_id still empty right after commit', [
            'id_order' => $PaymentReport['id_order'],
        ]);
    }
} elseif ($datain == "Discount") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!rxTableValueExists('Discount', 'code', $text)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }
    $checklimit = select("Discount", "*", "code", $text, "select");
    $__gstatus = strtolower(trim((string)($checklimit['status'] ?? '')));
    if ($__gstatus !== '' && $__gstatus !== 'active') {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gtarget = trim((string)($checklimit['target_user'] ?? ''));
    if ($__gtarget !== '' && $__gtarget !== (string)$from_id) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $__gexp = intval($checklimit['expire_at'] ?? 0);
    if ($__gexp !== 0 && time() >= $__gexp) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    if (intval($checklimit['limituse']) > 0 && intval($checklimit['limitused']) >= intval($checklimit['limituse'])) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimitdiscount'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_user = $user['Balance'] + $get_codesql['price'];
    update("user", "Balance", $balance_user, "id", $from_id);
    $discountlimitadd = intval($checklimit['limitused']) + 1;
    update("Discount", "limitused", $discountlimitadd, "code", $text);
    step('home', $from_id);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], rxFormatToman($get_codesql['price']));
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:id_user, :code)");
    $stmt->execute([
        ':id_user' => $from_id,
        ':code' => $text,
    ]);
    $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (($text !== '' && $text == $datatextbot['text_Tariff_list']) || $datain == "Tariff_list") {
    rx_section_media_enter('tariff', $from_id, $datain, $message_id, ['Tariff_list']);
    sendmessage($from_id, $datatextbot['text_dec_Tariff_list'], null, 'HTML');
} elseif ($datain == "colselist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_affiliates']) || $datain == "affiliatesbtn") {
    if (!check_active_btn($setting['keyboardmain'], "text_affiliates")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['affiliatesstatus'] == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    $rxAffiliateLink = "https://t.me/$usernamebot?start=$from_id";
    $rxAffiliateBannerTpl = !empty($affiliates['banner_text']) ? $affiliates['banner_text'] : ($affiliates['description'] ?? '');
    $rxAffiliateBannerText = faoxima_render_text($rxAffiliateBannerTpl, [
        'link' => $rxAffiliateLink,
        'name' => $user['first_name'] ?? $username,
        'code' => $user['codeInvitation'] ?? $from_id,
    ]);
    $textaffiliates = "{$rxAffiliateBannerText}\n\n🔗 {$rxAffiliateLink}";
    if (strlen($affiliates['id_media']) >= 5 && $affiliates['id_media'] !== 'none') {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $affiliates['id_media'],
            'caption' => $textaffiliates,
            'parse_mode' => "HTML",
        ]);
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $sqlPanel = "SELECT COUNT(*) AS orders, COALESCE(SUM(price_product), 0) AS total_price
                 FROM invoice
                 WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
                 AND refral = :refral
                 AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sqlPanel);
    $stmt->execute([':refral' => $from_id]);
    $inforefral = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'total_price' => 0];
    $orders_count = (int)($inforefral['orders'] ?? 0);
    $total_purchase = (float)($inforefral['total_price'] ?? 0);
    $keyboard_share = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🎁 هدیه عضویت", 'callback_data' => "get_gift_start"],
                ['text' => "🔗 اشتراک لینک", 'url' => "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id"],
            ],
        ]
    ]);
    $text_start = "";
    $text_porsant = "";
    $Percent_porsant = $setting['affiliatespercentage'];
    $sum_order = number_format($total_purchase, 0);
    $rxCommissionEarnedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE id_user = :id_user AND category = 'affiliate_commission' AND direction = 'credit'");
    $rxCommissionEarnedStmt->execute([':id_user' => $from_id]);
    $rxCommissionEarned = number_format((float) $rxCommissionEarnedStmt->fetchColumn());
    $rxPendingGiftStmt = $pdo->prepare("SELECT COUNT(*) FROM reagent_report WHERE reagent = :reagent AND get_gift = 0");
    $rxPendingGiftStmt->execute([':reagent' => $from_id]);
    $rxPendingGiftCount = (int) $rxPendingGiftStmt->fetchColumn();
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $rxFmtAffiliateDiscount = rxFormatToman($affiliatescommission['price_Discount'] ?? 0);
        $text_start = "<b>🎁 هدیه عضویت:</b>
• 🎉 مجموع هدیه: {$rxFmtAffiliateDiscount} تومان
• 🔻 ۵۰٪ برای شما (معرف)
• 🔻 ۵۰٪ برای زیرمجموعه (کاربر جدید)
";
    }
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $text_porsant = "<b>💸 پورسانت خرید:</b>
•  $Percent_porsant درصد از مبلغ خرید زیرمجموعه به شما تعلق می‌گیره";
    }
    $textaffiliates = "<b>💼 زیرمجموعه‌گیری و هدیه خوش‌آمد</b>

با دعوت دوستان از طریق <b>لینک اختصاصی</b>، بدون پرداخت حتی ۱ ریال کیف پولت شارژ میشه و از خدمات ربات استفاده می‌کنی!

$text_start
$text_porsant

<b>📊 آمار شما:</b>
• 👥 زیرمجموعه‌ها: {$user['affiliatescount']} نفر
• 🛒 خریدها: $orders_count عدد
• 💵 مجموع خرید: $sum_order تومان
• 💰 مجموع پورسانت دریافتی: $rxCommissionEarned تومان
• ⏳ هدیه‌های در انتظار دریافت: $rxPendingGiftCount نفر

<b>📢 دعوت کن، هدیه بگیر، رشد کن!</b>
";
    $rxAffiliateInfoTpl = (string) ($textbotlang['users']['affiliates']['affiliateinfo'] ?? '');
    if (trim($rxAffiliateInfoTpl) !== '') {
        try {
            $rxAffiliateInfoText = vsprintf($rxAffiliateInfoTpl, [
                rxFormatToman($affiliatescommission['price_Discount'] ?? 0),
                $Percent_porsant,
                $user['affiliatescount'],
                $orders_count,
                $sum_order,
                $rxCommissionEarned,
                $rxPendingGiftCount,
            ]);
        } catch (\Throwable $rxAffiliateInfoErr) {
            $rxAffiliateInfoText = false;
        }
        if (is_string($rxAffiliateInfoText) && trim($rxAffiliateInfoText) !== '') {
            $textaffiliates = $rxAffiliateInfoText;
        }
    }

    sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
} elseif ($datain == "get_gift_start") {
    $rxGiftResult = rxReferralGiftPayout($from_id);
    $rxGiftStatus = (string) ($rxGiftResult['status'] ?? 'failed');
    if ($rxGiftStatus === 'paid') {
        rxReferralGiftNotify($rxGiftResult);
    } elseif ($rxGiftStatus === 'disabled' || $rxGiftStatus === 'no_amount') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_section_disabled'] ?? "📛 این بخش درحال حاضر غیرفعال می باشد", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'not_referred') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_not_affiliate_of_anyone'] ?? "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'already') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_already_received'] ?? "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'phone_required') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_phone_required'] ?? "📞 برای دریافت هدیه عضویت، ابتدا باید شماره موبایل خود را تایید کنید.", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'too_new') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_account_too_new'] ?? "⏳ حساب شما هنوز به اندازه کافی قدیمی نیست، کمی بعد دوباره تلاش کنید.", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'cap_daily') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف روزانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
    } elseif ($rxGiftStatus === 'cap_monthly') {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف ماهانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_failed'] ?? "⚠️ پرداخت هدیه عضویت در حال حاضر انجام نشد؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.", $keyboard, 'HTML');
    }
} elseif (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $locations = select("marzban_panel", "*", "code_panel", $dataget[2], "select");
    $location = $locations['name_panel'];
    $eextraprice = json_decode($locations['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $locations, 'extra_volume');
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    update("user", "Processing_value_one", $location, "id", $from_id);

    $textextra = sprintf($textbotlang['users']['Extra_volume']['enterextravolume'], $extrapricevalue);
    sendmessage($from_id, $textextra, $backuser, 'HTML');
    step('getvolumeextras', $from_id);
} elseif ($user['step'] == "getvolumeextras") {
    if (!isset($update['message']) && empty($text)) { return; }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    $priceextra = $extrapricevalue * $text;
    $priceextra = fx_finalize_amount($priceextra, $marzban_list_get, 'extra_volume');
    fx_quote_store_server($from_id, fx_quote_context_key('extra_volume_external', (string) $user['Processing_value'] . '|' . (string) $user['Processing_value_one']), $priceextra, $marzban_list_get, 'extra_volume', ['qty' => (int) $text]);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['Extra_volume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextras_(\w+)/', $datain, $dataget)) {
    $volume = $dataget[1];
    $fxExternalPanel = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if (!is_array($fxExternalPanel)) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $fxExternalUnit = fx_adjust_base_toman(json_decode((string) $fxExternalPanel['priceextravolume'], true)[$user['agent']] ?? null, $fxExternalPanel, 'extra_volume');
    $fxExternalVerify = fx_extra_quote_verify($from_id, fx_quote_context_key('extra_volume_external', (string) $user['Processing_value'] . '|' . (string) $user['Processing_value_one']), $fxExternalUnit, $volume, $fxExternalPanel, 'extra_volume');
    if ($fxExternalVerify['status'] === 'missing') {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($fxExternalVerify['status'] === 'changed') {
        sendmessage($from_id, fx_price_changed_text($fxExternalVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $fxExternalVerify['amount']]]]]), 'HTML');
        return;
    }
    $fxExternalQty = $fxExternalVerify['qty'];
    if (rx_ck_available_for_user($user) < $volume && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($user['Processing_value_one'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $volume = fx_finalize_amount($volume, $fxExternalPanel, 'extra_volume');
            $Balance_prim = $volume - rx_ck_available_for_user($user);
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            update("user", "Processing_value_tow", "", "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = fx_adjust_base_toman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volume = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $volume = fx_finalize_amount($volume, $marzban_list_get, 'extra_volume');

    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_one'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + (($fxExternalQty ?? intval($volume) / intval($extrapricevalue)) * pow(1024, 3));
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price) VALUES (:id_user, :username, :value, :type, :time, :price)");
    $value = $data_limit;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $user['Processing_value'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volume,
    ]);
    $data_limit_new = $fxExternalQty ?? (intval($volume) / intval($extrapricevalue));
    $extra_volume = $ManagePanel->extra_volume($user['Processing_value'], $marzban_list_get['code_panel'], $data_limit_new);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
        $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$user['Processing_value_one']}</blockquote>
<blockquote>نام کاربری سرویس : {$user['Processing_value']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
        sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (function_exists('balance_atomic_charge')) {
        $__allowNegEv = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__chargeEv = balance_atomic_charge($from_id, (float)$volume, $__allowNegEv);
        if (empty($__chargeEv['ok'])) {
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
            return;
        }
        $Balance_Low_user = $__chargeEv['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $volume, 'service_action', 'خرید حجم اضافه', null, 'invoice', (string)($user['Processing_value'] ?? ''));
        }
    } else {
        $Balance_Low_user = $user['Balance'] - $volume;
        update("user", "Balance", $Balance_Low_user, "id", $from_id);
    }
    $back = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
    $volumes = $fxExternalQty ?? $volume / $extrapricevalue;
    $volumes = number_format($volumes, 0);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumepurchase'], $from_id, $volumes, $volume, $user['Balance'], $user['Processing_value']);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    if (function_exists('faoxima_public_purchase_log_event')) {
        faoxima_public_purchase_log_event('volume_topup', [
            'user_id' => $from_id,
            'amount'  => $volumes,
            'price'   => number_format((float)$volume),
        ], $setting);
    }
} elseif ($datain == "searchservice") {
    sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
    step('getuseragnetservice', $from_id);
} elseif ($datain == "Responseuser") {
    step('getmessageAsuser', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backuser, 'HTML');
} elseif ($user['step'] == "getmessageAsuser") {
    if (!isset($update['message']) && empty($text)) { return; }
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        if ($text) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['usermessage'], $from_id, $username, $caption . $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['Admin']['MessageBulk']['userresponse'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
} elseif ((($text !== '' && $text == $datatextbot['textpanelagent']) || $datain == "agentpanel") && $user['agent'] != "f") {
    if ($setting['inlinebtnmain'] == "oninline" || (function_exists('rx_richSectionActive') && rx_richSectionActive('agent'))) {
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['agent']['agenttext'], $keyboardagent, 'HTML');
    }
} elseif ($text == $textbotlang['users']['agenttext']['customnameusername'] || $datain == "selectname") {
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
    step('selectusernamecustom', $from_id);
} elseif ($user['step'] == "selectusernamecustom") {
    if (!isset($update['message']) && empty($text)) { return; }
    $text = str_replace('_', '-', $text);
    if (!preg_match('~(?![_-])^[a-z][a-z\d_-]{2,32}(?<![_-])$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['submitusername'], $keyboardagent, 'html');
    update("user", "namecustom", $text, "id", $from_id);
    step("home", $from_id);
} elseif (($text !== '' && $text == $datatextbot['textrequestagent']) || $datain == "requestagent") {
    if ($user['Balance'] < $setting['agentreqprice']) {
        $priceagent = number_format($setting['agentreqprice']);
        sendmessage($from_id, sprintf($textbotlang['users']['agenttext']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
        return;
    }
    $existingAgentRequest = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($existingAgentRequest) {
        // فقط درخواستی که هنوز در حال بررسی (waiting) است مانع ثبت درخواست جدید می‌شود.
        if ($existingAgentRequest['status'] == "waiting") {
            sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], null, 'html');
            return;
        }
        // اگر درخواست قبلی رد شده بود، رکورد قدیمی پاک می‌شود تا کاربر بتواند مجدداً درخواست دهد.
        if ($existingAgentRequest['status'] == "reject") {
            $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id AND status = 'reject'");
            $stmtDelOld->execute([':id' => $from_id]);
        }
    }
    if ($user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['agenttext']['isagent'], null, 'html');
        return;
    }
    if ($datain == "requestagent") {
        Editmessagetext($from_id, $message_id, $datatextbot['text_request_agent_dec'], $backuser);
    } else {
        sendmessage($from_id, $datatextbot['text_request_agent_dec'], $backuser, 'html');
    }
    step("getagentrequest", $from_id);
} elseif ($user['step'] == "getagentrequest" && $text) {
    if (!isset($update['message']) && empty($text)) { return; }
    // اطمینان از نبود درخواست قدیمیِ رد/تاییدشده تا INSERT جدید با خطای کلید تکراری مواجه نشود.
    $oldReq = select("Requestagent", "*", "id", $from_id, "select", ['cache' => false]);
    if ($oldReq && $oldReq['status'] == "waiting") {
        // اگر همزمان درخواست در حال بررسی ثبت شده، از ثبت دوباره جلوگیری می‌کنیم.
        sendmessage($from_id, $textbotlang['users']['agenttext']['requestreport'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($oldReq) {
        $stmtDelOld = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id");
        $stmtDelOld->execute([':id' => $from_id]);
    }
    $balancelow = $user['Balance'] - $setting['agentreqprice'];
    update("user", "Balance", $balancelow, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['agenttext']['endrequest'], $keyboard, 'html');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
    $status = "waiting";
    $type = "None";
    $current_time = time();
    $description = $text;
    $requestAgentInserted = false;
    try {
        $stmt->execute([
            ':id' => $from_id,
            ':username' => $username,
            ':time' => $current_time,
            ':description' => $description,
            ':status' => $status,
            ':type' => $type,
        ]);
        $requestAgentInserted = true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4('Requestagent');
            if ($tableConverted) {
                try {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $description,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                }
            }

            if (!$requestAgentInserted) {
                $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                if ($sanitisedDescription !== $description) {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $sanitisedDescription,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } else {
                    throw $e;
                }
            }
        } else {
            throw $e;
        }
    }

    if (!$requestAgentInserted) {
        throw new RuntimeException('Failed to persist agent request description.');
    }
    $textrequestagent = sprintf($textbotlang['users']['agenttext']['agent-request'], $from_id, $username, $first_name, $text);
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['agenttext']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agenttext']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
    }
} elseif ($text == "/privacy") {
    sendmessage($from_id, $datatextbot['text_roll'], null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_wheel_luck']) || $datain == "wheel_luck" || $text == "/gift") {
    if (!check_active_btn($setting['keyboardmain'], "text_wheel_luck")) {
        sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
        return;
    }
    if ($setting['wheelagent'] == "0" and $user['agent'] != "f") {
        sendmessage($from_id, $datatextbot['dyn_wheel_button_disabled_you'] ?? "❌ این دکمه برای شما غیرفعال می باشد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if (intval($setting['statusfirstwheel']) == 1 and $countinvoice != 0) {
        sendmessage($from_id, $datatextbot['dyn_wheel_no_purchase_users_only'] ?? "❌ متاسفانه این آپشن فقط برای کاربرانی فعال است که از ربات خریدی نداشته باشند.", null, 'HTML');
        return;
    }
    if ($setting['wheelـluck'] == "0" or ($setting['wheelagent'] == "0" and $users['agent'] != "f")) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['feature-disabled'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = '$from_id' ORDER BY time DESC LIMIT 1");
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if (intval($setting['Dice']) == 1) {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎲",
        ]);
        sleep(4.5);
    } else {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎰",
        ]);
        sleep(4);
    }
    if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
        $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
        error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['error'] ?? '❌ خطایی در دریافت نتیجه بازی رخ داد. لطفاً بعداً مجدداً تلاش کنید.', null, 'HTML');
        return;
    }
    $diceValue = (int) $diceResponse['result']['dice']['value'];
    $dateacc = date('Y/m/d H:i:s');
    $wheelDay = date('Y-m-d');
    $status = false;
    if (intval($setting['Dice']) == 1) {
        if ($diceValue === 6) {
            $status = true;
        }
    } else {
        if (in_array($diceValue, [1, 43, 64, 22], true)) {
            $status = true;
        }
    }
    $pricelast = $status ? $setting['wheelـluck_price'] : 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price,wheel_day) VALUES (:id_user,:first_name,:wheel_code,:time,:price,:wheel_day)");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':wheel_code', $diceValue);
    $stmt->bindParam(':time', $dateacc);
    $stmt->bindParam(':price', $pricelast);
    $stmt->bindParam(':wheel_day', $wheelDay);
    $stmt->execute();
    if ($stmt->rowCount() !== 1) {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['already-participated'], null, 'HTML');
        return;
    }
    if ($status) {
        $wheelPrize = intval($setting['wheelـluck_price']);
        if (function_exists('balance_atomic_credit') && $wheelPrize > 0) {
            $__wheelOk = balance_atomic_credit($from_id, $wheelPrize);
            if ($__wheelOk && function_exists('wallet_ledger_record')) {
                wallet_ledger_record($from_id, 'credit', $wheelPrize, 'lottery', 'جایزه گردونه شانس', null, 'wheel_list', (string)$from_id);
            }
        } else {
            $balance_last = $wheelPrize + $user['Balance'];
            update("user", "Balance", $balance_last, "id", $from_id);
        }
        $price = number_format($setting['wheelـluck_price']);
        sendmessage($from_id, sprintf($textbotlang['users']['wheel_luck']['winner-congratulations'], $price), null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => sprintf($textbotlang['users']['wheel_luck']['wheel-winner'], $username, $from_id),
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['wheel_luck']['notWinner'], null, 'HTML');
    }
} elseif ($text == "/tron") {
    $rates = requireTronRates(['TRX']);
    if ($rates === null) {
        sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['TRX'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tron-price'], $price), null, 'HTML');
} elseif ($text == "/usd") {
    $rates = requireTronRates(['USD']);
    if ($rates === null) {
        sendmessage($from_id, $datatextbot['dyn_wheel_price_unavailable'] ?? "❌ دریافت قیمت در حال حاضر امکان پذیر نیست. لطفاً بعداً تلاش کنید.", null, 'HTML');
        return;
    }
    $price = $rates['USD'];
    sendmessage($from_id, sprintf($textbotlang['users']['pricearze']['tether-price'], $price), null, 'HTML');
} elseif (($text !== '' && $text == $datatextbot['text_extend']) || $datain == "extendbtn") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
        return;
    }
    rx_section_media_enter('extend', $from_id, $datain, $message_id, ['extendbtn']);
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $_start_index_i = (int)$start_index; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_start_index_i, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $_si2 = (int)$start_index; $_p2 = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_si2, $_p2);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == 'previous_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $_pp = (int)$previous_page; $_p = (int)$items_per_page;
    $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
    $_stmt->bind_param("sii", $from_id, $_pp, $_p);
    $_stmt->execute();
    $result = $_stmt->get_result();
    $_stmt->close();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $rxLabelDup = rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
    if ($statusnote) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
    $backuser = [
        rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif (preg_match('/changenote_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $rxNoteCheck = select("invoice", "id_user", "id_invoice", $id_invoice, "select");
    if (!is_array($rxNoteCheck) || (string)($rxNoteCheck['id_user'] ?? '') !== (string)$from_id) {
        if (function_exists('rx_log_event')) {
            rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changenote on non-owned invoice', [
                'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changenote',
            ]);
        }
        return;
    }
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['SendNote'], $backinfoss);
    step("getnotedit", $from_id);
} elseif ($user['step'] == "getnotedit") {
    if (!isset($update['message']) && empty($text)) { return; }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (strlen($text) > 150) {
        sendmessage($from_id, $textbotlang['users']['note']['ErrorLongNote'], $keyboard, "html");
        return;
    }
    $text = sanitizeUserName($text);
    $id_invoice = $user['Processing_value'];
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    update("invoice", "note", $text, "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
    step("home", $from_id);
    $timejalali = jdate('Y/m/d H:i:s');
    $textreport = "📌  یک کاربر یادداشت سرویس خود را تغییر داد.

<blockquote>▫️ نام کاربری سرویس : {$invoice['username']}</blockquote>
<blockquote>▫️ یاداشت قبلی :‌ {$invoice['note']}</blockquote>
<blockquote>▫️ یاداشت جدید :‌  $text</blockquote>

<blockquote>زمان تغییر یادداشت : $timejalali </blockquote>";
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}

if (isset($update['pre_checkout_query']) && function_exists('rxStarsPreCheckoutV2') && rxStarsPreCheckoutV2($update['pre_checkout_query'])) {
    return;
} elseif (isset($update['pre_checkout_query'])) {
    $userid = $update['pre_checkout_query']['from']['id'];
    $id_order = $update['pre_checkout_query']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    } else {
        telegram('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => true,
        ]);
    }
    if ($Payment_report['payment_Status'] == "paid") {
        return;
    }
    $atomicStarPay = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = :o AND payment_Status <> 'paid'");
    $atomicStarPay->execute([':o' => $Payment_report['id_order']]);
    if ($atomicStarPay->rowCount() < 1) {
        return;
    }
    update("Payment_report", "dec_not_confirmed", json_encode($update['pre_checkout_query']), "id_order", $Payment_report['id_order']);
    DirectPayment($Payment_report['id_order']);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackstar", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $cashbackEligible = !function_exists('rx_cashbackEligibleForKey')
        || rx_cashbackEligibleForKey("chashbackstar", $Balance_id['register'] ?? null, $Payment_report['id_invoice'] ?? null, $Balance_id['id'] ?? null, $Payment_report['id_order'] ?? null);
    if ($cashbackEligible && $pricecashback != "0") {
        $result = (int) floor(($Payment_report['price'] * $pricecashback) / 100);
        if (rx_cashback_credit_once($Payment_report['id_order'], $Balance_id['id'], $result, 'chashbackstar', 'کش‌بک پرداخت استارز') === 'credited') {
            $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], rxFormatToman($result));
            sendmessage($Balance_id['id'], $text_report, null, 'HTML');
        }
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => sprintf($textbotlang['Admin']['reportgroup']['new-payment-star'], $Balance_id['username'], $Balance_id['id'], $Payment_report['price'], $update['pre_checkout_query']['total_amount']),
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/extends_(\w+)_(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $invoiceIdForExtend = $dataget[2] ?? '';
    $nameloc = false;
    if ($invoiceIdForExtend !== '') {
        $nameloc = select("invoice", "*", "id_invoice", $invoiceIdForExtend, "select");
        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extends on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $invoiceIdForExtend, 'handler' => 'extends',
                ]);
            }
            $nameloc = false;
        }
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $username, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if (function_exists('nmStockPanelForInvoice') && is_array($nameloc)) {
        $marzban_list_get = nmStockPanelForInvoice($nameloc);
    } else {
        $marzban_list_get = false;
    }
    if (!is_array($marzban_list_get)) {
        $panelRef = $user['Processing_value_four'] ?? '';
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $panelRef, "select");
        if (!is_array($marzban_list_get)) {
            $marzban_list_get = select("marzban_panel", "*", "code_panel", $panelRef, "select");
        }
    }
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید", null, 'html');
        return;
    }
    $location = $marzban_list_get['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    if (is_array($nameloc) && !empty($nameloc['id_invoice'])) {
        update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    }

    if (is_array($nameloc) && function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get) && function_exists('nmStockExtendProductKeyboard')) {
        $stockKeyboard = nmStockExtendProductKeyboard($nameloc, $user, $marzban_list_get);
        if (!$stockKeyboard) {
            Editmessagetext($from_id, $message_id, "❌ برای این سرویس موجودی انبار قابل تمدید وجود ندارد.", json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE), 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, "📦 وضعیت نت ملی فعال است؛ فقط محصولات موجود در انبار برای تمدید نمایش داده می‌شوند. یکی را انتخاب کنید:", $stockKeyboard, 'HTML');
        return;
    }

    $query = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
    $queryParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $customVolumeData = json_decode($marzban_list_get['customvolume'] ?? '{}', true);
    if (!is_array($customVolumeData)) $customVolumeData = [];
    $statuscustomvolume = $customVolumeData[$user['agent']] ?? '0';
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
    $textextend = faoxima_render_text($textbotlang['users']['extend']['selectservice'], [
        'panel' => $marzban_list_get['name_panel'],
    ]);
    Editmessagetext($from_id, $message_id, $textextend, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $codeproduct = $dataget[1];
    $username = $dataget[2];
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:processing_value, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend preview failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
        return;
    }
    $fxExternalRenewPanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($fxExternalRenewPanel)) {
        $prodcut = fx_apply_to_product($prodcut, $fxExternalRenewPanel);
        $prodcut['price_product'] = fx_finalize_amount($prodcut['price_product'], $fxExternalRenewPanel, 'product');
        fx_quote_store($from_id, fx_quote_context_key('renew_external', (string) $username . '|' . (string) $codeproduct), fx_finalize_amount(fx_apply_user_discount($prodcut['price_product'], $user['pricediscount']), $fxExternalRenewPanel, 'product'), $fxExternalRenewPanel, 'product');
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
            ]
        ]
    ]);
    $prodcutVolumeLabel = intval($prodcut['Volume_constraint']) == 0 ? $textbotlang['users']['stateus']['Unlimited'] : $prodcut['Volume_constraint'];
    $rxRenewPreview = rxRenewalPreviewForService($ManagePanel, select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select"), (string) $username, $prodcut['Volume_constraint'], $prodcut['Service_time'], ['stage' => 'telegram_preview']);
    if (!empty($rxRenewPreview['blocked'])) {
        $keyboardextend = null;
    }
    $rxRenewInvoiceText = sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcutVolumeLabel, $prodcut['note'], $user['Balance']);
    if ($rxRenewPreview['text'] !== '') {
        $rxRenewInvoiceText .= "\n\n" . $rxRenewPreview['text'];
    }
    sendmessage($from_id, $rxRenewInvoiceText, $keyboardextend, 'html');
} elseif (preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget)) {
    $codeproduct = $dataget[1];
    $usernamePanelExtends = $dataget[2];
    deletemessage($from_id, $message_id);
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:processing_value, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend confirmation failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $prodcut = fx_apply_to_product($prodcut, $marzban_list_get);
    $prodcut['price_product'] = fx_finalize_amount($prodcut['price_product'], $marzban_list_get, 'product');
    $fxExternalRenewKey = fx_quote_context_key('renew_external', (string) $usernamePanelExtends . '|' . (string) $codeproduct);
    $fxExternalRenewAmount = fx_finalize_amount(fx_apply_user_discount($prodcut['price_product'], $user['pricediscount']), $marzban_list_get, 'product');
    if (!fx_quote_check($from_id, $fxExternalRenewKey, $fxExternalRenewAmount, $marzban_list_get, 'product')) {
        fx_quote_store($from_id, $fxExternalRenewKey, $fxExternalRenewAmount, $marzban_list_get, 'product');
        sendmessage($from_id, fx_price_changed_text($fxExternalRenewAmount), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $usernamePanelExtends]]]]), 'HTML');
        return;
    }
    $nameloc = false;
    if (!empty($user['Processing_value_one'])) {
        $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value_one'], "select");
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $usernamePanelExtends, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if (rx_ck_available_for_user($user) < $prodcut['price_product'] && $user['agent'] != "n2") {
        $marzbandirectpay = panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $prodcut['price_product'] = fx_finalize_amount($prodcut['price_product'], $marzban_list_get, 'product');
            $Balance_prim = $prodcut['price_product'] - rx_ck_available_for_user($user);
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            update("user", "Processing_value_tow", "", "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $prodcut['price_product']) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
        $prodcut['price_product'] = $prodcut['price_product'] - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $prodcut['price_product'] = fx_finalize_amount($prodcut['price_product'], $marzban_list_get, 'product');
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (!is_array($nameloc)) {
            sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
            return;
        }
        $stockNew = function_exists('nmStockReserveForProduct') ? nmStockReserveForProduct($marzban_list_get, $prodcut, $from_id, $nameloc['id_invoice'], 'normal_extend_national_stock') : false;
        if (!$stockNew) {
            sendmessage($from_id, $datatextbot['dyn_errors_stock_depleted_no_charge'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.", $keyboard, 'HTML');
            return;
        }
        $__pp = (float)$prodcut['price_product'];
        $__allowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__charge = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp, $__allowNeg) : ['ok' => false, 'reason' => 'helper-missing'];
        if (empty($__charge['ok'])) {
            if (function_exists('nmStockReleaseReservation')) nmStockReleaseReservation($stockNew);
            sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
            return;
        }
        $Balance_Low_user = $__charge['new_balance'];
        if (function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'debit', $__pp, 'service_action', 'تمدید سرویس (انبار ملی)', null, 'invoice', (string)$nameloc['id_invoice']);
        }
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        $invoiceNew = array_merge($nameloc, [
            'name_product' => $prodcut['name_product'],
            'price_product' => $prodcut['price_product'],
            'Volume' => $prodcut['Volume_constraint'],
            'Service_time' => $prodcut['Service_time'],
            'time_sell' => time(),
            'user_info' => $stockNew['content'],
            'source_panel_code' => $marzban_list_get['code_panel'],
        ]);
        nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
        sendmessage($from_id, $datatextbot['dyn_errors_stock_extend_success'] ?? "✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.", $keyboard, 'HTML');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $rxFinalizePreview = rxRenewalPreviewForService($ManagePanel, $marzban_list_get, (string) $usernamePanelExtends, $prodcut['Volume_constraint'], $prodcut['Service_time'], ['snapshot' => rx_pf_snapshot_from_datauser($DataUserOut, (string) ($marzban_list_get['type'] ?? '')), 'id_invoice' => is_array($nameloc) ? (string) $nameloc['id_invoice'] : '', 'stage' => 'telegram_confirm']);
    if (!empty($rxFinalizePreview['blocked'])) {
        sendmessage($from_id, (string) $rxFinalizePreview['text'], $keyboard, 'HTML');
        return;
    }
    $__pp2 = (float)$prodcut['price_product'];
    $__allowNeg2 = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__charge2 = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp2, $__allowNeg2) : ['ok' => false, 'reason' => 'helper-missing'];
    if (empty($__charge2['ok'])) {
        sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
        return;
    }
    $Balance_Low_user = $__charge2['new_balance'];
    if (function_exists('wallet_ledger_record')) {
        wallet_ledger_record($from_id, 'debit', $__pp2, 'service_action', 'تمدید سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
    }
    $rxFinalizeRef = 'wallet:' . $from_id . ':' . bin2hex(random_bytes(4));
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel'], null, [
        'source' => 'wallet',
        'ref' => $rxFinalizeRef,
        'id_user' => $from_id,
        'price' => $__pp2,
        'reservation' => ['payment_ref' => $rxFinalizeRef],
    ]);
    $rxFinalizePending = rxRenewalIsPending($extend);
    if (empty($extend['status']) && !$rxFinalizePending && function_exists('balance_atomic_credit')) {
        $__refundedFin = balance_atomic_credit($from_id, $__pp2);
        $Balance_Low_user = $user['Balance'];
        if ($__refundedFin && function_exists('wallet_ledger_record')) {
            wallet_ledger_record($from_id, 'credit', $__pp2, 'refund', 'بازگشت وجه تمدید سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
        }
    }
    if ($extend['status'] == false && !$rxFinalizePending) {
        $rxStockEmpty = ($extend['code'] ?? '') === 'manual_stock_empty';
        $rxQueuedExists = ($extend['code'] ?? '') === 'queued_renewal_exists';
        $extend['msg'] = rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
        $textreports = "خطای تمدید سرویس
        <blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
        <blockquote>نام کاربری سرویس : $usernamePanelExtends</blockquote>
        <blockquote>کد خطا : " . htmlspecialchars((string) ($extend['code'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>
        <blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
        if ($rxStockEmpty) {
            sendmessage($from_id, $datatextbot['dyn_errors_renewal_stock_empty'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } elseif ($rxQueuedExists) {
            sendmessage($from_id, $datatextbot['dyn_errors_queued_renewal_exists'] ?? "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
        } else {
            sendmessage($from_id, rxRenewalFailureText($extend, $datatextbot['dyn_errors_renewal_support_error'] ?? "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
        }
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (is_array($nameloc) && !$rxFinalizePending && empty($extend['queued'])) {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "user_info", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        try { update("invoice", "source_panel_code", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
    ));
    $value = fx_value_with_snapshot($value, fx_pricing_snapshot($marzban_list_get, 'product', $prodcut['fx_base_price'] ?? $prodcut['price_product'], $prodcut['price_product'], 0, ['product' => (string) ($prodcut['code_product'] ?? '')]));
    $dateacc = date('Y/m/d H:i:s');
    $type = "extends_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $usernamePanelExtends,
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $prodcut['price_product'],
        ':output' => json_encode($extend)
    ]);
    $prodcut['price_product'] = number_format($prodcut['price_product']);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    if (!empty($extend['queued'])) {
        $rxQueuedSuccessTpl = (string) ($extend['reservation']['confirmation'] ?? rxRenewalText('reservation_confirmed'));
        $textextend = "$rxQueuedSuccessTpl

▫️نام سرویس : $usernamePanelExtends
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید {$prodcut['price_product']} تومان
";
    } else {
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamePanelExtends
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید {$prodcut['price_product']} تومان
";
        $textextend = rxRenewalDecorateSuccess($textextend, $extend, (string) $usernamePanelExtends, (string) ($marzban_list_get['name_panel'] ?? ''));
    }
    sendmessage($from_id, $textextend, $keyboard, 'HTML');
    if ($marzban_list_get['type'] == "Manualsale") {
        $manualSubLink = is_array($extend) ? rxResolveConnectionLink($marzban_list_get, $extend['subscription_url'] ?? "", $extend['file_ext'] ?? null) : "";
        sendMessageService($marzban_list_get, "", $manualSubLink, $usernamePanelExtends, null, $textextend, $nameloc['id_invoice'] ?? "", $from_id);
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewaldetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
}
if (in_array($from_id, $admin_ids)) {
    $GLOBALS['rx_admin_scope'] = true;
    require_once 'admin.php';
}

$pdo = null;
$connect->close();
