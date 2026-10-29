<?php

$botinfo = select("botsaz", "*", "bot_token", $ApiToken, "select");
$userbot = select("user", "*", "id", $botinfo['id_user'], "select");
$hide_panel = json_decode($botinfo['hide_panel'], true);
$text_bot_var =  json_decode(file_get_contents('text.json'), true);
// Main menu: persistent Telegram ReplyKeyboardMarkup (fixed keyboard at bottom).
// Reply keyboards cannot have custom per-button colors or glass effects in Telegram.
$keyboarddate = [
    'buy' => $text_bot_var['btn_keyboard']['buy'],
    'test' => $text_bot_var['btn_keyboard']['test'],
    'my_service' => $text_bot_var['btn_keyboard']['my_service'],
    'wallet' => $text_bot_var['btn_keyboard']['wallet'],
    'support' => $text_bot_var['btn_keyboard']['support'],
];
$list_admin = select("botsaz", "*", "bot_token", $ApiToken, "select");
$admin_idsmain = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$mainAdminIds = json_decode($list_admin['admin_ids'] ?? '[]', true);
if (!is_array($mainAdminIds)) $mainAdminIds = [];
if (in_array($from_id, $mainAdminIds) || in_array($from_id, $admin_idsmain)) {
    $keyboarddate['admin'] = "👨‍💼 پنل مدیریت";
}
$keyboardRows = [];
$tempArray = [];
foreach ($keyboarddate as $key => $keyboardtext) {
    $tempArray[] = ['text' => $keyboardtext];
    if (count($tempArray) === 2) {
        $keyboardRows[] = $tempArray;
        $tempArray = [];
    }
}
if ($tempArray) $keyboardRows[] = $tempArray;
$keyboard = json_encode([
    'keyboard' => $keyboardRows,
    'resize_keyboard' => true,
    'is_persistent' => true,
    'one_time_keyboard' => false,
    'input_field_placeholder' => 'یک گزینه از منو انتخاب کنید'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$backuser = json_encode([
    'keyboard' => [
        [['text' => "🏠 بازگشت به منوی اصلی"]]
    ],
    'resize_keyboard' => true,
    'input_field_placeholder' => "برای بازگشت روی دکمه زیر کلیک کنید"
]);

// keyboard list panel for test 

$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = '{$userbot['agent']}' OR agent = 'all')");
$stmt->execute();
$list_marzban_panel_usertest = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true))) continue;
    if (in_array($result['name_panel'], $hide_panel)) continue;
    $list_marzban_panel_usertest['inline_keyboard'][] = [
        ['text' => $result['name_panel'], 'callback_data' => "locationtest_{$result['code_panel']}"]
    ];
}
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


$keyboardadmin = json_encode([
    'keyboard' => [
        [
            ['text' => "📊 آمار ربات"]
        ],
        [
            ['text' => "💰 تنظیمات فروشگاه"],
            ['text' => "⚙️ وضعیت قابلیت ها"],
        ],
        [
            ['text' => "🔍 جستجو کاربر"],
            ['text' => "👨‍🔧  مدیریت ادمین ها"]
        ],
        [
            ['text' => "📝 تنظیم متون"]
        ],
        [
            ['text' => "📞 تنظیم نام کاربری پشتیبانی"],
            ['text' => "📬 گزارش ربات"],
        ],
        [
            ['text' => "📣 جوین اجباری"]
        ],
        [
            ['text' => "🏠 بازگشت به منوی اصلی"]
        ],
    ],
    'resize_keyboard' =>  true
]);

$keyboardprice = json_encode([
    'keyboard' => [
        [
            ['text' => "🔋 قیمت حجم"],
            ['text' => "⌛️ قیمت زمان"],
        ],
        [
            ['text' => "💰 تنظیم قیمت محصول"],
            ['text' => "✏️ تنظیم نام محصول"],
        ],
        [
            ['text' => "بازگشت به منوی ادمین"]
        ],
    ],
    'resize_keyboard' =>  true
]);

$keyboard_change_price = json_encode([
    'keyboard' => [
        [
            ['text' => "💎 متن کارت"],
            ['text' => "💳 شماره کارت"]
        ],
        [
            ['text' => "👤 نام صاحب کارت"],
            ['text' => "🛍 دکمه خرید"]
        ],
        [
            ['text' => "🔑 دکمه تست"],
            ['text' => "🛒 دکمه سرویس های من"]
        ],
        [
            ['text' => "👤 دکمه حساب کاربری"],
            ['text' => "☎️ متن دکمه پشتیبانی"]
        ],
        [
            ['text' => "💸 متن مرحله افزایش موجودی"]
        ],
        [
            ['text' => "بازگشت به منوی ادمین"]
        ]
    ],
    'resize_keyboard' =>  true
]);

$backadmin = json_encode([
    'keyboard' => [
        [
            ['text' => "بازگشت به منوی ادمین"]
        ],
    ],
    'resize_keyboard' =>  true
]);

//------------------  [ listpanelusers ]----------------//
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = '{$userbot['agent']}' OR agent = 'all')");
$stmt->execute();
$list_marzban_panel_users = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true))) continue;
    if (in_array($result['name_panel'], $hide_panel)) continue;
    $list_marzban_panel_users['inline_keyboard'][] = [
        ['text' => $result['name_panel'], 'callback_data' => "location_{$result['code_panel']}"]
    ];
}
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"],
];
$list_marzban_panel_user = json_encode($list_marzban_panel_users);

$payment = json_encode([
    'inline_keyboard' => [
        [['text' => "💳 انتخاب روش پرداخت", 'callback_data' => "choosepaymentmethod", 'style' => 'primary']],
        [['text' => "🏠 بازگشت به منوی اصلی",  'callback_data' => "backuser", 'style' => 'danger']]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$KeyboardBalance = json_encode([
    'inline_keyboard' => [
        [['text' => "💸 افزایش موجودی", 'callback_data' => "AddBalance"]],
        [['text' => "🏠 بازگشت به منوی اصلی",  'callback_data' => "backuser"]]
    ]
]);

function KeyboardProduct($location, $query, $pricediscount, $datakeyboard, $statuscustom = false, $backuser = "backuser", $valuetow = null, $customvolume = "customsellvolume")
{
    global $pdo, $textbotlang;
    $product = ['inline_keyboard' => []];
    $statusshowprice = select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value'];
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $valuetow = $valuetow != null ? "-$valuetow" : "";
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productlist = json_decode(file_get_contents('product.json'), true);
        $productlist_name = json_decode(file_get_contents('product_name.json'), true);
        if (isset($productlist[$result['code_product']])) $result['price_product'] = $productlist[$result['code_product']];
        $result['name_product'] = empty($productlist_name[$result['code_product']]) ? $result['name_product'] : $productlist_name[$result['code_product']];
        $hide_panel = json_decode($result['hide_panel'], true);
        if (!is_array($hide_panel)) {
            if ($hide_panel === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log(sprintf('Invalid hide_panel JSON for product #%s: %s', $result['id'] ?? 'unknown', json_last_error_msg()));
            }
            $hide_panel = [];
        }
        if (intval($pricediscount) != 0) {
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        $namekeyboard = $result['name_product'] . " - " . number_format($result['price_product']) . "تومان";
        if ($statusshowprice == "onshowprice") $result['name_product'] = $namekeyboard;

        // Display each service as a three-column row: volume, duration, price.
        // All three cells intentionally use the same callback so tapping any cell
        // selects the original product and preserves the existing purchase flow.
        $productCallback = "{$datakeyboard}{$result['code_product']}{$valuetow}";
        $volumeLabel = intval($result['Volume_constraint']) === 0
            ? 'نامحدود'
            : rtrim(rtrim(number_format((float)$result['Volume_constraint'], 2, '.', ''), '0'), '.') . 'GB';
        $timeLabel = intval($result['Service_time']) === 0
            ? 'نامحدود'
            : number_format((float)$result['Service_time']) . ' روز';
        $priceLabel = number_format((float)$result['price_product']) . ' تومان';
        $product['inline_keyboard'][] = [
            ['text' => $volumeLabel, 'callback_data' => $productCallback, 'style' => 'primary'],
            ['text' => $timeLabel, 'callback_data' => $productCallback, 'style' => 'primary'],
            ['text' => $priceLabel, 'callback_data' => $productCallback, 'style' => 'primary'],
        ];
    }
    if ($statuscustom) $product['inline_keyboard'][] = [['text' => $textbotlang['users']['customsellvolume']['title'], 'callback_data' => $customvolume, 'style' => 'success']];
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => $backuser, 'style' => 'danger'],
    ];
    return json_encode($product);
}
function KeyboardCategory($location, $agent, $backuser = "backuser")
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmts = $pdo->prepare(
            "SELECT * FROM product 
             WHERE (Location = :location OR Location = '/all') 
             AND category = :category
             AND (agent = :agent OR agent = 'all')"
        );
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
        $stmts->bindParam(':agent', $agent, PDO::PARAM_STR);
        $stmts->execute();
        if ($stmts->rowCount() == 0) continue;
        $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorynames_" . $row['id'], 'style' => 'primary']];
    }
    $list_category['inline_keyboard'][] = [
        ['text' => "▶️ بازگشت به منوی قبل", "callback_data" => $backuser],
    ];
    return json_encode($list_category);
}
