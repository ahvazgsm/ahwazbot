<?php
require_once 'config.php';
function applyConfiguredKeyboardStyles($markup)
{
    if (!is_string($markup) || $markup === '') return $markup;
    $data = json_decode($markup, true);
    if (!is_array($data)) return $markup;
    $isInline = isset($data['inline_keyboard']) && is_array($data['inline_keyboard']);
    $isReply = isset($data['keyboard']) && is_array($data['keyboard']);
    if (!$isInline && !$isReply) return $markup;

    $configPath = __DIR__ . '/button-colors.json';
    $config = is_file($configPath) ? json_decode((string)file_get_contents($configPath), true) : [];
    if (!is_array($config)) $config = [];
    $allowedStyles = ['primary', 'success', 'danger'];
    $valid = static function ($style, $fallback = 'primary') use ($allowedStyles) {
        return is_string($style) && in_array($style, $allowedStyles, true) ? $style : $fallback;
    };
    $styles = [
        'primary' => $valid($config['primary'] ?? null, 'primary'),
        'success' => $valid($config['success'] ?? null, 'success'),
        'danger' => $valid($config['danger'] ?? null, 'danger'),
    ];
    $buttonStyles = is_array($config['buttons'] ?? null) ? $config['buttons'] : [];
    $replyStyles = is_array($config['reply_buttons'] ?? null) ? $config['reply_buttons'] : [];

    // Prefer styles configured in the main panel (setting.keyboard_styles_all).
    $panelStyles = [];
    try {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $stmt = $GLOBALS['pdo']->query("SELECT keyboard_styles_all FROM setting LIMIT 1");
            $rawPanelStyles = $stmt ? $stmt->fetchColumn() : false;
            $parsedPanelStyles = is_string($rawPanelStyles) ? json_decode($rawPanelStyles, true) : null;
            if (is_array($parsedPanelStyles)) {
                foreach ($parsedPanelStyles as $menuStyles) {
                    if (!is_array($menuStyles)) continue;
                    foreach ($menuStyles as $key => $style) {
                        if (is_string($style) && in_array($style, $allowedStyles, true)) $panelStyles[(string)$key] = $style;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[KEYBOARD_STYLE] Could not load configured styles: ' . $e->getMessage());
    }

    if ($isInline) {
        foreach ($data['inline_keyboard'] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$button) {
                if (!is_array($button) || !isset($button['text'])) continue;
                $label = (string)$button['text'];
                $callback = (string)($button['callback_data'] ?? '');
                if ($callback !== '' && isset($panelStyles[$callback])) {
                    $button['style'] = $panelStyles[$callback];
                    continue;
                }
                if ($callback !== '' && isset($buttonStyles[$callback])) {
                    $button['style'] = $valid($buttonStyles[$callback], $styles['primary']);
                    continue;
                }
                if (isset($button['copy_text'])) $role = 'success';
                elseif (in_array($callback, ['account', 'main_test', 'AddBalance'], true)) $role = 'success';
                elseif ($callback === 'admin' || preg_match('/بازگشت|انصراف|لغو|حذف|رد کردن/u', $label)) $role = 'danger';
                elseif (preg_match('/تأیید|تایید|تکمیل|دریافت سرویس|پرداخت از موجودی|کپی/u', $label)) $role = 'success';
                else $role = 'primary';
                $button['style'] = $styles[$role];
            }
            unset($button);
        }
        unset($row);
    }

    if ($isReply) {
        foreach ($data['keyboard'] as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$button) {
                if (!is_array($button) || !isset($button['text'])) continue;
                $label = trim((string)$button['text']);
                $key = null;
                if (preg_match('/خرید|دکمه خرید/u', $label)) $key = 'buy';
                elseif (preg_match('/تست|دکمه تست/u', $label)) $key = 'test';
                elseif (preg_match('/سرویس.?های من|سرویس های من/u', $label)) $key = 'my_service';
                elseif (preg_match('/کیف پول|حساب کاربری|افزایش موجودی/u', $label)) $key = 'wallet';
                elseif (preg_match('/پشتیبانی|متن دکمه پشتیبانی/u', $label)) $key = 'support';
                elseif (preg_match('/پنل مدیریت|منوی ادمین/u', $label)) $key = 'admin';
                elseif (preg_match('/بازگشت|خانه|منوی اصلی/u', $label)) $key = 'back';
                if ($key !== null && isset($replyStyles[$key])) {
                    $button['style'] = $valid($replyStyles[$key], 'primary');
                    continue;
                }
                if (preg_match('/بازگشت|لغو|حذف|رد کردن/u', $label)) $role = 'danger';
                elseif (preg_match('/خرید|تأیید|تایید|افزایش موجودی|پشتیبانی/u', $label)) $role = 'success';
                else $role = 'primary';
                $button['style'] = $styles[$role];
            }
            unset($button);
        }
        unset($row);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $markup;
}

function telegram($method, $datas = [],$botToken = null)
{
    global $ApiToken;
    if($botToken != null){
        $ApiToken = $botToken;
    }
    $url = "https://api.telegram.org/bot" . $ApiToken . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res,true);
    }
}
function sendmessage($chat_id,$text,$keyboard,$parse_mode){
    $keyboard = applyConfiguredKeyboardStyles($keyboard);
    return telegram('sendmessage',[
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
        
        ]);
}
function prepareTelegramInputFile($input)
{
    if ($input instanceof CURLFile) {
        return $input;
    }

    if (is_string($input)) {
        if (preg_match('/^https?:\/\//i', $input)) {
            return $input;
        }

        $realPath = realpath($input);
        if ($realPath !== false && is_file($realPath) && is_readable($realPath)) {
            return new CURLFile($realPath);
        }

        error_log(sprintf('Telegram document path is not readable: %s', $input));
        return null;
    }

    error_log('Unsupported Telegram input file type: ' . gettype($input));
    return null;
}

function sendDocument($chat_id, $documentPath, $caption) {
        $document = prepareTelegramInputFile($documentPath);
        if ($document === null) {
            return [
                'ok' => false,
                'description' => 'Document could not be prepared for Telegram upload.'
            ];
        }

        return telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => $caption,
        ]);
}

function forwardMessage($chat_id,$message_id,$chat_id_user){
    telegram('forwardMessage',[
        'from_chat_id'=> $chat_id,
        'message_id'=> $message_id,
        'chat_id'=> $chat_id_user,
    ]);
}
function sendphoto($chat_id,$photoid,$caption){
    telegram('sendphoto',[
        'chat_id' => $chat_id,
        'photo'=> $photoid,
        'caption'=> $caption,
    ]);
}
function sendvideo($chat_id,$videoid,$caption){
    telegram('sendvideo',[
        'chat_id' => $chat_id,
        'video'=> $videoid,
        'caption'=> $caption,
    ]);
}
function senddocumentsid($chat_id,$documentid,$caption){
    telegram('sendDocument',[
        'chat_id' => $chat_id,
        'document'=> $documentid,
        'caption'=> $caption,
    ]);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard,$parse_mode = 'HTML'){
    $keyboard = applyConfiguredKeyboardStyles($keyboard);
    return telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,

    ]);
}
 function deletemessage($chat_id, $message_id){
  telegram('deletemessage', [
'chat_id' => $chat_id, 
'message_id' => $message_id,
]);
 }
function getFileddire($photoid){
  return telegram('getFile', [
'file_id' => $photoid, 
]);
 }
function pinmessage($from_id,$message_id){
  return telegram('pinChatMessage', [
'chat_id' => $from_id, 
'message_id' => $message_id, 
]);
 }
 function unpinmessage($from_id){
  return telegram('unpinAllChatMessages', [
'chat_id' => $from_id, 
]);
 }
  function answerInlineQuery($inline_query_id,$results){
  return telegram('answerInlineQuery', [
      "inline_query_id" => $inline_query_id,
        "results" => json_encode($results)
]);
 }
 function convertPersianNumbersToEnglish($string) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($persian_numbers, $english_numbers, $string);
}
#-----------------------------#
$update = json_decode(file_get_contents("php://input"), true);
$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update["inline_query"]['from']['id'] ?? 0;
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"]  ?? '';
$text =convertPersianNumbersToEnglish($text);
$text_inline = $update["callback_query"]["message"]['text'] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$document = $update["message"]["document"] ?? 0;
$fileid = $update["message"]["document"]["file_id"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$datain = $update["callback_query"]["data"] ?? '';
$first_name = $update['message']['from']['first_name']  ?? $update["callback_query"]["from"]["first_name"] ?? $update["inline_query"]['from']['first_name'] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $update["callback_query"]["from"]["username"] ?? 'NOT_USERNAME';
$user_phone =$update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$callback_query_id = $update["callback_query"]["id"] ?? 0;
$inline_query_id = $update["inline_query"]["id"] ?? 0;
$query = $update["inline_query"]["query"] ?? 0;