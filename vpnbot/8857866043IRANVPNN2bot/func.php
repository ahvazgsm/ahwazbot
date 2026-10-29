<?php

/** Safely read an optional JSON file; return an empty array when missing/invalid. */
if (!function_exists('readJsonFileIfExists')) {
    function readJsonFileIfExists($filePath) {
        if (!is_string($filePath) || $filePath === '') {
            return [];
        }
        $path = $filePath;
        if (!is_file($path)) {
            $path = __DIR__ . DIRECTORY_SEPARATOR . ltrim($filePath, '/\\');
        }
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return [];
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }
}


function DirectPaymentbot($order_id,$image = 'images.jpg'){
    global $pdo,$ManagePanel,$textbotlang,$keyboardextendfnished,$keyboard,$Confirm_pay,$from_id,$message_id,$datatextbot;
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id,"select");
    $paymentNote = function_exists('formatPaymentReportNote')
        ? formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null)
        : ($Payment_report['dec_not_confirmed'] ?? '');
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'],"select");
    $Balance_id['Balance'] = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true)['Balance'];
    $isDirectPurchase = (($Payment_report['id_invoice'] ?? '') === 'DIRECT_PURCHASE');
    if (!$isDirectPurchase) {
        update("user","Processing_value","0", "id",$Balance_id['id']);
        update("user","Processing_value_one","0", "id",$Balance_id['id']);
        update("user","Processing_value_tow","0", "id",$Balance_id['id']);
        update("user","Processing_value_four","0", "id",$Balance_id['id']);
    }
    $Balance_confrim = intval($Balance_id['Balance']) + intval($Payment_report['price']);
        $userbalance = json_decode(file_get_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json"),true);
        $userbalance['Balance'] = $Balance_confrim;
        file_put_contents("data/{$Payment_report['id_user']}/{$Payment_report['id_user']}.json",json_encode($userbalance));
        update("Payment_report","payment_Status","paid","id_order",$Payment_report['id_order']);
        $Payment_report['price'] = number_format($Payment_report['price'], 0);
        $format_price_cart = $Payment_report['price'];
        if($Payment_report['Payment_Method'] == "cart to cart" or   $Payment_report['Payment_Method'] == "arze digital offline"){
        $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        if ($isDirectPurchase) {
            step('payment', $Payment_report['id_user']);
            $completePurchaseKeyboard = json_encode(['inline_keyboard' => [
                [['text' => '✅ تکمیل خرید و دریافت اشتراک', 'callback_data' => 'confirmandgetservice', 'style' => 'success']]
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            sendmessage($Payment_report['id_user'], "✅ پرداخت مستقیم خرید تأیید شد.\n🛒 کد پیگیری: {$Payment_report['id_order']}\n\nبرای ساخت اشتراک، روی دکمه زیر بزن.", $completePurchaseKeyboard, 'HTML');
        } else {
            sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.\n\n🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
        }
}
function channel_check($id_channel){
    global $from_id;
        $channel_link = array();
         $response = telegram('getChatMember',[
                'chat_id' => $id_channel,
                'user_id' => $from_id
                ]);
            if($response['ok']){
        if(!in_array($response['result']['status'], ['member', 'creator', 'administrator'])){
                $channel_link[] = $id_channel;
            }
        }
        
        if(count($channel_link) == 0){
            return [];
        }else{
            return $channel_link;
        }
}
