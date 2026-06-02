<?php
ob_start();
$token = "7494548602:AAFE8A7XPQaAc73Lp3cB3ldwNWhc9ofyAs4";
define("API_KEY", $token);
$admin = "7012982464";
$bot = [];
$admins = $bot['admins'];
$domin = $_SERVER['HTTP_HOST'];

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

function callAPI($action, $channel_id, $user_id = null, $number = 1) {
    
    if ($action === 'check' && $user_id !== null) {
        $response = bot("getChatMember", [
            "chat_id" => $channel_id,
            "user_id" => $user_id
        ]);
        
        if ($response->ok) {
            $status = $response->result->status;
            $subscribed = in_array($status, ['creator', 'administrator', 'member']);
            return ['subscribed' => $subscribed, 'status' => $status];
        }
        return ['subscribed' => false, 'error' => 'فشل في التحقق'];
    }
    
    if ($action === 'link') {
        // إنشاء رابط دعوة للقناة
        try {
            $response = bot("createChatInviteLink", [
                "chat_id" => $channel_id,
                "member_limit" => $number,
                "creates_join_request" => false
            ]);
            
            if ($response->ok && isset($response->result->invite_link)) {
                return ['link' => $response->result->invite_link];
            }
        } catch (Exception $e) {
            // خطة احتياطية: رابط مباشر إذا كان للقناة معرف
            $chat = bot("getChat", ["chat_id" => $channel_id]);
            if ($chat->ok && isset($chat->result->username)) {
                return ['link' => "https://t.me/" . $chat->result->username];
            }
        }
        return ['link' => null, 'error' => 'فشل في إنشاء الرابط'];
    }
    
    return ['error' => 'إجراء غير معروف'];
}

function send_message($message, $from_id, $tk) {
    $url = "https://api.telegram.org/bot" . $tk . "/sendMessage?chat_id=" . $from_id;
    $url .= "&text=" . urlencode($message);
    $url .= "&parse_mode=markdown"; 
    file_get_contents($url);
}

function jexo2() {
    global $chat_id, $message_id, $folder, $upload, $check;
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
⎋ اهلا بك في الاعدادات الخاصه ببوت الرفع
⚙️ — — — — — — — — — — — ⚙️
",
        'parse_mode'=>"MARKDOWN",
        'reply_markup'=>json_encode([ 
            'inline_keyboard'=>[
                [['text'=>"فحص الملفات " . $check,'callback_data'=>"check"]],
                [['text'=>"رفع الملفات " . $upload,'callback_data'=>"upload"]],
                [['text'=>"انشاء فولدرات " . $folder,'callback_data'=>"folder"]],
                [['text'=>'• المحظورين من الرفع  • ','callback_data'=>"banall" ]],
                [['text'=>'عدد ملفات' ,'callback_data'=>"numberfiles"],
                ['text'=>'عدد تحذيرات' ,'callback_data'=>"numberban"]],
                [['text'=>'• الاعدادات العامه •' ,'callback_data'=>"bot"]]
            ]
        ])
    ]);
}

function advancedFileCheck($file_content, $file_name) {
    global $from_id, $admins;
    
    if (in_array($from_id, $admins)) {
        return "safe";
    }
    
    $blocked_extensions = [
        'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 
        'htaccess', 'htpasswd'
    ];
    
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (in_array($extension, $blocked_extensions)) {
        return "امتداد الملف غير مسموح به";
    }
    
    $custom_blocked_words = [
        'hacker',
        'exploit', 
        'backdoor',
        'malware',
        'virus',
        'هكر',
        'هاكر',
        'اختراق',
        'اخترق', 
        'سحب ملفات',
        'اختراق الاستضافه',
        'hakr',
        'hekr',
        'hack', 
        'Penetrated',
        'Pulling files',
        'دمار',
        'تهكير',
        'shell_exec',
        'system_exec',
        'base64_decode',
        'eval(',
        'hacr',
        'hecr',
        'heck', 
        'اختتراق',
        'تدمير',
        'استضافه',
        'بوت اختراق',
        'إختراق',
        'estdafa',
        'hackerr',
        'filehack',
    ];
    
    foreach ($custom_blocked_words as $word) {
        if (stripos($file_content, $word) !== false) {
            return "تم اكتشاف خطر ممنوع في الملف الخاص بك";
        }
    }

    $url_pattern = '/https?:\/\/[^\s"\'<>]+/i';
    preg_match_all($url_pattern, $file_content, $matches);
    
    if (!empty($matches[0])) {
        $allowed_domains = [
            'api.telegram.org',
            'telegram.org',
            't.me'
        ];
        
        foreach ($matches[0] as $url) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';
            
            $is_allowed = false;
            
            if (strpos($url, 'api.telegram.org/bot') !== false) {
                $is_allowed = true;
            }
            
            foreach ($allowed_domains as $domain) {
                if (strpos($host, $domain) !== false) {
                    $is_allowed = true;
                    break;
                }
            }
            
            if (!$is_allowed) {
                return "الملف يحتوي على روابط غير مسموح بها: " . htmlspecialchars($url);
            }
        }
    }
    return "safe";
}

function canUploadFile($user_id) {
    global $jexo, $admin, $bot;
    
    if ($user_id == $admin) {
        return true;
    }
    
    if (isset($bot['admins']) && in_array($user_id, $bot['admins'])) {
        return true;
    }
    
    $free_files_limit = $bot['free_files'] ?? 2; 
if (isset($jexo['users'][$user_id]['free_uploads']) && $jexo['users'][$user_id]['free_uploads'] > 0) {
    return true;
}
    
    if (isset($jexo['users'][$user_id]['subscription']) && $jexo['users'][$user_id]['subscription'] !== null) {
        $sub = $jexo['users'][$user_id]['subscription'];
        if (time() < $sub['expiry']) {
            return true;
        } else {
    $jexo['users'][$user_id]['subscription'] = null;
    file_put_contents('jexo.json', json_encode($jexo)); 
        }
    }
    
    return false;
}

function checkConditions($f) {
    global $from_id, $admin, $admins;
    
    if (in_array($from_id, $admins)) {
        return false;
    }

    $output = false;

    if ($from_id != $admin) {
        $conditions = [
            "/H3K/",
            "/public function create/",
            '/(.*)ZipArchive(.*)/i',
            '/(.*)zip(.*)/i',
            '/(.*)eval(.*)/i',
            '/(.*)base64_decode(.*)/i',
            '/(.*)Hack Tool Hosting(.*)/i',
            '/(.*)\.htaccess(.*)/i',
            '/(.*)exec(.*)/i',
            '/(.*)system(.*)/i',
            '/(.*)shell_exec(.*)/i',
            '/(.*)passthru(.*)/i',
            '/(.*)proc_open(.*)/i',
            '/(.*)popen(.*)/i',
            '/(.*)curl_exec(.*)/i',
            '/(.*)curl_multi_exec(.*)/i',
            '/(.*)gzinflate(.*)/i',
            '/(.*)gzuncompress(.*)/i',
            '/(.*)str_rot13(.*)/i',
        ];

        $custom_blocked_words = [
    'hacker',
    'exploit', 
    'backdoor',
    'malware',
    'virus',
    'هكر',
    'هاكر',
    'اختراق',
    'اخترق', 
    'سحب ملفات',
    'اختراق الاستضافه',
    'hakr',
    'hekr',
    'hack', 
    'Penetrated',
    'Pulling files',
    'دمار',
    'تهكير',
    'shell_exec',
    'system_exec',
    'base64_decode',
    'eval(',
    'hacr',
    'hecr',
    'heck', 
    'اختتراق',
    'تدمير',
    'استضافه',
    'بوت اختراق',
    'إختراق',
    'estdafa',
    'hackerr',
    'filehack',
    ];
        
        foreach ($custom_blocked_words as $word) {
            $conditions[] = '/(.*)' . preg_quote($word, '/') . '(.*)/i';
        }

        $matches = [];
        $dangerousCount = 0;
        
        foreach ($conditions as $pattern) {
            if (preg_match($pattern, $f, $matches)) {
                $dangerousCount++;
            }
        }

        if ($dangerousCount > 3) {
            $output = true;
        }
        
        if (preg_match('/base64_decode\(["\']([^"\']+)["\']\)/', $f, $base64Matches)) {
            $decoded = base64_decode($base64Matches[1]);
            if ($decoded && preg_match('/(eval|system|exec|shell_exec)/i', $decoded)) {
                $output = true;
            }
        }
    }
    return $output;
}

date_default_timezone_set('Africa/Cairo');
$bloktime = date('h:i');
$period = date('a');
if ($period == 'am') {
    $period = 'صبـاحًا';
} else {
    $period = 'مسـاءًا';
}
$bloktime .= ' ' . $period;
$update = json_decode(file_get_contents('php://input'));
$bot_id = bot("getme")->result->id;
$bot_user = bot("getme")->result->username;
$bot_name = bot("getme")->result->first_name;
$message = $update->message ?? null;
$callback_query = $update->callback_query ?? null;
$message_id = $message->message_id ?? $callback_query->message->message_id ?? null;
$username = $message->from->username ?? $callback_query->from->username ?? null;
$chat_id = $message->chat->id ?? $callback_query->message->chat->id ?? null;
$title = $message->chat->title ?? $callback_query->message->chat->title ?? null;
$text = $message->text ?? $callback_query->message->text ?? null;
$photo = $message->photo ?? null;
$voice = $message->voice ?? null;
$audio = $message->audio ?? null;
$video = $message->video ?? null;
$document = $message->document ?? null;
$sticker = $message->sticker ?? null;
$caption = $message->caption ?? null;
$name = $message->from->first_name ?? 
$callback_query->from->first_name ?? null;
$from_id = $message->from->id ?? 
$callback_query->from->id ?? null;
$type = $message->chat->type ?? null;
$reply = $message->reply_to_message ?? null;
$reply_message_id = $reply->message_id ?? null;
$rep_for = $reply->forward_from->id ?? null;
$document_file_id = $document->file_id ?? null;
$document_file_name = $document->file_name ?? null;
$data = $callback_query->data ?? null;
$message_id = $update->message->message_id ?? $message_id = $update->callback_query->message->message_id ?? null ;
$bot = file_exists('bot.json') ? json_decode(file_get_contents('bot.json'), true) : [];
$bot['banned'] = $bot['banned'] ?? []; 
if ($from_id && in_array($from_id, $bot['banned'])) {
    if ($text == "/start" || $data == "back2") {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🚫 *تم حظرك من البوت*\n\n• السبب : تخطي عدد التحذيرات المسموح بها\n• يمكنك التواصل مع المطور @vvtlv لإلغاء الحظر",
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[['text' => 'تواصل مع المطور', 'url' => 'tg://user?id=' . $admin]]]
            ])
        ]);
        exit;
    }
    
    if ($message || $data) {
        if ($data) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "🚫 تم حظرك من البوت",
                'show_alert' => true
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🚫 تم حظرك من استخدام البوت. راسل المطور @vvtlv",
                'parse_mode' => 'markdown'
            ]);
        }
        exit;
    }
}

$jexo = file_exists('jexo.json') ? json_decode(file_get_contents('jexo.json'), true) : [];

if(!isset($jexo['users'])) {
    $jexo['users'] = [];
    file_put_contents('jexo.json', json_encode($jexo));
}

if(!isset($jexo['invites'])) {
    $jexo['invites'] = [];
    file_put_contents('jexo.json', json_encode($jexo));
}

if(!isset($jexo['users'][$from_id])) {
    $jexo['users'][$from_id] = [
        'free_uploads' => 2,
        'points' => 0,
        'subscription' => null
    ];
    file_put_contents('jexo.json', json_encode($jexo));
}

$eshterak_file = 'eshterak.json';
if (file_exists($eshterak_file)) {
    $eshterak = json_decode(file_get_contents($eshterak_file), true);
} else {
    $eshterak = [];
    file_put_contents($eshterak_file, json_encode($eshterak, JSON_PRETTY_PRINT));
}

if(!isset($bot['subscription_prices'])) {
    $bot['subscription_prices'] = [
        'daily' => 3,
        'weekly' => 10,
        'monthly' => 25,
        'yearly' => 100
    ];
    file_put_contents('bot.json', json_encode($bot));
}

if(!isset($bot['free_files'])) {
    $bot['free_files'] = 2;
    file_put_contents('bot.json', json_encode($bot));
}

if(!isset($bot['invite_points'])) {
    $bot['invite_points'] = 1;
    file_put_contents('bot.json', json_encode($bot));
}

function s() {
    global $jexo, $bot, $eshterak;
    file_put_contents('jexo.json', json_encode($jexo));
    file_put_contents('bot.json', json_encode($bot));
    file_put_contents('eshterak.json', json_encode($eshterak, JSON_PRETTY_PRINT));
}

if(!isset($bot['tak'])){
    $bot['tak'] = "on";
    s();
}
if(!isset($bot['all_file'])) {
    $bot['all_file'] = 0;
    s();
}
if(!isset($bot['tawgeh'])){
    $bot['tawgeh'] = "on";
    s();
}
if(!isset($bot['bott'])){
    $bot['bott'] = "on";
    s();
}
if(!isset($bot['premium'])){
    $bot['premium'] = "on";
    s();
}
if(!isset($bot['VIP_button'])){
    $bot['VIP_button'] = "on";
    s();
}

if(!isset($bot['check'])){
    $bot['check'] = "on";
    s();
}
if(!isset($bot['upload'])){
    $bot['upload'] = "on";
    s();
}
if(!isset($bot['folder'])){
    $bot['folder'] = "on";
    s();
}
if(!isset($bot['from_folder'])){
    mkdir("all");
    mkdir("all/$chat_id");
    s();
}

if (!file_exists("all")) {
    mkdir("all");
}

if (!file_exists("all/$chat_id")) {
    mkdir("all/$chat_id");
}

if (!file_exists("all/$chat_id")) {
    mkdir("all/$chat_id");
}

$folder_id = $bot['from_folder'];

$VIP_button = $bot['VIP_button'] === "on" ? "✅" : "❌";
if ($data == 'VIP_button') {
    $bot['VIP_button'] = $bot['VIP_button'] === "on" ? "off" : "on";
    $bott = $bot['VIP_button'] === "on" ? "يعمل ✅" : "معطل ❌";
    s();
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['VIP_button'] === "on" ? "تفعيل" : "تعطيل") . " زر التقديم على طلب اشتراك"
    ]);
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)\nمرحبا بك في قسم إدارة الـ VIP",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => " زر التقديم على طلب اشتراك " . $VIP_button, 'callback_data' => "VIP_button"]],
                [['text'=>"• إضافة VIP •",'callback_data'=>"addvip"],['text'=>"• حذف VIP •",'callback_data'=>"removevip"]],
                [['text' => "• عرض جميع الـ VIP •", 'callback_data' => "viewvips"]],
                [['text' => "• حذف جميع الـ VIP •", 'callback_data' => "clearvips"]],
                [['text' => "• رجوع •", 'callback_data' => "bot"]]
            ]
        ])
    ]);
}

$premium = $bot['premium'] === "on" ? "✅" : "❌";
$bott = $bot['bott'] === "on" ? "✅" : "❌";
$tawgeh = $bot['tawgeh'] === "on" ? "✅" : "❌";
$tak = $bot['tak'] === "on" ? "✅" : "❌";
if ($data == 'premium') {
    $bot['premium'] = $bot['premium'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => jexo()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['stabilizing'] === "on" ? "تفعيل" : "تعطيل") . " التثبيت."
    ]);
} elseif ($data == 'bott') {
    $bot['bott'] = $bot['bott'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => jexo()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['directing'] === "on" ? "تفعيل" : "تعطيل") . " التوجيه."
    ]);
} elseif ($data == 'tawgeh') {
    $bot['tawgeh'] = $bot['tawgeh'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => jexo()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['radio_p'] === "on" ? "تفعيل" : "تعطيل") . " الاذاعة في الخاص."
    ]);
} elseif ($data == 'tak') {
    $bot['tak'] = $bot['tak'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => jexo()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['radio_g'] === "on" ? "تفعيل" : "تعطيل") . " الاذاعة في الجروبات."
    ]);
}
function jexo() {
    global $bot;
    $premium = $bot['premium'] === "on" ? "✅" : "❌";
    $bott = $bot['bott'] === "on" ? "✅" : "❌";
    $tawgeh = $bot['tawgeh'] === "on" ? "✅" : "❌";
    $tak = $bot['tak'] === "on" ? "✅" : "❌";
    $radio_ch = $bot['radio_ch'] === "on" ? "✅" : "❌";
        $check_files_button = $bot['check_files_admin'] === "on" ? "✅" : "❌";

    return json_encode([
        'inline_keyboard' => [
            [['text' => 'تنبيه دخول الاعضاء  ' . $tak, 'callback_data' => "tak"]],
            [['text'=> 'توجيه الرسائل  ' . $tawgeh, 'callback_data'=>"tawgeh"]], 
            [['text'=> 'وضع البوت  ' . $bott, 'callback_data'=>"bott" ]], 
            [['text'=> ' الوضع المدفوع  ' . $premium, 'callback_data'=>"premium"]], 
            [['text'=>'• قسم الحظر •' ,'callback_data'=>"ksmblock"], 
            ['text'=>'• قسم الادمنيه •' ,'callback_data'=>"ksmadmin"]], 
            [['text' => "• قسم الاذاعه •", 'callback_data' => "msg"]], 
            [['text'=>'• قسم الاشتراك الاجباري •' ,'callback_data'=>"eshterak"]],
            [['text'=>'• تحقق من الملفات •' ,'callback_data'=>"check_uploads"]],
            [['text' => "• قسم باقات الـ VIP •", 'callback_data' => "jexobots"]], 
            [['text'=>'• قسم الاشتراك الـ ( VIP ) •' ,'callback_data'=>"ksmvip"],
            ['text' => "• اشتراكات مدفوعة •", 'callback_data' => "vip_menu"]],
            [['text'=>'• احصائيات البوت •' ,'callback_data'=>"statistics"]], 
            [['text'=>'• اعدادات بوت الرفع•' ,'callback_data'=>"jexo"]]
        ]
    ]);
}

$bot['admins'] = $bot['admins'] ?? [];
if (!in_array($admin, $bot['admins'])) {
    $bot['admins'][] = $admin;
    s();
}
$admins = $bot['admins'];
if (($text == '/start' or $data == 'bot') and in_array($from_id, $admins)) {
    if ($data) {
        $m = 'EditMessageText';
    } else {
        $m = 'sendMessage';
    }
    $getUpdatedMarkup =  jexo();
    bot($m, [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "اهلا بك عزيزي المطور
اليك لوحة الصانع
⚙️ — — — — — — — — ⚙️

[قناة السورس](https://t.me/S7_MX3)
",
        'parse_mode' => "markdown",
        'disable_web_page_preview' => true,
        'reply_markup' => $getUpdatedMarkup
    ]);
    $jexo['mode'][$from_id]['mode'] = null;
    s();
}

if ($message && $from_id != $admin && $bot['tawgeh'] == "on" && $type == "private") {
    $pp = bot('forwardMessage', [
        'chat_id' => $admin,
        'from_chat_id' => $from_id,
        'message_id' => $message_id
    ]);

    $message_id_to = $pp->result->message_id;
    $jexo["twasol"][$message_id_to] = $from_id;
    s();
}

if ($data == "vip_menu") {
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "📦 قائمة الاشتراكات المدفوعة:",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "➕ إضافة اشتراك", 'callback_data' => "add_vip"]],
                [['text' => "➖ حذف اشتراك", 'callback_data' => "del_vip"]],
                [['text' => "📋 عرض الاشتراكات", 'callback_data' => "list_vip"]],
                [['text' => "🔙 رجوع", 'callback_data' => "bot"]],
            ]
        ])
    ]);
    exit;
}

if ($message && $from_id == $admin && $reply && $text != "ايدي" && in_array($reply->message_id, array_keys($jexo["twasol"]))) {
    $reply_chat_id = $jexo["twasol"][$reply->message_id];

    if ($text) {
        bot('sendMessage', [
            'chat_id' => $reply_chat_id,
            'text' => "وصلتك رسالة جديده من الدعم \n" . $text,
            'parse_mode' => "markdown",
            'protect_content' => true,
        ]);
    } elseif ($photo) {
        bot('sendPhoto', [
            'chat_id' => $reply_chat_id,
            'photo' => $photo[0]->file_id,
            'caption' => "وصلتك رسالة جديده من الدعم \n" . $caption,
        ]);
    } elseif ($voice) {
        bot('sendVoice', [
            'chat_id' => $reply_chat_id,
            'voice' => $voice->file_id,
            'caption' => "وصلتك رسالة جديده من الدعم \n" . $caption,
        ]);
    } elseif ($audio) {
        bot('sendAudio', [
            'chat_id' => $reply_chat_id,
            'audio' => $audio->file_id,
            'caption' => "وصلتك رسالة جديده من الدعم \n" . $caption,
        ]);
    } elseif ($video) {
        bot('sendVideo', [
            'chat_id' => $reply_chat_id,
            'video' => $video->file_id,
            'caption' => "وصلتك رسالة جديده من الدعم \n" . $caption,
        ]);
    } elseif ($document) {
        bot('sendDocument', [
            'chat_id' => $reply_chat_id,
            'document' => $document->file_id,
            'caption' => "وصلتك رسالة جديده من الدعم \n" . $caption,
        ]);
    } elseif ($sticker) {
        bot('sendSticker', [
            'chat_id' => $reply_chat_id,
            'sticker' => $sticker->file_id,
        ]);
    }
    exit;
} elseif ($reply && $from_id == $admin && $text == "ايدي"){
    $names = "";
    $reply_from_id = $jexo["twasol"][$reply->message_id] ?? "ايدي غير مسجل";
    $user_info = bot('getChatMember', ['chat_id' => $reply_from_id, 'user_id' => $reply_from_id])->result;
    if ($user_info) {
        $username = $user_info->user->username ?? '';
        $name = $user_info->user->first_name ?? '';
        $names .= "*User ID :* [$reply_from_id](tg://openmessage?user_id=$reply_from_id)\n";
        $names .= "*Username :*[ @$username ]\n";
        $names .= "*Name :* [$name](tg://user?id=$reply_from_id)\n";
    } else {
        $names .= "*User ID :* $reply_from_id\n";
        $names .= "*Error:* User not found\n\n";
    }
    
    bot("sendMessage", [
        "chat_id" => $chat_id, 
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464):\n$names",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "bot"]],
            ]
        ])
    ]);
}

$statsFile = 'statistics.json';
$stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [
    "users" => [],
    "groups" => [],
    "stats" => [
        "total_users" => 0,
        "total_groups" => 0,
        "today" => ["date" => date('Y-m-d'), "users" => 0, "groups" => 0],
        "yesterday" => ["date" => date('Y-m-d', strtotime("-1 day")), "users" => 0, "groups" => 0],
        "new_today" => 0,
        "new_groups_today" => 0,
    ],
];

if ($stats['stats']['today']['date'] != date('Y-m-d')) {
    $stats['stats']['yesterday'] = $stats['stats']['today'];
    $stats['stats']['today'] = ["date" => date('Y-m-d'), "users" => 0, "groups" => 0];
    $stats['stats']['new_today'] = 0;
    $stats['stats']['new_groups_today'] = 0;
}

function notifyAdmin($text) {
    global $admin;
    bot('sendmessage', ['chat_id' => $admin, 'text' => $text, "parse_mode" => "markdown"]);
}

if ($type == "private" && !in_array($from_id, $stats['users'])) {
    $stats['users'][] = $from_id;
    $stats['stats']['total_users']++;
    $stats['stats']['today']['users']++;
    $stats['stats']['new_today']++;
    bot('sendmessage', ['chat_id' => $admin, 'text' => "*🆕 مستخدم جديد دخل البوت*\n\n- الاسم : [$name](tg://user?id=$from_id)\n- المعرف : [@" . ($user ?? "غير متوفر") . "]\n- الايدي : `$from_id`", "parse_mode" => "markdown"]);
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
}

if (($type == "group" || $type == "supergroup") && !in_array($chat_id, $stats['groups'])) {
    $stats['groups'][] = $chat_id;
    $stats['stats']['total_groups']++;
    $stats['stats']['today']['groups']++;
    $stats['stats']['new_groups_today']++;
    bot('sendmessage', ['chat_id' => $admin, 'text' => "*🆕 تم اضافة البوت الى جروب جديد*\n\n- الاسم : $chat_title\n- الايدي : `$chat_id`", "parse_mode" => "markdown"]);
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
}

if ($data == "statistics") {
    $todayDate = date('Y-m-d');
    $yesterdayDate = date('Y-m-d', strtotime("-1 day"));
    
    $totalUsers = $stats['stats']['total_users'];
    $totalGroups = $stats['stats']['total_groups'];
    $usersToday = $stats['stats']['today']['users'];
    $groupsToday = $stats['stats']['today']['groups'];
    
    $usersYesterday = $stats['stats']['yesterday']['users'];
    $groupsYesterday = $stats['stats']['yesterday']['groups'];
    
    $newUsersToday = $stats['stats']['new_today'];
    $newUsersYesterday = $stats['stats']['new_groups_today'];
    $newUsersThisMonth = $stats['stats']['new_today'];
    $newUsersLastMonth = $stats['stats']['new_today']; 
    $message = "مرحبًا بك في قسم الإحصائيات 📊\n\n";
    $message .= "• المستخدمون :\n\n";
    $message .= "- العدد الإجمالي للمستخدمين : $totalUsers\n";
    $message .= "- عدد المستخدمين في الخاص : $totalUsers\n"; 
    $message .= "- عدد القنوات و المجموعات : $totalGroups\n\n";  
    $message .= "• التفاعل :\n\n";
    $message .= "- اليوم ($todayDate) :\n";
    $message .= "- المستخدمون : $usersToday\n";
    $message .= "- المجموعات: $groupsToday\n\n";
    $message .= "- في الأمس ($yesterdayDate):\n";
    $message .= "- المستخدمون : $usersYesterday\n";
    $message .= "- المجموعات: $groupsYesterday\n\n";
    $message .= "- عدد المستخدمين الجدد اليوم : $newUsersToday\n";
    $message .= "- عدد المستخدمين الجدد بالأمس : $newUsersYesterday\n";
    $message .= "- عدد المستخدمين الجدد هذا الشهر : $newUsersThisMonth\n";
    $message .= "- عدد المستخدمين الجدد في الشهر الماضي : $newUsersLastMonth\n\n";

    $recentUsers = array_slice($stats['users'], -5); 
    $message .= "- قائمة آخر الأعضاء الذين اشتركوا :\n";
    foreach ($recentUsers as $userId) {
        $message .= "$userId\n";
    }
     
    bot('EditMessageText', [
        'chat_id' => $chat_id, 
        'message_id' => $message_id, 
        'text' => $message, 
        'parse_mode' => "html", 
        "reply_markup" => json_encode([
            "inline_keyboard" => [[
                ["text" => "• رجوع •", "callback_data" => "bot"]
            ]]
        ])
    ]);
}

if ($data == "check_uploads") {
    $is_admin = in_array($from_id, $admins);
    if ($is_admin) {
        $base_folder = "all";
        $scan_message = "جميع مجلدات المستخدمين";
    } else {
        $base_folder = "all/$from_id/bots";
        $scan_message = "مجلد bots الخاص بك";
    }
    
    $suspicious_files = [];
    $all_folders_scanned = [];
    
    function scanFolderRecursive($folder_path, $base_url) {
        $files_found = [];
        
        if (!is_dir($folder_path)) {
            return $files_found;
        }
        
        $items = scandir($folder_path);
        foreach ($items as $item) {
            if ($item == "." || $item == "..") continue;
            
            $full_path = $folder_path . '/' . $item;
            
            if (is_dir($full_path)) {
                $sub_files = scanFolderRecursive($full_path, $base_url);
                $files_found = array_merge($files_found, $sub_files);
            } else {
                $file_info = checkFileUploadMethod($full_path, $item);
                
                if ($file_info['suspicious']) {
                    $relative_path = str_replace($folder_path . '/', '', $full_path);
                    $file_url = $base_url . '/' . $relative_path;
                    
                    $files_found[] = [
                        'name' => $item,
                        'path' => $full_path,
                        'folder' => $folder_path,
                        'url' => $file_url,
                        'time' => date('Y-m-d H:i:s', filemtime($full_path)),
                        'size' => formatSize(filesize($full_path)),
                        'reason' => $file_info['reason']
                    ];
                }
            }
        }
        
        return $files_found;
    }
    
    if ($is_admin) {
        $users_folders = scandir("all");
        foreach ($users_folders as $user_folder) {
            if ($user_folder == "." || $user_folder == "..") continue;
            
            $user_path = "all/$user_folder/bots";
            if (is_dir($user_path)) {
                $all_folders_scanned[] = "مستخدم : $user_folder";
                $user_files = scanFolderRecursive($user_path, "https://$_SERVER[HTTP_HOST]/" . dirname($_SERVER['SCRIPT_NAME']) . "/all/$user_folder/bots");
                $suspicious_files = array_merge($suspicious_files, $user_files);
            }
        }
    } else {
        if (is_dir($base_folder)) {
            $all_folders_scanned[] = "مجلدك الشخصي";
            $suspicious_files = scanFolderRecursive($base_folder, "https://$_SERVER[HTTP_HOST]/" . dirname($_SERVER['SCRIPT_NAME']) . "/$base_folder");
        }
    }
    
    if (!empty($suspicious_files)) {
        $message = "⚠️ *اكتشاف ملفات مشبوهة*\n\n";
        $message .= "📁 *المجلدات المفحوصة :* " . implode(", ", $all_folders_scanned) . "\n";
        $message .= "🔍 *عدد الملفات المشبوهة :* " . count($suspicious_files) . "\n\n";
        $message .= "📄 *قائمة الملفات :*\n";
        
        $counter = 1;
        foreach ($suspicious_files as $file) {
            $message .= "\n`{$counter}.` *{$file['name']}*\n";
            $message .= "   📍 المسار : `{$file['path']}`\n";
            $message .= "   🔗 الرابط : [اضغط هنا]({$file['url']})\n";
            $message .= "   ⏰ الوقت : {$file['time']}\n";
            $message .= "   📏 الحجم : {$file['size']}\n";
            $message .= "   ⚠️ السبب : {$file['reason']}\n";
            $counter++;
        }
        
        $message .= "\n\n🔒 *توصية:* قم بحذف هذه الملفات فوراً لأنها قد تشكل خطراً أمنياً";
        
        $inline_keyboard = [
            [['text' => "🗑️ حذف جميع الملفات المشبوهة", 'callback_data' => "delete_all_suspicious"]]
        ];
        
        if ($is_admin) {
            $inline_keyboard[] = [['text' => "📊 إحصائيات تفصيلية", 'callback_data' => "detailed_stats"]];
            $inline_keyboard[] = [['text' => "🔍 فحص مجدد", 'callback_data' => "check_uploads"]];
        }
        
        $inline_keyboard[] = [['text' => "🔙 رجوع", 'callback_data' => "bot"]];
        
    } else {
        $message = "✅ *فحص أمني ناجح*\n\n";
        $message .= "📁 *المجلدات المفحوصة :* " . implode(", ", $all_folders_scanned) . "\n";
        $message .= "🎯 *النتيجة:* لم يتم العثور على أي ملفات مشبوهة\n\n";
        $message .= "🛡️ *جميع الملفات رفعت عبر البوت بشكل آمن*";
        
        $inline_keyboard = [
            [['text' => "🔄 تحديث الفحص", 'callback_data' => "check_uploads"]],
            [['text' => "🔙 رجوع", 'callback_data' => "bot"]]
        ];
    }
    
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'parse_mode' => 'markdown',
        'disable_web_page_preview' => true,
        'reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard])
    ]);
    
    if (!empty($suspicious_files) && !$is_admin) {
        $admin_message = "⚠️ *اكتشاف ملفات مشبوهة*\n\n";
        $admin_message .= "👤 *المستخدم :* [$name](tg://user?id=$from_id)\n";
        $admin_message .= "🆔 *الايدي :* `$from_id`\n";
        $admin_message .= "📁 *المجلد :* all/$from_id/bots\n";
        $admin_message .= "📄 *عدد الملفات :* " . count($suspicious_files) . "\n\n";
        
        foreach ($suspicious_files as $file) {
            $admin_message .= "• `{$file['name']}` - {$file['reason']}\n";
            $admin_message .= "  🔗 [رابط الملف]({$file['url']})\n";
        }
        
        bot('sendMessage', [
            'chat_id' => $admin,
            'text' => $admin_message,
            'parse_mode' => 'markdown',
            'disable_web_page_preview' => true
        ]);
    }
}


function checkFileUploadMethod($file_path, $file_name) {
    global $bot, $from_id;
    
    $file_time = filemtime($file_path);
    $current_time = time();
    
    if (isset($bot['Info_from_upload'])) {
        foreach ($bot['Info_from_upload'] as $file_info) {
            if (strpos($file_info['url'], $file_name) !== false) {
                return [
                    'suspicious' => false,
                    'reason' => 'مرفوع عبر البوت'
                ];
            }
        }
    }
    
    $file_age = $current_time - $file_time;
    
    if ($file_age > (7 * 24 * 60 * 60)) {
        return [
            'suspicious' => true,
            'reason' => 'ملف قديم (أقدم من أسبوع)'
        ];
    }
    
    $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps'];
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (in_array($extension, $dangerous_extensions)) {
        return [
            'suspicious' => true,
            'reason' => 'ملف PHP غير مسجل في البوت'
        ];
    }
    
    $file_size = filesize($file_path);
    if ($file_size > 10 * 1024 * 1024) {
        return [
            'suspicious' => true,
            'reason' => 'حجم ملف كبير جداً'
        ];
    }
    
    return [
        'suspicious' => false,
        'reason' => 'ملف عادي'
    ];
}

function formatSize($bytes) {
    if ($bytes == 0) return "0 B";
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

if (strpos($data, "delete_all_suspicious") === 0) {
    $deleted_count = 0;
    $deleted_files = [];
    
    $user_folder = "all/$from_id/bots";
    
    if (is_dir($user_folder)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($user_folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $file_info = checkFileUploadMethod($item->getPathname(), $item->getFilename());
                
                if ($file_info['suspicious']) {
                    if (unlink($item->getPathname())) {
                        $deleted_count++;
                        $deleted_files[] = $item->getFilename();
                    }
                }
            }
        }
    }
    
    $message = "✅ *تم الانتهاء من التنظيف*\n\n";
    $message .= "🗑️ *عدد الملفات المحذوفة :* $deleted_count\n";
    
    if (!empty($deleted_files)) {
        $message .= "\n📄 *الملفات المحذوفة :*\n";
        foreach ($deleted_files as $file) {
            $message .= "• `$file`\n";
        }
    } else {
        $message .= "\nℹ️ *لم يتم العثور على ملفات مشبوهة للحذف*";
    }
    
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'parse_mode' => 'markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔄 فحص مجدد", 'callback_data' => "check_uploads"]],
                [['text' => "🔙 رجوع", 'callback_data' => "bot"]]
            ]
        ])
    ]);
}

if ($data == "eshterak") {
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)\nمرحبا بك في قسم الاشتراك الإجباري. اختر الإجراء المطلوب:",
        "parse_mode" => "markdown",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "+ اضف قناة +", "callback_data" => "esh"], ["text" => "- حذف قناة -", "callback_data" => "unesh"]],
                [["text" => "👁 عرض قنوات الاشتراك الإجباري", "callback_data" => "eshh"]],
                [["text" => "❗ حذف جميع القنوات", "callback_data" => "uneshh"]],
                [['text' => "• رجوع •", 'callback_data' => "bot"]]
            ]
        ])
    ]);
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($data == "esh") {
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => "👤 أرسل معرف القناة (@channel) ، أيدي القناة",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "• إلغاء •", "callback_data" => "eshterak"]],
            ]
        ])
    ]);
    $jexo['mode'][$from_id]['mode'] = "esh_step1";
    s();
    exit;
}

if ($message && $jexo['mode'][$from_id]['mode'] == "esh_step1") {
    $channel_id = null;

    if (strpos($text, "@") === 0) {
        $channel_info = bot("getChat", ["chat_id" => $text]);
        if ($channel_info->ok) {
            $channel_id = $channel_info->result->id;
        }
    } elseif (is_numeric($text)) {
        $channel_id = "-100" . $text;
    } elseif ($message->forward_from_chat) {
        $channel_id = $message->forward_from_chat->id;
    }

    if ($channel_id) {
        // التحقق من الصلاحيات
        $chat_member = bot("getChatMember", ["chat_id" => $channel_id, "user_id" => $bot_id]);
         if (!$chat_member->ok || strpos($chat_member->result->status, "administrator") === false) {
        bot("sendmessage", [
          "chat_id" => $chat_id,
           "text" => "⚠️ البوت لا يمتلك صلاحيات كافية لإدارة هذه القناة. تأكد من تعيينه كمدير.",
           ]);
             exit;
         }

        $jexo['bot']['temp_channel_id'] = $channel_id;
        $channel_name = bot("getChat", ["chat_id" => $channel_id])->result->title;
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "✅ تم التعرف على القناة : $channel_name\nالآن، أرسل عدد الاشتراكات المطلوب :",
        ]);
        $jexo['mode'][$from_id]['mode'] = "esh_step2";
        s();
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "⚠️ لم أتمكن من استخراج أيدي القناة. يرجى المحاولة مرة أخرى أو التأكد من صحة البيانات.",
        ]);
    }
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "esh_step2") {
    if (is_numeric($text) && intval($text) > 0) {
        $channel_id = $jexo['bot']['temp_channel_id'];
        $eshterak[$channel_id] = intval($text);
        s();

        $channel_name = bot("getChat", ["chat_id" => $channel_id])->result->title;
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "✅ تمت إضافة القناة ($channel_name) لقائمة الاشتراك الإجباري بعدد مطلوب : $text.",
        ]);
        $jexo['mode'][$from_id]['mode'] = null;
        unset($jexo['bot']['temp_channel_id']);
        s();
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "⚠️ يرجى إرسال عدد صحيح للاشتراكات المطلوبة.",
        ]);
    }
    exit;
}

if ($data == "unesh") {
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => "🗑️ أرسل معرف أو أيدي القناة التي تريد حذفها من قائمة الاشتراك الإجباري.",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "• رجوع •", "callback_data" => "eshterak"]],
            ]
        ])
    ]);
    $jexo['mode'][$from_id]['mode'] = "unesh";
    s();
    exit;
}

if ($message && $jexo['mode'][$from_id]['mode'] == "unesh") {
    $channel_id = null;

    if (strpos($text, "@") === 0) {
        $channel_info = bot("getChat", ["chat_id" => $text]);
        if ($channel_info->ok) {
            $channel_id = $channel_info->result->id;
        }
    } elseif (is_numeric($text)) {
        $channel_id = "-100" . $text;
    } elseif ($message->forward_from_chat) {
        $channel_id = $message->forward_from_chat->id;
    }

    if ($channel_id && isset($eshterak[$channel_id])) {
        unset($eshterak[$channel_id]);
        s();
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "✅ تم حذف القناة من قائمة الاشتراك الإجباري.",
        ]);
        $jexo['mode'][$from_id]['mode'] = null;
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "❌ القناة غير موجودة في قائمة الاشتراك الإجباري.",
        ]);
    }
    exit;
}


if ($data == "eshh") {
    if (!empty($eshterak)) {
        $eshterak_list = "📋 **قنوات الاشتراك الإجباري :**\n\n";
        foreach ($eshterak as $channel_id => $count) {
            $channel_info = bot("getChat", ["chat_id" => $channel_id]);
            $title = $channel_info->result->title ?? "غير معروف";
            $eshterak_list .= "🔹 [$title](tg://user?id=$channel_id) - العدد المطلوب : $count\n";
        }
    } else {
        $eshterak_list = "❌ لا توجد قنوات ضمن قائمة الاشتراك الإجباري.";
    }

    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => $eshterak_list,
        "parse_mode" => "Markdown",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "• رجوع •", "callback_data" => "eshterak"]],
            ]
        ])
    ]);
    exit;
}


if ($data == "uneshh") {
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => "⚠️ هل أنت متأكد أنك تريد حذف **جميع** قنوات الاشتراك الإجباري؟",
        "parse_mode" => "Markdown",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "نعم", "callback_data" => "confirm_uneshh"], ["text" => "لا", "callback_data" => "eshterak"]],
            ]
        ])
    ]);
    exit;
}

if ($data == "confirm_uneshh") {
    $eshterak = null;
    s();
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => "✅ تم حذف جميع قنوات الاشتراك الإجباري.",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "• رجوع •", "callback_data" => "eshterak"]],
            ]
        ])
    ]);
    exit;
}

if (($data || $message) && $type == "private" && !in_array($from_id, $admins)) {
    
    if (is_array($eshterak) && !empty($eshterak)) {
        $channels = $eshterak;
        $is_subscribed = true;
        $missing_channels = [];

        foreach ($channels as $channel_id => $number) {
            $response = callAPI('check', $channel_id, $from_id);
            
            if (isset($response['subscribed']) && $response['subscribed'] === false) {
                if (!isset($jexo[$channel_id]["members"])) {
                    $jexo[$channel_id]["members"] = [];
                }
                
                if (!in_array($from_id, $jexo[$channel_id]["members"])) {
                    $jexo[$channel_id]["members"][] = $from_id;
                    s();
                }

                $is_subscribed = false;
                $missing_channels[$channel_id] = $number;
            } else {
                if (isset($jexo[$channel_id]["members"]) && is_array($jexo[$channel_id]["members"])) {
                    if (in_array($from_id, $jexo[$channel_id]["members"])) {
                        $key = array_search($from_id, $jexo[$channel_id]["members"]);
                        if ($key !== false) {
                            unset($jexo[$channel_id]["members"][$key]);
                            
                            if (!isset($jexo[$channel_id]["nom"])) {
                                $jexo[$channel_id]["nom"] = 0;
                            }
                            $jexo[$channel_id]["nom"]++;
                            s();

                            $channel_info = bot("getChat", ['chat_id' => $channel_id]);
                            $channel_name = $channel_info->ok ? $channel_info->result->title : "قناة";
                            $current_count = $jexo[$channel_id]["nom"];

                            if ($current_count < $number) {
                                $msg = "تم اشتراك عضو جديد\nاسم العضو : $name\nالقناة : $channel_name\nاجمالي الاعضاء الذين اشتركوا : $current_count";
                            } else {
                                $msg = "تم اكتمال العدد المطلوب للقناة $channel_name.\nعدد الأعضاء : $current_count من $number";
                                unset($eshterak[$channel_id]);
                                if (isset($jexo[$channel_id])) {
                                    unset($jexo[$channel_id]);
                                }
                                s();
                            }

                            bot("sendMessage", [
                                "chat_id" => $admin,
                                "text" => $msg,
                                "parse_mode" => "Markdown",
                            ]);
                        }
                    }
                }
            }
        }

        if (!$is_subscribed) {
            $buttons = [];
            foreach ($missing_channels as $channel_id => $number) {
                $chat = bot("getChat", ['chat_id' => $channel_id]);
                $channel_name = $chat->ok ? $chat->result->title : "قناة";

                if (!isset($jexo[$channel_id]["link"])) {
                    $response = callAPI('link', $channel_id, null, $number);
                    if (isset($response['link'])) {
                        $link = $response['link'];
                        $jexo[$channel_id]["link"] = $link;
                        s();
                    } else {
                        bot("sendMessage", [
                            "chat_id" => $chat_id,
                            "text" => "حدث خطأ أثناء إنشاء رابط الدعوة.\nيرجى التواصل مع المطور @vvtlv.",
                            "parse_mode" => "Markdown",
                        ]);
                        exit;
                    }
                }

                $link = isset($jexo[$channel_id]["link"]) ? $jexo[$channel_id]["link"] : "#";
                $buttons[] = [['text' => "اشترك في قناة $channel_name", 'url' => $link]];
            }

            $message = "اهلا بك عليك الاشتراك في قنوات البوت اولا قبل الدخول للمتابعه و الاستمرار في البوت : ";
            $keyboard = ['inline_keyboard' => $buttons];

            if ($data) {
                bot("EditMessageText", [
                    "chat_id" => $chat_id,
                    "message_id" => $message_id,
                    "text" => $message,
                    "parse_mode" => "Markdown",
                    'reply_markup' => json_encode($keyboard)
                ]);
            } else {
                bot("sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => $message,
                    "parse_mode" => "Markdown",
                    'reply_markup' => json_encode($keyboard)
                ]);
            }
            exit;
        }
    }
}

//-------------------------- الاذاعة ------------------------------//
$stabilizing = $bot['stabilizing'] === "on" ? "✅" : "❌";
$directing = $bot['directing'] === "on" ? "✅" : "❌";
$radio_type = $bot['radio_type'] === "Manufacturer" ? "في بوت الصانع" : "في كل البوتات";
$radio_g_or_p = $bot['radio_g_or_p'] === "private" ? "الخاص" : "الجروبات";
if ($data == 'stabilizing') {
    $bot['stabilizing'] = $bot['stabilizing'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => getUpdatedMarkup()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['stabilizing'] === "on" ? "تفعيل" : "تعطيل") . " التثبيت."
    ]);
} elseif ($data == 'directing') {
    $bot['directing'] = $bot['directing'] === "on" ? "off" : "on";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => getUpdatedMarkup()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['directing'] === "on" ? "تفعيل" : "تعطيل") . " التوجيه."
    ]);
} elseif ($data == 'radio_type') {
    $bot['radio_type'] = $bot['radio_type'] === "Manufacturer" ? "all" : "Manufacturer";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => getUpdatedMarkup()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم اختيار البث الآن " . ($bot['radio_type'] === "Manufacturer" ? "في بوت الصانع" : "في كل البوتات") . "."
    ]);
} elseif ($data == 'radio_g_or_p') {
    $bot['radio_g_or_p'] = $bot['radio_g_or_p'] === "private" ? "group" : "private";
    s();
    bot('EditMessageReplyMarkup', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => getUpdatedMarkup()
    ]);
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم اختيار مكان البث في " . ($bot['radio_g_or_p'] === "private" ? "الخاص" : "الجروبات") . "."
    ]);
}
function getUpdatedMarkup() {
    global $bot;
    $stabilizing = $bot['stabilizing'] === "on" ? "✅" : "❌";
    $directing = $bot['directing'] === "on" ? "✅" : "❌";
    $radio_type = $bot['radio_type'] === "Manufacturer" ? "في بوت الصانع" : "في كل البوتات";
    $radio_g_or_p = $bot['radio_g_or_p'] === "private" ? "الخاص" : "الجروبات";

    return json_encode([
        'inline_keyboard' => [
            [['text' => " بالتثبيت " . $stabilizing, 'callback_data' => "stabilizing"]],
            [['text' => " بالتوجيه " . $directing, 'callback_data' => "directing"]],
            [['text' => "الاذاعه في  " . $radio_g_or_p, 'callback_data' => "radio_g_or_p"]],
            [['text' => "• بدء الاذاعه •", 'callback_data' => "start_radio"]],
            [['text' => "• رجوع •", 'callback_data' => "bot"]]
        ]
    ]);
}

$from_upload = isset($bot['from_php'][$from_id]) ? $bot['from_php'][$from_id] : 0;
$upload_all_bot = isset($bot['all_file']) ? $bot['all_file'] : 0;
$sf = $username ?? "غير متوفر";

if ($data == "msg") {
    $getUpdatedMarkup =  getUpdatedMarkup();
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "• مرحبا عزيزي المطور في قسم الاذاعه المتطور •",
        'parse_mode' => "markdown",
        'reply_markup' => $getUpdatedMarkup
    ]);
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($data == "start_radio") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "• أرسل الآن الكليشة ( النص أو جميع الوسائط )
• يمكنك استخدام كود جاهز في الإذاعة أو يمكنك استخدام الماركدوان" ,
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• الغاء •", 'callback_data' => "msg"]]
            ]
        ])
    ]);

    $bot['mode'][$from_id]['mode'] = "waiting_for_message";
    s();
    exit;
}
$photo=$message->photo;
$video=$message->video;
$document=$message->document;
$sticker=$message->sticker;
$voice=$message->voice;
$audio=$message->audio;
$caption = $message->caption;
if ($photo) {
$sens="sendphoto";
$file_id = $update->message->photo[1]->file_id;
} elseif ($document) {

$sens="senddocument";
$file_id = $update->message->document->file_id;
} elseif ($video) {

$sens="sendvideo";
$file_id = $update->message->video->file_id;
} elseif ($audio) {

$sens="sendaudio";
$file_id = $update->message->audio->file_id;
} elseif ($voice) {

$sens="sendvoice";
$file_id = $update->message->voice->file_id;
} elseif ($sticker) {

$sens="sendsticker";
$file_id = $update->message->sticker->file_id;
} else {
    $sens="sendmessage";
    $file_id = $text;
}
if ($message and $bot['mode'][$from_id]['mode'] == "waiting_for_message") {
    $targets = $bot['radio_g_or_p'] === "private" ? $stats['users'] : $stats['groups'];
    $stabilizing = $bot['stabilizing'] === "on" ? "on" : "off";
    $directing = $bot['directing'] === "on" ? "on" : "off";
    $filename = "broadcast_" . time() . ".php";
    $fileUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['SCRIPT_NAME']) . "/$filename";

    $messag_bb = bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "تم بدء الإذاعة",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• اضغط هنا بعد ثانيتين •", 'url' => $fileUrl]]
            ]
        ])
    ]);
    $code = generateBroadcastCode($targets, $stabilizing, $directing);
    file_put_contents($filename, $code);
    $bot['mode'][$from_id]['mode'] = null;
    s();
    shell_exec("nohup php $filename > /dev/null 2>&1 &");
}

function generateBroadcastCode($targets, $stabilizing, $directing) {
    global $name, $from_id, $message, $token, $message_id, $caption, $file_id, $sens, $chat_id, $messag_bb;

    $targets = var_export($targets, true);
    $file_id = var_export($file_id, true);
    $caption = addslashes($caption);
    $sens = addslashes($sens);
    $messag_bb = $messag_bb->result->message_id;
    return <<<PHP
<?php
define('API_KEY', '$token');

function bot(\$method, \$datas = []) {
    \$url = "https://api.telegram.org/bot" . API_KEY . "/" . \$method;
    \$ch = curl_init();
    curl_setopt(\$ch, CURLOPT_URL, \$url);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$datas);
    \$res = curl_exec(\$ch);
    curl_close(\$ch);
    return json_decode(\$res);
}
\$targets = $targets;
\$file_id = $file_id;
\$caption = "$caption";
\$sens = "$sens";
\$stabilizing = "$stabilizing";
\$directing = "$directing";
\$count = count(\$targets);
\$blocked = 0;
\$failed = 0;
\$succeeded = 0;
foreach (\$targets as \$target) {
    try {
        \$response = null;

        if (\$directing == 'on') {
            \$response = bot('forwardMessage', [
                'chat_id' => \$target,
                'from_chat_id' => $from_id,
                'message_id' => $message_id
            ]);
        } else {
            \$payload = ["chat_id" => \$target];
            if (\$sens !== 'sendmessage') {
                \$ss = str_replace("send", "", \$sens);
                \$payload[\$ss] = \$file_id;
                if (\$caption) {
                    \$payload['caption'] = \$caption;
                }
            } else {
                \$payload['text'] = \$file_id;
                \$payload['parse_mode'] = 'Markdown';
            }
            \$response = bot(\$sens, \$payload);
        }

        if (!\$response || !\$response->ok) {
            if (isset(\$response->error_code) && \$response->error_code == 403) {
                \$blocked++;
            } else {
                \$failed++;
            }
        } else {
            \$succeeded++;
        }

        // حساب النسبة المئوية
        \$percentage = round((\$succeeded / \$count) * 100, 2);
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $messag_bb,
            'text' => "تم بدء الإذاعة\n
• جاري الإذاعة إلى {\$count} مستخدم 🌐\n
• تم الإرسال إلى {\$succeeded} مستخدم 🎯\n
• المستخدمين الذين حظروا البوت: {\$blocked} 🚫\n
• النسبة المئوية : {\$percentage}%"
        ]);
    } catch (Exception \$e) {
        error_log("Error broadcasting to \$target: " . \$e->getMessage());
    }
}
bot('editMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $messag_bb,
    'text' => "<s>تم بدء الإذاعة</s>\n تم الانتهاء من الاذاعة\n
• جاري الإذاعة إلى {\$count} مستخدم 🌐\n
• تم الإرسال إلى {\$succeeded} مستخدم 🎯\n
• المستخدمين الذين حظروا البوت: {\$blocked} 🚫\n
• النسبة المئوية: {\$percentage}%",
    'parse_mode' => 'HTML'
]);
// الرسالة النهائية
bot('sendMessage', [
    'chat_id' => $from_id,
    'text' => "• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {" . \$succeeded . "} عضو حقيقي
• الاعضاء الذين قامو بحظر البوت {" . \$blocked . "}

• المستخدمين الذين لم يستطع البوت ارسال اذاعه لهم {" . \$failed . "} مستخدم

• عدد العضاء الكلي : {" . \$count . "}",
    'parse_mode' => 'Markdown'
]);
unlink(__FILE__);
?>
PHP;
}
//-------------------------- قسم الحظر ------------------------------//
if ($data == "ksmblock") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)\nمرحبا بك في قسم الحظر",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• حظر عضو •", 'callback_data' => "block"]],
                [['text' => "• إلغاء حظر عضو •", 'callback_data' => "unblock"]],
                [['text' => "• إعادة ضبط تحذيرات عضو •", 'callback_data' => "reset_warnings"]],
                [['text' => "• عرض جميع المحظورين •", 'callback_data' => "blocks"]],
                [['text' => "• حذف جميع المحظورين •", 'callback_data' => "unblocks"]],
                [['text' => "• رجوع •", 'callback_data' => "bot"]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($data == "reset_warnings") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "أرسل آيدي المستخدم لإعادة ضبط تحذيراته:",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => 'إلغاء', 'callback_data' => 'ksmblock']]
            ]
        ])
    ]);
    $jexo['mode'][$from_id]['mode'] = "reset_warnings_user";
    s();
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "reset_warnings_user") {
    $user_id = trim($text);
    if (is_numeric($user_id)) {
        if (isset($bot["from_ban"][$user_id])) {
            unset($bot["from_ban"][$user_id]);
        }
        
        if (isset($bot['banned']) && ($key = array_search($user_id, $bot['banned'])) !== false) {
            unset($bot['banned'][$key]);
            $bot['banned'] = array_values($bot['banned']);
        }
        
        s();
        
        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "✅ تم إعادة ضبط تحذيرات المستخدم $user_id وإلغاء الحظر إذا كان محظوراً"
        ]);
    } else {
        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "❌ الرجاء إرسال آيدي صحيح"
        ]);
    }
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    exit;
}


if ($data == "block") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا ارسل ايدي الشخص المراد حظره",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• الغاء •", 'callback_data' => "ksmblock"]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'block';
    s();
    exit;
}
if ($data == "unblock") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا ارسل ايدي الشخص المراد إلغاء حظره",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• الغاء •", 'callback_data' => "ksmblock"]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'unblock';
    s();
    exit;
}
if ($text && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'block') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['banned'][] = $text;
        s();
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم حظر [العضو](tg://user?id=$text) بنجاح 🔒",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "• رجوع •", 'callback_data' => "ksmblock"]]
                ]
            ])
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ او ان الايدي خاطئ\nارسل الايدي مجددا",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "• الغاء •", 'callback_data' => "ksmblock"]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($text && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'unblock') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['banned'] = array_filter($bot['banned'], function($id) use ($text) {
            return $id != $text;
        });
        s();
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم إلغاء حظر [العضو](tg://user?id=$text) بنجاح ✅",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "• رجوع •", 'callback_data' => "ksmblock"]]
                ]
            ])
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ او ان الايدي خاطئ\nارسل الايدي مجددا",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "• الغاء •", 'callback_data' => "ksmblock"]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($data == "blocks") {
    $names = '';
    foreach ($bot['banned'] as $id) {
        $names .= "ID: $id\n\n";
    }
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "*المحظورين* :\n$names",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmblock"]]
            ]
        ])
    ]);
}
if ($data == "unblocks") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "هل أنت متأكد من أنك تريد حذف جميع المحظورين؟ لا يمكن التراجع عن هذا الإجراء.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "✅ نعم، احذف", 'callback_data' => "confirm_unblocks"]],
                [['text' => "❌ إلغاء", 'callback_data' => "ksmblock"]]
            ]
        ])
    ]);
    exit;
}
if ($data == "confirm_unblocks") {
    $bot['banned'] = [];
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "تم حذف جميع المحظورين بنجاح.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmblock"]]
            ]
        ])
    ]);
    s();
    exit;
}
//-------------------------- قسم الحظر ------------------------------//


































//-------------------------- قسم الادمنيه ------------------------------//
if ($data == "ksmadmin") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)\nمرحبا بك في قسم الادمنيه",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
    'inline_keyboard' => [
        [['text'=>"• رفع ادمن •",'callback_data'=>"admins"],['text'=>"• حذف ادمن •",'callback_data'=>"unadmins"]],
        [['text' => "• عرض جميع الادمنيه •", 'callback_data' => "adminss"]],
        [['text' => "• حذف جميع الادمنيه •", 'callback_data' => "unadminss"]],
        [['text' => "• رجوع •", 'callback_data' => "bot"]]
    ]
])
]);
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($data == "set_subscription_prices") {
    $message = "💰 تحديد أسعار العضويات\n\n";
    $message .= "• اليومية: " . ($bot['subscription_prices']['daily'] ?? 3) . " نقطة\n";
    $message .= "• الأسبوعية: " . ($bot['subscription_prices']['weekly'] ?? 10) . " نقطة\n";
    $message .= "• الشهرية: " . ($bot['subscription_prices']['monthly'] ?? 25) . " نقطة\n";
    $message .= "• السنوية: " . ($bot['subscription_prices']['yearly'] ?? 100) . " نقطة\n\n";
    $message .= "• اختر نوع العضوية لتعديل سعرها:";
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "اليومية - " . ($bot['subscription_prices']['daily'] ?? 3) . " نقطة", 'callback_data' => "edit_price|daily"]],
                [['text' => "الأسبوعية - " . ($bot['subscription_prices']['weekly'] ?? 10) . " نقطة", 'callback_data' => "edit_price|weekly"]],
                [['text' => "الشهرية - " . ($bot['subscription_prices']['monthly'] ?? 25) . " نقطة", 'callback_data' => "edit_price|monthly"]],
                [['text' => "السنوية - " . ($bot['subscription_prices']['yearly'] ?? 100) . " نقطة", 'callback_data' => "edit_price|yearly"]],
                [['text' => "🔙 رجوع", 'callback_data' => "ksmadmin"]]
            ]
        ])
    ]);
    exit;
}

if (strpos($data, "edit_price|") === 0) {
    $type = str_replace("edit_price|", "", $data);
    
    $type_names = [
        'daily' => 'اليومية',
        'weekly' => 'الأسبوعية',
        'monthly' => 'الشهرية',
        'yearly' => 'السنوية'
    ];
    
    $default_prices = [
        'daily' => 3,
        'weekly' => 10,
        'monthly' => 25,
        'yearly' => 100
    ];
    
    $current_price = $bot['subscription_prices'][$type] ?? $default_prices[$type] ?? 100;
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "✏️ تعديل سعر العضوية {$type_names[$type]}\n\nالسعر الحالي: $current_price نقطة\n\nأرسل السعر الجديد (رقم فقط):",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع", 'callback_data' => "set_subscription_prices"]]
            ]
        ])
    ]);
    
    $jexo['mode'][$from_id]['mode'] = "edit_price_" . $type;
    s();
    exit;
}

if ($data == "set_invite_points") {
    $current_points = $bot['invite_points'] ?? 1;
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🎯 تعيين نقاط رابط الدعوة\n\nالنقاط الحالية: $current_points نقطة لكل مدعو\n\nأرسل عدد النقاط الجديد (رقم فقط):",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع", 'callback_data' => "ksmadmin"]]
            ]
        ])
    ]);
    
    $jexo['mode'][$from_id]['mode'] = "set_invite_points";
    s();
    exit;
}

if ($text && strpos($jexo['mode'][$from_id]['mode'], "edit_price_") === 0) {
    if (is_numeric($text) && $text > 0) {
        $type = str_replace("edit_price_", "", $jexo['mode'][$from_id]['mode']);
        $bot['subscription_prices'][$type] = (int)$text;
        s();
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم تحديث سعر العضوية بنجاح\nالسعر الجديد: $text نقطة",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 رجوع للأسعار", 'callback_data' => "set_subscription_prices"]]
                ]
            ])
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال رقم صحيح موجب"
        ]);
    }
    
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "set_invite_points") {
    if (is_numeric($text) && $text > 0) {
        $bot['invite_points'] = (int)$text;
        s();
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم تحديث نقاط الدعوة بنجاح\nالنقاط الجديدة: $text نقطة لكل مدعو"
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال رقم صحيح موجب"
        ]);
    }
    
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($data == "admins") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا ارسل الايدي بتاعه حالا",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text'=>"• الغاء •",'callback_data'=>"ksmadmin" ]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'admins';
    s();
    exit;
}
if ($data == "unadmins") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا ارسل ايدي البرنس دا حالا",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text'=>"• الغاء •",'callback_data'=>"ksmadmin" ]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'unadmins';
    s();
    exit;
}
if ($text and !$data && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'admins') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['admins'][] = $text;
        s();

        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم رفع [العضو](tg://user?id=$text) ادمن بنجاح 🌹",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• رجوع •",'callback_data'=>"ksmadmin" ]]
                ]
            ])
        ]);
        bot("sendmessage", [
            "chat_id" => $text, 
            "text" => "مرحبا.. 🌹\nتم رفعك ادمن في البوت بواسطة [المطور](tg://user?id=$admin) ♥",
            'parse_mode' => "markdown"
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ او ان الايدي خاطئ\nارسل الايدي مجددا",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• الغاء •",'callback_data'=>"ksmadmin" ]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($text and !$data && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'unadmins') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['admins'] = array_filter($bot['admins'], function($id) use ($text) {
            return $id != $text;
        });
        s();
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم سحب الادمن من [العضو](tg://user?id=$text) بنجاح 💯",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• رجوع •",'callback_data'=>"ksmadmin" ]]
                ]
            ])
        ]);
        bot("sendmessage", [
            "chat_id" => $text, 
            "text" => "تم سحب الادمنيه منك بواسطة [المطور](tg://user?id=$admin)",
            'parse_mode' => "markdown"
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ او ان الايدي خاطئ\nارسل الايدي مجددا",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• الغاء •",'callback_data'=>"ksmadmin" ]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}
if ($data == "adminss") {
    $names = '';
    foreach ($bot['admins'] as $id) {
        $user_info = bot('getChatMember', ['chat_id' => $id, 'user_id' => $id])->result;
        $username = $user_info->user->username ?? '';
        $name = $user_info->user->first_name ?? '';
        $names .= "ID: $id\nUsername: [@$username]\nName: [$name](tg://user?id=$id)\n\n";
    }
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "*الادمنيه* :\n$names",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmadmin"]]
            ]
        ])
    ]);
}
if ($data == "unadminss") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "هل أنت متأكد من أنك تريد حذف جميع الإدمنيه؟ لا يمكن التراجع عن هذا الإجراء.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "✅ نعم، احذف", 'callback_data' => "confirm_unadminss"]],
                [['text' => "❌ إلغاء", 'callback_data' => "ksmadmin"]]
            ]
        ])
    ]);
    exit;
}
if ($data == "confirm_unadminss") {
    $bot['admins'] = [];
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "تم حذف جميع الإدمنيه بنجاح.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmadmin"]]
            ]
        ])
    ]);
    s();
    exit;
}
//-------------------------- قسم الادمنيه ------------------------------//










































//-------------------------- قسم الـ VIP ------------------------------//

if ($data == "ksmvip") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)\nمرحبا بك في قسم إدارة الـ VIP",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => " زر التقديم على طلب اشتراك " . $VIP_button, 'callback_data' => "VIP_button"]],
                [['text'=>"• إضافة VIP •",'callback_data'=>"addvip"],['text'=>"• حذف VIP •",'callback_data'=>"removevip"]],
                [['text' => "• عرض جميع الـ VIP •", 'callback_data' => "viewvips"]],
                [['text' => "• حذف جميع الـ VIP •", 'callback_data' => "clearvips"]],
                [['text' => "• رجوع •", 'callback_data' => "bot"]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($data == "addvip") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا، أرسل الـ ID الخاص بالمستخدم لإضافته إلى قائمة الـ VIP.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text'=>"• إلغاء •",'callback_data'=>"ksmvip" ]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'addvip';
    s();
    exit;
}

if ($data == "removevip") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "حسنا، أرسل الـ ID الخاص بالمستخدم لحذفه من قائمة الـ VIP.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text'=>"• إلغاء •",'callback_data'=>"ksmvip" ]]
            ]
        ])
    ]);
    $bot['mode'][$from_id]['mode'] = 'removevip';
    s();
    exit;
}

if ($text and !$data && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'addvip') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['promotionn'][] = $text;
        s();

        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم إضافة [العضو](tg://user?id=$text) إلى قائمة الـ VIP بنجاح 🌟",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• رجوع •",'callback_data'=>"ksmvip" ]]
                ]
            ])
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ أو أن الـ ID خاطئ. من فضلك أرسل الـ ID مجددًا.",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• إلغاء •",'callback_data'=>"ksmvip" ]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($text and !$data && $from_id == $admin && $bot['mode'][$from_id]['mode'] == 'removevip') {
    $pattern = '/\b\d{8,12}\b/';
    if (preg_match($pattern, $text, $matches)) {
        $bot['promotionn'] = array_filter($bot['promotionn'], function($id) use ($text) {
            return $id != $text;
        });
        s();
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "تم حذف [العضو](tg://user?id=$text) من قائمة الـ VIP بنجاح.",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• رجوع •",'callback_data'=>"ksmvip" ]]
                ]
            ])
        ]);
    } else {
        bot("sendmessage", [
            "chat_id" => $chat_id, 
            "text" => "حدث خطأ أو أن الـ ID خاطئ. من فضلك أرسل الـ ID مجددًا.",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text'=>"• إلغاء •",'callback_data'=>"ksmvip" ]]
                ]
            ])
        ]);
    }
    $bot['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

if ($data == "viewvips") {
    $names = '';
    foreach ($bot['promotionn'] as $id) {
        $user_info = bot('getChatMember', ['chat_id' => $id, 'user_id' => $id])->result;
        $username = $user_info->user->username ?? '';
        $name = $user_info->user->first_name ?? '';
        $names .= "ID: $id\nUsername: [@$username]\nName: [$name](tg://user?id=$id)\n\n";
    }
    bot("EditMessageText", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "*المستخدمين الـ VIP* :\n$names",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmvip"]]
            ]
        ])
    ]);
}

if ($data == "clearvips") {
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "هل أنت متأكد من أنك تريد حذف جميع المستخدمين الـ VIP؟ لا يمكن التراجع عن هذا الإجراء.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "✅ نعم، احذف", 'callback_data' => "confirm_clearvips"]],
                [['text' => "❌ إلغاء", 'callback_data' => "ksmvip"]]
            ]
        ])
    ]);
    exit;
}

if ($data == "confirm_clearvips") {
    $bot['promotionn'] = null;
    bot("EditMessageText", [
        "chat_id" => $chat_id, 
        'message_id' => $message_id,
        "text" => "تم حذف جميع المستخدمين الـ VIP بنجاح.",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• رجوع •", 'callback_data' => "ksmvip"]]
            ]
        ])
    ]);
    s();
    exit;
}
//-------------------------- قسم الـ VIP ------------------------------//
















if ($data == "add_vip") {
    $jexo['vip_mode'][$from_id] = "add";
    s();
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🔢 أرسل الآن آيدي العضو الذي تريد إضافته للاشتراكات المدفوعة (VIP):"
    ]);
    exit;
}

// التعامل مع حذف اشتراك
if ($data == "del_vip") {
    $jexo['vip_mode'][$from_id] = "del";
    s();
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🗑️ أرسل آيدي العضو الذي تريد حذفه من الاشتراكات المدفوعة:"
    ]);
    exit;
}

// عرض كل المشتركين في VIP
if ($data == "list_vip") {
    $vips = $jexo['vip'] ?? [];
    if (count($vips) == 0) {
        $msg = "❌ لا يوجد مشتركين VIP حالياً.";
    } else {
        $msg = "✅ قائمة المشتركين VIP:

";
        foreach ($vips as $id) {
            $msg .= "🔹 $id
";
        }
    }
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $msg,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع", 'callback_data' => "vip_menu"]]
            ]
        ])
    ]);
    exit;
}

// استقبال رسائل إضافة أو حذف VIP
if ($message && isset($jexo['vip_mode'][$from_id])) {
    $mode = $jexo['vip_mode'][$from_id];
    $vip_id = trim($text);
    if ($mode == "add") {
        $jexo['vip'][] = $vip_id;
        bot("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "✅ تم إضافة العضو $vip_id إلى قائمة VIP بنجاح."
        ]);
        bot("sendMessage", [
            "chat_id" => $vip_id,
            "text" => "🎉 تم إضافتك لقائمة VIP بنجاح! استمتع بمميزات غير محدودة."
        ]);
    } elseif ($mode == "del") {
        if (($key = array_search($vip_id, $jexo['vip'])) !== false) {
            unset($jexo['vip'][$key]);
            bot("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "🗑️ تم حذف العضو $vip_id من قائمة VIP بنجاح."
            ]);
        } else {
            bot("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "⚠️ العضو $vip_id غير موجود في القائمة."
            ]);
        }
    }
    unset($jexo['vip_mode'][$from_id]);
    s();
    exit;
}


if ($data == "vip") {
    bot("sendMessage", [
        "chat_id" => $admin,
        "text" => "
*✅ - طلب تفعيل اشتراك 
☑️ - الشخص :* $name
 
[$from_id](tg://user?id=$chat_id) 
[Acount](tg://openmessage?user_id=$chat_id)
",
        "parse_mode" => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• تفعيل اشتراك •", 'callback_data' => "trues|$from_id"], ['text' => "• رفض اشتراك •", 'callback_data' => "falses|$from_id"]],
            ]
        ])
    ]);
    bot('EditMessageText', [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
*تم ارسال طلب اشتراك* [للمطور](tg://openmessage?user_id=$admin)
",
        "parse_mode" => "markdown",
    ]);
    exit;
}
list($action, $userId) = explode("|", $data);
if ($action == "trues") {
    bot("editMessagetext", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
• - تم قبول طلب الاشتراك بنجاح وتم تفعيل حساب [المستخدم](tg://user?id=$userId)
",
        "parse_mode" => "markdown",
    ]);
    bot("sendMessage", [
        "chat_id" => $userId,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
* • - تم قبول طلب الاشتراك حسابك بنجاح *

• - ارسل /start
",
        "parse_mode" => "markdown",
    ]);
    $bot['promotionn'][] = $userId;
    s();
}
if ($action == "falses") {
    bot("editMessagetext", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
تم رفض طلب [المستخدم](tg://user?id=$userId)
",
        "parse_mode" => "markdown",
    ]);
    bot("sendMessage", [
        "chat_id" => $userId,
        "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
*- * [المطور](tg://openmessage?user_id=$admin) رفض اشتراكك يمكنك مراسلته لتفعيل البوت
",
        "parse_mode" => "markdown",
    ]);
    exit;
}

























if ($bot['bott'] != "on" and !in_array($from_id, $admins)) {
    if ($data) {
        $m = 'EditMessageText';
    } else {
        $m = 'sendMessage';
    }
    bot($m, [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🚧 البوت تحت الصيانة حالياً
♦️ نرجو المحاولة لاحقًا، شكرًا لتفهمك 
📢 تابع التحديثات: @S7_MX3
",
        'parse_mode' => "markdown",
    ]);
    exit;
}
$bot['promotionn'] = $bot['promotionn'] ?? [];
if ($bot['premium'] == "on" && !in_array($from_id, $admins) && !in_array($from_id, $bot['promotionn'])) {
    // نحدد إذا كان التعديل على رسالة موجودة أو إرسال رسالة جديدة
    $m = $data ? 'editMessageText' : 'sendMessage';

    // نجهز نص الرسالة
    $messageText = "
عذرا، هذا البوت مدفوع\n يمكنك مراسلة المطور للاشتراك في البوت
";

    // نجهز زر الإنلاين إذا كان VIP_button == "on"
    $replyMarkup = null;
    if ($bot['VIP_button'] == "on") {
        $replyMarkup = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'اضغط هنا لإرسال اشتراك للمطور', 'callback_data' => 'vip']
                ]
            ]
        ]);
    }

    // نرسل الرسالة
    bot($m, [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $messageText,
        'parse_mode' => "markdown",
        'reply_markup' => $replyMarkup
    ]);

    exit;
}






























































































$check = $bot['check'] === "on" ? "مفعل ✅" : "معطل ❌";
$upload = $bot['upload'] === "on" ? "مفعل ✅" : "معطل ❌";
$folder = $bot['folder'] === "on" ? "مفعل ✅" : "معطل ❌";
$vip_list = $jexo['vip'] ?? [];
if (in_array($from_id, $vip_list)) {
    $numberfiles = PHP_INT_MAX; // عدد لا نهائي للـ VIP
} else {
    $numberfiles = isset($bot["numberfiles"]) ? $bot["numberfiles"] : 7; // العدد العادي للباقي
}
$numberban = isset($bot["numberban"]) ? $bot["numberban"] : 5;

if ($data == 'check') {
    $bot['check'] = $bot['check'] === "on" ? "off" : "on";
    $check = $bot['check'] === "on" ? "مفعل ✅" : "معطل ❌";
    s();
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['check'] === "on" ? "تفعيل" : "تعطيل") . "فحص الملفات"
    ]);
    jexo2();
}
if ($data == 'upload') {
    $bot['upload'] = $bot['upload'] === "on" ? "off" : "on";
    $upload = $bot['upload'] === "on" ? "مفعل ✅" : "معطل ❌";
    s();
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['upload'] === "on" ? "تفعيل" : "تعطيل") . "فحص الملفات"
    ]);
    jexo2();
}
if ($data == 'folder') {
    $bot['folder'] = $bot['folder'] === "on" ? "off" : "on";
    $folder = $bot['folder'] === "on" ? "مفعل ✅" : "معطل ❌";
    s();
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => "تم " . ($bot['folder'] === "on" ? "تفعيل" : "تعطيل") . "فحص الملفات"
    ]);
    jexo2();
}


if ($data == "jexo") {
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
⎋ اهلا بك في الاعدادات الخاصه ببوت الرفع
⚙️ — — — — — — — — — — — ⚙️
",
        'parse_mode' => "MARKDOWN",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "فحص الملفات " . $check, 'callback_data' => "check"]],
                [['text' => "رفع الملفات " . $upload, 'callback_data' => "upload"]],
                [['text' => "إنشاء فولدرات " . $folder, 'callback_data' => "folder"]],
                [['text' => '• المحظورين من الرفع •', 'callback_data' => "banall"]],
                [['text' => "عدد ملفات  " . ($bot['free_files'] ?? 2), 'callback_data' => "set_free_files"],
                 ['text' => "عدد التحذيرات {$numberban}", 'callback_data' => "set_numberban"]],
                [['text' => '• الاعدادات العامه •', 'callback_data' => "bot"]]
            ]
        ])
    ]);
}

if ($data == "set_free_files") {
    handleSetMode(" الملفات", "free_files");
    exit;
}

if ($data == "set_numberban") {
    handleSetMode(" التحذيرات", "numberban");
    exit;
}

function handleSetMode($label, $key) {
    global $from_id, $message_id;
    bot('EditMessageText', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'text' => "قم بإرسال العدد الجديد لـ " . $label,
        'parse_mode' => "MARKDOWN",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '• إلغاء •', 'callback_data' => "jexo"]]
            ]
        ])
    ]);
    global $jexo;
    $jexo['mode'][$from_id]['mode'] = $key;
    s();
}

if (isset($text) && isset($jexo['mode'][$from_id]['mode'])) {
    $mode = $jexo['mode'][$from_id]['mode'];
    if ($mode === "numberfiles" || $mode === "numberban" || $mode === "free_files") {
        handleSetNewValue($text, $mode);
    }
}

function handleSetNewValue($newValue, $key) {
    global $from_id, $jexo, $bot;
    
    if (!is_numeric($newValue) || $newValue < 0) {
        bot('sendMessage', [
            'chat_id' => $from_id,
            'text' => "⚠️ العدد يجب أن يكون رقمًا صحيحًا موجبًا.",
            'parse_mode' => "MARKDOWN"
        ]);
        return;
    }

    $bot[$key] = (int)$newValue;
    
    // إذا كان تعديل عدد الملفات المجانية، عدل لكل المستخدمين
    if ($key === "free_files") {
        foreach ($jexo['users'] as $user_id => $user_data) {
            $jexo['users'][$user_id]['free_uploads'] = (int)$newValue;
        }
    }
    
    s();
    
    // تحديد اسم الحقل بالعربي
    $field_names = [
        'numberfiles' => 'عدد الملفات',
        'numberban' => 'عدد التحذيرات', 
        'free_files' => 'الملفات المجانية'
    ];
    
    $field_name = $field_names[$key] ?? $key;
    
    bot('sendMessage', [
        'chat_id' => $from_id,
        'text' => "✅ تم تعيين العدد الجديد `" . $newValue . "` لـ: " . $field_name,
        'parse_mode' => "MARKDOWN",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => '• رجوع •', 'callback_data' => "jexo"]]
            ]
        ])
    ]);
    
    $jexo['mode'][$from_id]['mode'] = null;
    s();
}



if ($text == "/start" || $data == "back2") {
    if ($data) {
        $m = 'EditMessageText';
    } else {
        $m = 'sendMessage';
    }
    
if ($text && strpos($text, '/start') === 0) {
    $parts = explode(' ', $text);
    
    if (count($parts) >= 2 && is_numeric($parts[1])) {
        $inviter_id = $parts[1];
        
        if ($inviter_id != $from_id) {
            
            if (!isset($jexo['invites'])) {
                $jexo['invites'] = [];
            }
            
            if (!isset($jexo['invites'][$inviter_id])) {
                $jexo['invites'][$inviter_id] = ['invited' => []];
            }
            
            if (!in_array($from_id, $jexo['invites'][$inviter_id]['invited'])) {
                
$jexo['invites'][$inviter_id]['invited'][] = $from_id;
                
                if (!isset($jexo['users'][$inviter_id])) {
                    $free_files_limit = $bot['free_files'] ?? 2;
                    $jexo['users'][$inviter_id] = [
                        'free_uploads' => $free_files_limit,
                        'points' => 0,
                        'subscription' => null
                    ];
                }
                
                $points_to_add = $bot['invite_points'] ?? 1;
                $jexo['users'][$inviter_id]['points'] += $points_to_add;
                
                file_put_contents('jexo.json', json_encode($jexo));
                
                // أرسل إشعار للداعي
                bot("sendMessage", [
                    "chat_id" => $inviter_id,
                    "text" => "🎉 *تمت دعوة عضو جديد!*\n\n" .
                             "👤 العضو: $name\n" .
                             "🆔 الايدي: `$from_id`\n" .
                             "💰 النقاط المضافة: *$points_to_add نقطة*\n" .
                             "📊 إجمالي مدعوينك: *" . count($jexo['invites'][$inviter_id]['invited']) . " شخص*\n" .
                             "💎 نقاطك الآن: *" . $jexo['users'][$inviter_id]['points'] . " نقطة*",
                    "parse_mode" => "markdown"
                ]);
            }
        }
    }
}
    
    $user_points = $jexo['users'][$from_id]['points'] ?? 0;
    $subscription_info = "";
    
    if (isset($jexo['users'][$from_id]['subscription']) && $jexo['users'][$from_id]['subscription'] !== null) {
        $sub = $jexo['users'][$from_id]['subscription'];
        if (time() < $sub['expiry']) {
            $remaining = $sub['expiry'] - time();
            $days = floor($remaining / 86400);
            $hours = floor(($remaining % 86400) / 3600);
            $subscription_info = "• الاشتراك : {$sub['type']} (متبقي : $days يوم, $hours ساعة)";
        }
    }
    
    if (empty($subscription_info)) {
        $free_uploads = $jexo['users'][$from_id]['free_uploads'] ?? 2;
        $subscription_info = "• الرفعات المجانية المتبقية : $free_uploads ملف";
    }
    
    $invite_count = isset($jexo['invites'][$from_id]['invited']) ? count($jexo['invites'][$from_id]['invited']) : 0;
    $invite_link = "https://t.me/" . $bot_user . "?start=" . $from_id;
    
    bot($m, [
    "chat_id" => $chat_id,
    'message_id' => $message_id,
    "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
💞 ⸽ • اهلا بك عزيزي ↜ [$name](tg://openmessage?user_id=$from_id)
🎗️ ⸽ • ايديك ↜ : [$from_id](tg://user?id=$from_id)
💰 ⸽ • نقاطك الحالية : {$user_points} نقطة
👥 ⸽ • عدد المدعوين : {$invite_count} شخص
🎯 ⸽ • لكل دعوة : " . ($bot['invite_points'] ?? 1) . " نقطة
{$subscription_info}
ׁ۪ ⬞.┄ׅ━ׄ┄ׅ━ׄ┄ׅ━ׄ─ׅ۰ ★ ۰─ׅ━ׄ┄ׅ━ׄ┄ׅ━ׄ┄ׅ ⬞. ׁ۪
```⭐⭐⭐⭐⭐
↜انت مستخدم 『𝐕𝐢𝐏』👀 ```
``` 
         🤖 hmd bots 🤖
            ```
📋 ⸽ • لوحة التحكم الخاصه بـك 
⚙️ ⸽ • لرفع الملفات فقط قم بارسالها هنا 
⛓ ⸽ • المجلد الافتراضي للرفع هو ↜ {$folder_id}
📁 ⸽ • ملفاتك المرفوعه ↜ {$from_upload}
🤖 ⸽ • عدد مستخدمين البوت ↜ {$stats['stats']['total_users']}
🌀 ⸽ • احصائيات الرفع في البوت ↜ {$upload_all_bot}
",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode([
        'inline_keyboard' => [
            [['text' => "🛠 - تحديث البوت ", 'callback_data' => "refr"], ['text' => "🛡️ - احصائيات الحمايه ", 'callback_data' => "nas"]],
            [['text' => "💎 شراء عضوية", 'callback_data' => "buy_subscription"], ['text' => "🎁 رابط الدعوة", 'callback_data' => "earn_points"]],
            [['text' => "💌 - التواصل مع الدعم ", 'callback_data' => "contact"]],
            [['text' => "➕ - انشاء مجلد  ", 'callback_data' => "Create_folder"], ['text' => "☑️ - تعيين مجلد الرفع ", 'callback_data' => "set_flowr"]],
            [['text' => "📜 - معلوماتي", 'callback_data' => "show"]],
            [['text' => "💯 - شكرا لثقتك بـ بوتنا ", 'callback_data' => "Editfile"], ['text'=>'المطور ـ 🪪','url'=>"tg://user?id=$admin"]],
        ]
    ])
]);

$jexo['mode'][$from_id]['mode'] = null;
s();
exit;
}

if ($data == "jexobots") {
   bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "مرحبا بك في قسم باقات الـ VIP",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "• تعيين أسعار العضويات •", 'callback_data' => "set_subscription_prices"]],
[['text' => "• تعيين نقاط الدعوة •", 'callback_data' => "set_invite_points"]],
[['text' => "• إضافة نقاط لعضو •", 'callback_data' => "add_points_user"],['text' => "• خصم نقاط لعضو •", 'callback_data' => "deduct_points_user"]],
        [['text' => "• رجوع •", 'callback_data' => "bot"]]
            ]
        ])
    ]);
    exit;
}

if ($data == "deduct_points_user") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "➖ خصم نقاط من عضو\n\nأرسل ايدي العضو :",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع", 'callback_data' => "jexobots"]]
            ]
        ])
    ]);
    
    $jexo['mode'][$from_id]['mode'] = "deduct_points_step1";
    s();
    exit;
}

if ($data == "add_points_user") {
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💰 إضافة نقاط لعضو\n\nأرسل ايدي العضو :",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع", 'callback_data' => "jexobots"]]
            ]
        ])
    ]);
    
    $jexo['mode'][$from_id]['mode'] = "add_points_step1";
    s();
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "add_points_step1") {
    if (is_numeric($text)) {
        $jexo['temp_user_id'] = $text;
        s();
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم حفظ الأيدي: $text\n\nالآن أرسل عدد النقاط :",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 إلغاء", 'callback_data' => "jexobots"]]
                ]
            ])
        ]);
        
        $jexo['mode'][$from_id]['mode'] = "add_points_step2";
        s();
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال ايدي صحيح (أرقام فقط)"
        ]);
    }
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "add_points_step2") {
    if (is_numeric($text) && $text > 0) {
        $user_id = $jexo['temp_user_id'];
        $points_to_add = (int)$text;
        
        // تأكد من وجود المستخدم
        if (!isset($jexo['users'][$user_id])) {
            $free_files_limit = $bot['free_files'] ?? 2;
            $jexo['users'][$user_id] = [
                'free_uploads' => $free_files_limit,
                'points' => 0,
                'subscription' => null
            ];
        }
        
        // إضافة النقاط
        $current_points = $jexo['users'][$user_id]['points'] ?? 0;
        $jexo['users'][$user_id]['points'] = $current_points + $points_to_add;
        
        s();
        
        // إشعار للأدمن
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم إضافة $points_to_add نقطة للعضو $user_id\n\nنقاطه الآن :" . $jexo['users'][$user_id]['points'] . " نقطة",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 رجوع", 'callback_data' => "jexobots"]]
                ]
            ])
        ]);
        
        // إشعار للعضو
        bot('sendMessage', [
            'chat_id' => $user_id,
            'text' => "🎉 تم إضافة $points_to_add نقطة لحسابك من قبل الأدمن!\n\nنقاطك الآن : " . $jexo['users'][$user_id]['points'] . " نقطة"
        ]);
        
        // تنظيف
        unset($jexo['temp_user_id']);
        $jexo['mode'][$from_id]['mode'] = null;
        s();
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال عدد صحيح موجب"
        ]);
    }
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "deduct_points_step1") {
    if (is_numeric($text)) {
        $jexo['temp_user_id_deduct'] = $text;
        s();
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم حفظ الأيدي: $text\n\nالآن أرسل عدد النقاط للخصم :",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 إلغاء", 'callback_data' => "jexobots"]]
                ]
            ])
        ]);
        
        $jexo['mode'][$from_id]['mode'] = "deduct_points_step2";
        s();
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال ايدي صحيح (أرقام فقط)"
        ]);
    }
    exit;
}

if ($text && $jexo['mode'][$from_id]['mode'] == "deduct_points_step2") {
    if (is_numeric($text) && $text > 0) {
        $user_id = $jexo['temp_user_id_deduct'];
        $points_to_deduct = (int)$text;
        
        // تأكد من وجود المستخدم
        if (!isset($jexo['users'][$user_id])) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ العضو $user_id غير موجود في قاعدة البيانات"
            ]);
            exit;
        }
        
        // التحقق من الرصيد
        $current_points = $jexo['users'][$user_id]['points'] ?? 0;
        
        if ($current_points < $points_to_deduct) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ لا يمكن خصم $points_to_deduct نقطة\nرصيد العضو فقط : $current_points نقطة"
            ]);
            exit;
        }
        
        // خصم النقاط
        $jexo['users'][$user_id]['points'] = $current_points - $points_to_deduct;
        
        s();
        
        // إشعار للأدمن
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم خصم $points_to_deduct نقطة من العضو $user_id\n\nنقاطه الآن : " . $jexo['users'][$user_id]['points'] . " نقطة",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "🔙 رجوع", 'callback_data' => "jexobots"]]
                ]
            ])
        ]);
        
        // إشعار للعضو
        bot('sendMessage', [
            'chat_id' => $user_id,
            'text' => "⚠️ تم خصم $points_to_deduct نقطة من حسابك من قبل الأدمن!\n\nنقاطك الآن: " . $jexo['users'][$user_id]['points'] . " نقطة"
        ]);
        
        // تنظيف
        unset($jexo['temp_user_id_deduct']);
        $jexo['mode'][$from_id]['mode'] = null;
        s();
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ الرجاء إرسال عدد صحيح موجب"
        ]);
    }
    exit;
}

if ($data == "earn_points") {
    $invite_link = "https://t.me/" . $bot_user . "?start=" . $from_id;
    
    $message = "🎁 تجميع النقاط\n\n";
    $message .= "كل صديق يدخل عبر رابطك = " . ($bot['invite_points'] ?? 1) . " نقطة\n";
    $message .= "الرابط خاص بك :\n\n";
    $message .= "$invite_link\n\n";
    $message .= "عدد الأصدقاء الذين دخلوا: " . (isset($jexo['invites'][$from_id]['invited']) ? count($jexo['invites'][$from_id]['invited']) : 0) . " صديق\n";
    $message .= "النقاط المجمعة من الدعوة : " . ($jexo['users'][$from_id]['points'] ?? 0) . " نقطة";
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📤 مشاركة الرابط", 'url' => "https://t.me/share/url?url=" . urlencode($invite_link)]],
                [['text' => "🔄 تحديث", 'callback_data' => "earn_points"]],
                [['text' => "🔙 رجوع", 'callback_data' => "back2"]]
            ]
        ])
    ]);
    exit;
}

if ($data == "buy_subscription") {
    $user_points = $jexo['users'][$from_id]['points'] ?? 0;
    
    $message = "💎 قائمة العضويات\n\n";
    $message .= "• نقاطك الحالية: $user_points نقطة\n\n";
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "يومية (24 ساعة) - " . ($bot['subscription_prices']['daily'] ?? 3) . " نقطة", 'callback_data' => "confirm_buy|daily"]],
                [['text' => "أسبوعية (7 أيام) - " . ($bot['subscription_prices']['weekly'] ?? 10) . " نقطة", 'callback_data' => "confirm_buy|weekly"]],
                [['text' => "شهرية (30 يوم) - " . ($bot['subscription_prices']['monthly'] ?? 25) . " نقطة", 'callback_data' => "confirm_buy|monthly"]],
                [['text' => "سنوية (365 يوم) - " . ($bot['subscription_prices']['yearly'] ?? 100) . " نقطة", 'callback_data' => "confirm_buy|yearly"]],
                [['text' => "🔙 رجوع", 'callback_data' => "back2"]]
            ]
        ])
    ]);
    exit;
}

if (strpos($data, "confirm_buy|") === 0) {
    $type = str_replace("confirm_buy|", "", $data);
    $price = $bot['subscription_prices'][$type] ?? 0;
    $user_points = $jexo['users'][$from_id]['points'] ?? 0;
    
    if ($user_points < $price) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "نقاطك غير كافية! تحتاج $price نقطة",
            'show_alert' => true
        ]);
        exit;
    }
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "⚠️ تأكيد الشراء\n\nهل أنت متأكد من شراء العضوية $type بسعر $price نقطة؟",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "✅ نعم، اشترِ الآن", 'callback_data' => "final_buy|$type"]],
                [['text' => "❌ إلغاء", 'callback_data' => "buy_subscription"]]
            ]
        ])
    ]);
    exit;
}

if (strpos($data, "final_buy|") === 0) {
    $type = str_replace("final_buy|", "", $data);
    $price = $bot['subscription_prices'][$type] ?? 0;
    
    $jexo['users'][$from_id]['points'] -= $price;
    
    $expiry = time();
    if ($type == 'daily') $expiry += 86400;
    elseif ($type == 'weekly') $expiry += 604800;
    elseif ($type == 'monthly') $expiry += 2592000;
    elseif ($type == 'yearly') $expiry += 31536000;
    
    $jexo['users'][$from_id]['subscription'] = [
        'type' => $type,
        'expiry' => $expiry
    ];
    
    s();
    
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🎉 تم الشراء بنجاح!\n\n• اشتركت في العضوية $type\n• تنتهي في: " . date('Y-m-d H:i:s', $expiry) . "\n• يمكنك الآن رفع ملفات غير محدودة!",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🔙 رجوع للقائمة", 'callback_data' => "back2"]]
            ]
        ])
    ]);
    exit;
}

if ($data == "nas") {
    $messageText = "
*إحصائيات الملفات المرفوعة في البوت*[$bot_name](tg://user?id=$bot_id)

🔹 **إجمالي الملفات المرفوعة:** `{$bot["all_file"]}`
🔸 **ملفات بوتات (Telegram):** `{$bot["Info_uploads"]["telegram"]}`
🔸 **ملفات غير مرتبطة بتليجرام:** `{$bot["Info_uploads"]["not_telegram"]}`
🔹 **ملفات PHP المرفوعة:** `{$bot["php"]}`
🔸 **ملفات JSON المرفوعة:** `{$bot["json"]}`
🔸 **ملفات نصية (TXT):** `{$bot["text"]}`
🔹 **ملفات تحتوي على مكتبة CURL:** `{$bot["Info_uploads"]["curl"]}`

---

🛡️ **الإحصائيات الأمنية:**
- 🚫 **ملفات PHP الضارة التي تم حظرها:** `{$bot["php_ban"]}`
- 🚫 **ملفات JSON الضارة التي تم حظرها:** `{$bot["json_ban"]}`
- 🚫 **ملفات TXT الضارة التي تم حظرها:** `{$bot["text_ban"]}`
- 🔒 **نسبة حماية البوت للملفات الضارة:** *عالية الدقة*

---
";

    bot("editMessageText", [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => $messageText,
        "parse_mode" => "markdown",
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [["text" => "رجوع", "callback_data" => "back2"]]
            ]
        ])
    ]);
}





function progress($total, $current) {
    $progress = $current / $total;
    $bar_length = 20;
    $filled_length = round($bar_length * $progress);

    $moon_phases = ["🌑", "🌒", "🌓", "🌔", "🌕", "🌖", "🌗", "🌘"];
    $moon_phase = $moon_phases[$current % count($moon_phases)];

    $bar = str_repeat("_", $filled_length) . "👨🏼‍🦼‍➡️" . str_repeat("_", ($bar_length - $filled_length - 1));
    $result = [
        "bar" => $bar,
        "moon" => $moon_phase
    ];
    return $result["bar"] . "  " . $result["moon"];
}



if ($data == "refr") {
    for ($i = 0; $i <= 10; $i++) {
        bot("editMessageText", [
            "chat_id" => $chat_id,
            'message_id' => $message_id,
            "text" => "*
♻️ يتم عمل تحديث انتظر قليلا
" . progress(10, $i) . "
*",
            "parse_mode" => "markdown",
        ]);
        sleep(0.3);
    }
    bot("editMessageText", [
        "chat_id" => $chat_id,
        'message_id' => $message_id,
        "text" => "*
✨ تم الانتهاء من التحديث ✔
*",
        "parse_mode" => "markdown",
    ]);
    sleep(1.5);
    bot("editMessageText", [
    "chat_id" => $chat_id,
    'message_id' => $message_id,
    "text" => "[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
💞 ⸽ • اهلا بك عزيزي ↜ [$name](tg://openmessage?user_id=$from_id)
🎗️ ⸽ • ايديك ↜ : [$from_id](tg://user?id=$from_id)
💰 ⸽ • نقاطك الحالية : {$user_points} نقطة
👥 ⸽ • عدد المدعوين : {$invite_count} شخص
🎯 ⸽ • لكل دعوة : " . ($bot['invite_points'] ?? 1) . " نقطة
{$subscription_info}
ׁ۪ ⬞.┄ׅ━ׄ┄ׅ━ׄ┄ׅ━ׄ─ׅ۰ ★ ۰─ׅ━ׄ┄ׅ━ׄ┄ׅ━ׄ┄ׅ ⬞. ׁ۪
```⭐⭐⭐⭐⭐
↜انت مستخدم 『𝐕𝐢𝐏』👀 ```
             ```
         🤖 hmd bots 🤖
             ```
📋 ⸽ • لوحة التحكم الخاصه بـك 
⚙️ ⸽ • لرفع الملفات فقط قم بارسالها هنا 
⛓ ⸽ • المجلد الافتراضي للرفع هو ↜ {$folder_id}
📁 ⸽ • ملفاتك المرفوعه ↜ {$from_upload}
🤖 ⸽ • عدد مستخدمين البوت ↜ {$stats['stats']['total_users']}
🌀 ⸽ • احصائيات الرفع في البوت ↜ {$upload_all_bot}
",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "🛠 - تحديث البوت ", 'callback_data' => "refr"], ['text' => "🛡️ - احصائيات الحمايه ", 'callback_data' => "nas"]],
[['text' => "💎 شراء عضوية", 'callback_data' => "buy_subscription"], ['text' => "🎁 تجميع نقاط", 'callback_data' => "earn_points"]],
                [['text' => "💌 - التواصل مع الدعم ", 'callback_data' => "contact"]],
                [['text' => "➕ - انشاء مجلد  ", 'callback_data' => "Create_folder"], ['text' => "☑️ - تعيين مجلد الرفع ", 'callback_data' => "set_flowr"]],
                [['text' => "📜 - معلوماتي", 'callback_data' => "show"]],
                [['text' => "💯 - شكرا لثقتك بـ بوتنا ", 'callback_data' => "Editfile"], ['text'=>'المطور ـ 🪪','url'=>"https://t.me/vvtlv"]],
            ]
        ])
    ]);
}

if ($data == 'Create_folder') {
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => '- قم بأرسال اسم المجلد الجديد، ',
        'parse_mode' => 'markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => 'رجوع', 'callback_data' => 'back2']]
            ]
        ])
    ]);

    $jexo['mode'][$from_id]['mode'] = 'Create_folder';
    s();
    exit;
}
if ($text && $jexo['mode'][$from_id]['mode'] == 'Create_folder') {
    $folder_name = "all/$chat_id/$text";
    mkdir("all");
    mkdir("all/$chat_id");
    if (!is_dir($folder_name)) {
        mkdir($folder_name, 0777, true);
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "- تم إنشاء الفولدر $text بنجاح ✅",
            'parse_mode' => 'markdown'
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "- المجلد موجود بالفعل.",
            'parse_mode' => 'markdown'
        ]);
    }
    $jexo['mode'][$from_id]['mode'] = null;
    s();
}






if(!isset($bot['from_folder'])){
    mkdir("all");
    mkdir("all/$chat_id");
    $bot['from_folder'] = "bots";
    $bot['all_file'] = 0;
    s();
}
if ($data == 'set_flowr') {
    if ($bot['folder'] != "off") {
        $user_folder = "all/$chat_id";
        $buttons = prepare_buttons($user_folder);
        if (empty($buttons)) {
            bot('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => '- لا توجد فولدرات متاحة للتعيين. تأكد من إنشاء فولدرات في المسار الأساسي.',
                'parse_mode' => 'markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'رجوع', 'callback_data' => 'back2']]
                    ]
                ])
            ]);
            return;
        }
        $inline_keyboard = [];
        foreach ($buttons as $folder_name) {
            $inline_keyboard[] = [['text' => $folder_name, 'callback_data' => "select_folder:$folder_name"]];
        }
        $inline_keyboard[] = [['text' => 'رجوع', 'callback_data' => 'back2']];
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "- اختر المجلد لتعيينه كفولدر رفع. لاحظ:\n' .
                '• عند عدم وجود العلامة `>`، يعني أن الفولدر في المسار الأساسي مباشرة.\n' .
                '• إذا كان هناك علامات `>`، فهذا يعني أن الفولدر متفرع داخل فولدر آخر.",
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => $inline_keyboard
            ])
        ]);
    } else {
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => '- لا يمكنك تعيين فولدر بسبب إغلاق المالك لهذا الأمر.',
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'رجوع', 'callback_data' => 'back2']]
                ]
            ])
        ]);
    }
}
if (strpos($data, 'select_folder:') === 0) {
    $selected_folder = str_replace('select_folder:', '', $data);
    $bot['from_folder'] = $selected_folder;
    s();
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "✅ تم تعيين فولدر الرفع الجديد :\n`$selected_folder`",
        'parse_mode' => 'markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => 'رجوع', 'callback_data' => 'back2']]
            ]
        ])
    ]);
}
function prepare_buttons($base_folder) {
    $folders = [];
    if (!is_dir($base_folder)) {
        return $folders;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_folder, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            $relative_path = str_replace($base_folder . '/', '', $item->getPathname());
            $formatted_path = str_replace('/', ' > ', $relative_path);
            $folders[] = $formatted_path;
        }
    }
    return $folders;
}





if ($data == 'contact') {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "📨 أرسل الآن رسالتك أو الوسائط التي تريد إرسالها إلى الدعم الفني. سيتم الرد عليك قريبًا.",
        'parse_mode' => 'markdown'
    ]);
    $jexo['mode'][$from_id]['mode'] = "contact";
    s();
    exit;
}

if ($text and $jexo['mode'][$from_id]['mode'] == "contact") {
    $pp = bot('sendMessage', [
        'chat_id' => $admin,
        'text' => "رسالة جديده عزيزي المطور من
- الاسم : [$name](tg://user?id=$from_id)
- المعرف :[ $sf ]
- الايدي : [$from_id](tg://openmessage?user_id=$from_id)

** نص الرساله **
{$text}

يمكنك الرد عليه من خلال الرد على هذا المسج
",
        'parse_mode' => 'markdown'
    ]);
    
    $message_id_to = $pp->result->message_id;
    $jexo["twasol"][$message_id_to] = $from_id;
    s();
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "تم ارسل رسالتك الى الدعم 
انتظر الرد
",
        'parse_mode' => 'markdown'
    ]);
    $jexo['mode'][$from_id]['mode'] = null;
    s();
    exit;
}

















if ($data == 'show') {
    $user_folder = "all/$chat_id";

    if (!is_dir($user_folder)) {
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => '- لم يتم العثور على مجلدات بعد.',
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'رجوع', 'callback_data' => 'back2']]
                ]
            ])
        ]);
    } else {
        $folders = get_folders($user_folder);

        if (!$folders || empty($folders)) {
            bot('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => '- لا توجد ملفات أو مجلدات في هذا المسار.',
                'parse_mode' => 'markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'رجوع', 'callback_data' => 'back2']]
                    ]
                ])
            ]);
            return;
        }

        $folder_icons = "📂";
        $file_icons = "📄";

        $total_folders = 0;
        $total_files = 0;
        $folders_list = "";

        foreach ($folders as $item) {
            if (strpos($item, $folder_icons) !== false) {
                $total_folders++;
            } elseif (strpos($item, $file_icons) !== false) {
                $total_files++;
            }

            $folders_list .= "- $item\n";
        }

        $max_display = 10;
        $display_list = implode("\n", array_slice(explode("\n", $folders_list), 0, $max_display));

        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "*العدد الكلي للفولدرات :* $total_folders\n" .
                      "*العدد الكلي للملفات :* $total_files\n\n" .
                      "العناصر المعروضة (أقصى $max_display) :\n$display_list",
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'رجوع', 'callback_data' => 'back2']],
                    $total_folders + $total_files > $max_display
                        ? [['text' => 'عرض المزيد', 'callback_data' => 'show_more']]
                        : []
                ]
            ])
        ]);
    }
}

function get_folders($base_folder) {
    if (!is_dir($base_folder)) return [];

    $items = [];
    $iterator = new DirectoryIterator($base_folder);

    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isDot()) continue;

        if ($fileinfo->isDir()) {
            $items[] = "📂 " . $fileinfo->getFilename();
        } elseif ($fileinfo->isFile()) {
            $items[] = "📄 " . $fileinfo->getFilename();
        }
    }

    return $items;
}


$tahzir = $numberban - $bot["from_ban"][$from_id];
if (!$bot["from_ban"][$from_id]) {
    $textban = "🚫 *تحذير أمني*

• تم اكتشاف محاولة رفع ملف ضار
• هذا التحذير رقم : 1
• التحذيرات المتبقية : " . ($numberban - 1) . "
• سيتم حظرك بعد نفاد جميع التحذيرات
• تم إرسال إشعار للمسؤول

• نسبة الحماية من الملفات الضارة: 99.9%";
} elseif ($tahzir > 1) {
    $textban = "🚫 *تحذير أمني*

• تم اكتشاف محاولة رفع ملف ضار
• هذا التحذير رقم : " . ($bot["from_ban"][$from_id] + 1) . "
• التحذيرات المتبقية : " . $tahzir . "
• سيتم حظرك بعد نفاد جميع التحذيرات
• تم إرسال إشعار للمسؤول

• نسبة الحماية من الملفات الضارة : 99.9%";
} elseif ($tahzir == 1) {
    $textban = "⚠️ *تحذير أخير*

• تم اكتشاف محاولة رفع ملف ضار
• لا يوجد لديك تحذيرات متبقية 
• إذا كررت الأمر مرة أخرى سيتم حظرك فوراً
• تم إرسال إشعار للمسؤول

• نسبة الحماية من الملفات الضارة : 99.9%";
} else {
    $textban = "🔒 *تم حظرك من البوت*

• تم حظرك نهائياً بسبب تجاوز التحذيرات ورفع ملفات ضارة
• لا يمكنك استخدام البوت بعد الآن";
}


$bot['promotionn'] = $bot['promotionn'] ?? [];

if($update->message->document){
    
    if(!canUploadFile($from_id)) {
        $free_uploads = $jexo['users'][$from_id]['free_uploads'] ?? ($bot['free_files'] ?? 2);
$total_free = $bot['free_files'] ?? 2;

$message = "🚫 لا يمكنك رفع الملف\n\n";
if ($free_uploads > 0) {
    $message .= "لديك $free_uploads من أصل $total_free رفع مجاني متبقية\n";
    $message .= "بعدها تحتاج لشراء اشتراك\n";
} else {
    $message .= "انتهت رفعاتك المجانية ($total_free ملف)\n";
    $message .= "تحتاج لشراء اشتراك للرفع غير المحدود\n";
}
        $message .= "نقاطك الحالية: $points نقطة\n\n";
        $message .= "اضغط /start للعودة للقائمة الرئيسية";
        
        bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => $message
        ]);
        exit;
    }
    
    if ($from_id != $admin && $bot['premium'] == "on" && !in_array($from_id, $bot['promotionn'])) {
        bot("sendMessage", [
            "chat_id" => $chat_id ,
            "text" => "
[ᶠʳᵒᵐ ʲᵘˢᵗ ʰᵐᵈ](tg://user?id=7012982464)
*عذرا لا يمنك رفع ملفاتك هنا لانك غير مشترك 
يمكنك التواصل مع المطور للاشتراك في البوت*
",
            'parse_mode'=>"markdown",
        ]);
        exit;
    }

    if($bot['upload'] == "off") {
        bot("sendmessage",[
            "chat_id" => $chat_id,
            "text" => "استقبال الملفات متوقف ❌" ,
            "parse_mode" => "marKdown",
            
        ]);
        exit;
    }

    if($bot["from_php"][$from_id] and $bot["from_php"][$from_id] > $numberfiles){
        bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"
• تم تجاوز عدد الملفات المحدد لك 
• العدد المحدد لك هوا $numberfiles ملف 
• يرجى حذف بعضا من الملفات المرفوعه مسبقا بواسطة الازرار
عدد ملفاتك المرفوعه --> ". $bot["from_php"][$from_id],
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[[['text'=>"• حذف جميع ملفاتك •",'callback_data'=>"delete_file_all|$from_id" ]]]
            ])
        ]);
        exit;
    }

   $file_id = "https://api.telegram.org/file/bot" . API_KEY . "/" . bot("getfile", ["file_id" => $document_file_id])->result->file_path;
   $file_path = "check/$chat_id.php";
   $f = file_get_contents($file_id);
   file_put_contents($file_path, $f);

   $advanced_check = advancedFileCheck($f, $document_file_name);
   if ($advanced_check !== "safe") {
    unlink("check/$chat_id.php");
    
    $bot["from_ban"][$from_id] = isset($bot["from_ban"][$from_id]) ? $bot["from_ban"][$from_id] + 1 : 1;
    s();
    
    $max_warnings = isset($bot["numberban"]) ? $bot["numberban"] : 5;
    $remaining_warnings = $max_warnings - $bot["from_ban"][$from_id];
    
    $warning_message = "*• تحذير لقد قمت بمحاوله اختراق 🥷🏽*\n\n";
    $warning_message .= "• عليك ان لا ترفع ملفات اختراق\n";
    $warning_message .= "• لديك " . $bot["from_ban"][$from_id] . " تحذير متبقي\n";
    $warning_message .= "• التحذيرات المتبقية : " . ($remaining_warnings > 0 ? $remaining_warnings : 0) . " : اذا نفذت سيتم حظرك\n\n";
    
    if ($remaining_warnings > 0) {
        $warning_message .= "• إذا وصلت إلى $max_warnings تحذيرات سيتم حظرك تلقائياً";
    } else {
        $warning_message .= "• تم حظرك الآن بسبب تجاوز الحد المسموح";
    }
    
    bot("sendmessage",[
        "chat_id" => $chat_id,
        "text" => $warning_message,
        "parse_mode" => "markdown",
    ]);
    
    if ($bot["from_ban"][$from_id] >= $max_warnings) {
        if (!in_array($from_id, $bot['banned'])) {
            $bot['banned'][] = $from_id;
            s();
            
            bot("sendmessage",[
                "chat_id" => $chat_id,
                "text" => "🔴 *تم حظرك نهائياً من البوت*\n\n• السبب : تجاوزت الحد المسموح به من التحذيرات ($max_warnings تحذيرات)\n• تم إبلاغ المسؤول\n• يمكنك التواصل مع المطور @vvtlv",
                "parse_mode" => "markdown",
            ]);
            
            bot("sendmessage",[
                "chat_id" => $admin,
                "text" => "🔴 *تم حظر مستخدم نهائياً*\n• الاسم: $name\n• الايدي : `$from_id`\n• السبب : تجاوز عدد التحذيرات ($max_warnings)\n• إجمالي محاولاته : " . $bot["from_ban"][$from_id],
                "parse_mode" => "markdown",
            ]);
        }
    } else {
        bot("sendmessage",[
            "chat_id" => $admin,
            "text" => "🚨 *محاولة رفع ملف ضار*\n• من : $name\n• السبب : $advanced_check\n• الايدي : `$from_id`\n• عدد المحاولات : " . $bot["from_ban"][$from_id],
            "parse_mode" => "markdown",
        ]);
    }
    
    exit;
}

   if(pathinfo($file_id, PATHINFO_EXTENSION) == "php"){
        $b = bot("sendmessage", [
            "chat_id" => $chat_id,
            "text" => "*• - يتم التحليل انتظر قليلاً..*" ,
            "parse_mode" => "markdown",
        ]);
        $count = explode("\n",$f);
        $count = count($count);
        $result = checkConditions($f);
        if ($result) {
            unlink("check/$chat_id.php");
            bot("editMessagetext",[
                "chat_id" => $chat_id,
                'message_id' => $b->result->message_id, 
                "text" => $textban ,
                "parse_mode" => "marKdown",
            ]);
            bot("sendmessage",[
                "chat_id" =>$admin,
                "text" => "
*• محاوله اختراق*
• من $name
        
[$from_id](tg://user?id=$chat_id) 
[Acount](tg://openmessage?user_id=$chat_id) 
" ,
                "parse_mode" => "markdown",       
            ]);
            $bot["from_ban"][$from_id]++;
            $bot["php_ban"]++;
            $bot["ban"]++;
            s();
            exit;
        }
       bot("editMessagetext",[
           "chat_id" => $chat_id,
           'message_id' => $b->result->message_id, 
           "text" => "
<s>• يتم التحليل انتظر قليلاً..</s>
• تم الرفع بنجاح 
• اسم الملف الخاص بك $document_file_name
" ,
           "parse_mode" => "html",
           ]);
        $ur = "https://$domin" . dirname($_SERVER['SCRIPT_NAME']) . "/all/$chat_id/$folder_id/$document_file_name";
        mkdir("all/$chat_id/$folder_id");
        $url = "all/$chat_id/$folder_id/$document_file_name";
        file_put_contents($url, $f);
        if(preg_match("/api.telegram.org/", $f)) {
           $bot["Info_uploads"]["telegram"]++;
        } else {
           $bot["Info_uploads"]["not_telegram"]++;
        }
        if (strpos($f, 'curl_') !== false) {
           $bot["Info_uploads"]["curl"]++;
        }
        $cr = rand(9999,999999);
        if (preg_match('/(\d{6,14}:[\w-]{35,75})/', $f, $matches)) {
            $took = $matches[0];
            $result = file_get_contents("https://api.telegram.org/bot$took/getme");
            if ($result != false) {
                $bot["Info_from_upload"][$cr]["token"] = $took;
                $bot["Info_from_upload"][$cr]["webhook"] = urlencode($ur);
                s();
                $keyb = [
                    [['text'=>"• ♻️ عمل ويبهوك ♻️ •",'callback_data'=>"up_webhook|$cr" ],['text'=>"• ⚠️ حذف الويبهوك ⚠️ •",'callback_data'=>"del_webhook|$cr" ]],
                    [['text'=>"• 💥 حذف الملف من الاستضافه 💥 •",'callback_data'=>"delete_file|$cr" ]],
                    [['text'=>"• 📝 معلومات البوت 📝 •",'callback_data'=>"information_bot|$cr" ]],
                    [['text'=>"• 📛 حذف جميع ملفاتك 📛 •",'callback_data'=>"delete_file_all|$from_id" ]]
                ];
                $jexo12 = urlencode($ur);

            } else {
                $took = "خذ هذا التوكن {" . $matches[0] . "} خاطئ او تم الغاء تفعيله من البوت فاذر يرجى تغييره";
                $keyb = [
                    [['text'=>"• 💥 حذف الملف من الاستضافه 💥 •",'callback_data'=>"delete_file|$cr" ]],
                     [['text'=>"• 📛 حذف جميع ملفاتك 📛 •",'callback_data'=>"delete_file_all|$from_id" ]]
                ];
                $jexo12 = "لا يوجد روابط لعرضها";
            }
       } else {
            $took = "لا يوجد توكن";
            $keyb = [
                [['text'=>"• 💥 حذف الملف من الاستضافه 💥 •",'callback_data'=>"delete_file|$cr" ]],
                [['text'=>"• 📛 حذف جميع ملفاتك 📛 •",'callback_data'=>"delete_file_all|$from_id" ]]
            ];
            $jexo12 = "لا يوجد روابط لعرضها";
        }
        bot("editMessagetext",[
            "chat_id" => $chat_id,
            'message_id' => $b->result->message_id, 
            "text" => "
- مسار الملف *$folder_id* 🧸

- رابط الويبهوك `$jexo12`

- توكن البوت  `$took`  🧸
            ",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode(['inline_keyboard' => $keyb])
        ]);
        bot("sendmessage",[
            "chat_id" => $admin,
            "text" => "
- تم رفع ملف جديد من المستخدم [$name](tg://user?id=$from_id) : [$from_id](tg://openmessage?user_id=$from_id)

- مسار الملف *$folder_id*

- رابط الويبهوك [ $ur ]

- توكن البوت  `$took` 
           ",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode(['inline_keyboard' => $keyb])
        ]);
        $bot["from_php"][$from_id]++;
        $bot["php"]++;
        $bot["file"]++;
        $bot["all_file"]++;

if (isset($jexo['users'][$from_id]['free_uploads']) && $jexo['users'][$from_id]['free_uploads'] > 0) {
    $jexo['users'][$from_id]['free_uploads']--;
    file_put_contents('jexo.json', json_encode($jexo));
}

$bot["Info_from_upload"][$cr]["url"] = "all/$chat_id/$folder_id/$document_file_name";
s();


   } elseif (pathinfo($file_id, PATHINFO_EXTENSION) == "json") {
        $data = json_decode($f, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            bot("editMessagetext",[
                "chat_id" => $chat_id,
                'message_id' => $b->result->message_id, 
                "text" => "حدث خطا ❌" ,
                "parse_mode" => "marKdown",
            ]);
            exit;
        }
        $suspicious_words = array("ZipArchive", "zip", "eval", ".php", "base64", "base64_decode", "github", "public function create", ".Php", ".pHp", ".phP", ".PHp", ".pHP", ".PhP", "include", "shell", "system", "timestamper", "__FILE__");
        foreach ($data as $key => $value) {
            foreach ($suspicious_words as $word) {
                if (strpos($value, $word) !== false || strpos($key, $word) !== false) {
                    bot("editMessagetext",[
                        "chat_id" => $chat_id,
                        'message_id' => $b->result->message_id, 
                        "text" => $textban ,
                        "parse_mode" => "marKdown",
                    ]);
                    bot("sendmessage",[
                        "chat_id" =>$admin,
                        "text" => "
*• محاوله اختراق*
• من $name

[$from_id](tg://user?id=$chat_id) 
[Acount](tg://openmessage?user_id=$chat_id) 
" ,
                    "parse_mode" => "marKdown",
                    ]);
                    $bot["from_ban"][$from_id]++;
                    $bot["json_ban"]++;
                    $bot["ban"]++;
                    s();
                    return false;
                }
            }
        }
        bot("editMessagetext",[
            "chat_id" => $chat_id,
            'message_id' => $b->result->message_id, 
            "text" => "
<s>• يتم التحليل انتظر قليلاً..</s>
• تم الرفع بنجاح 
• اسم الملف الخاص بك { $document_file_name }
" ,
            "parse_mode" => "html",
        ]);
        $url = "all/$chat_id/$folder_id/$document_file_name";
        file_put_contents($url, $f);
        $bot["from_json"][$from_id]++;
$bot["json"]++;
$bot["file"]++;
$bot["all_file"]++;

if (isset($jexo['users'][$from_id]['free_uploads']) && $jexo['users'][$from_id]['free_uploads'] > 0) {
    $jexo['users'][$from_id]['free_uploads']--;
    file_put_contents('jexo.json', json_encode($jexo));
}

s();


    } elseif (pathinfo($file_id, PATHINFO_EXTENSION) == "txt"){
        $txt_content = $f;
        $suspicious_words = array("ZipArchive", "zip", "eval", ".php", "base64", "base64_decode", "github", "public function create", ".Php", ".pHp", ".phP", ".PHp", ".pHP", ".PhP", "include", "shell", "system", "timestamper", "__FILE__");
        foreach ($suspicious_words as $word) {
            if (strpos($txt_content, $word) !== false) {
                bot("editMessagetext",[
                    "chat_id" => $chat_id,
                    'message_id' => $b->result->message_id, 
                    "text" => $textban ,
                    "parse_mode" => "marKdown",
                ]);
                bot("sendmessage",[
                    "chat_id" =>$admin,
                    "text" => "
*• محاوله اختراق*
• من $name

[$from_id](tg://user?id=$chat_id) 
[Acount](tg://openmessage?user_id=$chat_id) 
" ,
                    "parse_mode" => "marKdown",
                ]);
                $bot["from_ban"][$from_id]++;
                $bot["text_ban"]++;
                $bot["ban"]++;
                s();
                return false;
            }
        }
        bot("editMessagetext",[
            "chat_id" => $chat_id,
            'message_id' => $b->result->message_id, 
            "text" => "
<s>• يتم التحليل انتظر قليلاً..</s>
• تم الرفع بنجاح 
• اسم الملف الخاص بك { $document_file_name }
" ,
            "parse_mode" => "html",
        ]);
        $url = "all/$chat_id/$folder_id/$document_file_name";
        file_put_contents($url, $f);
        $bot["from_text"][$from_id]++;
$bot["text"]++;
$bot["file"]++;
$bot["all_file"]++;

if (isset($jexo['users'][$from_id]['free_uploads']) && $jexo['users'][$from_id]['free_uploads'] > 0) {
    $jexo['users'][$from_id]['free_uploads']--;
    file_put_contents('jexo.json', json_encode($jexo)); 
}

s();
    }
}

$da = explode("|", $data);
$command = $da[0];
$cr = $da[1] ?? null;
if ($command == "up_webhook") {

    $tk = $bot["Info_from_upload"][$cr]["token"];
    $ul = $bot["Info_from_upload"][$cr]["webhook"];
    file_get_contents("https://api.telegram.org/bot$tk/setwebhook?url=$ul");
    $result = file_get_contents("https://api.telegram.org/bot$tk/getme");
    if ($result === false) {
        $text = "التوكن خاطئ ❌";
    } else {
        $text = "• تم عمل ويبهوك ✅";
    }
    
    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => $text,
        'show_alert' => true
    ]);
    
    send_message('- بواسطة [البوت](https://t.me/S7_MXBOT) | تم إنشاء الويب هوك بنجاح ✅!
- أرسل /start لبدء التشغيل ♻️!', $from_id, $tk);

} elseif ($command == "del_webhook") {

    $tk = $bot["Info_from_upload"][$cr]["token"];
    $ul = $bot["Info_from_upload"][$cr]["webhook"];
    file_get_contents("https://api.telegram.org/bot$tk/deleteWebhook");
    $result = file_get_contents("https://api.telegram.org/bot$tk/getme");
    if ($result === false) {
        $text = "التوكن خاطئ ❌";
    } else {
        $text = "• تم ازالة الويبهوك ⭕";
    }

    bot('answerCallbackQuery', [
        'callback_query_id' => $update->callback_query->id,
        'text' => $text,
        'show_alert' => true
    ]);

    send_message('- بواسطة @S7_MXBOT | تم حذف الويب هوك بنجاح ✅!
• يمكنك الاشتراك لتتابع آخر التحديثات @S7_MX3 •', $from_id, $tk);
} elseif ($command == "information_bot") {
    $tk = $bot["Info_from_upload"][$cr]["token"];
    $ul = $bot["Info_from_upload"][$cr]["webhook"];
    $url = "https://api.telegram.org/bot" . $tk . "/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($output);
    if ($result->ok) {
        $bot_username = $result->result->username;
        $bot_id = $result->result->id;
        $bot_name = $result->result->first_name;
        $bot_privacy = $result->result->can_join_groups ? "Public" : "Private";
        $webhook = $result->result->webhook_url;
        bot("sendmessage",[
            "chat_id" => $chat_id, 
            "text" => "
- اسم البوت : [$bot_name](tg://user?id=$bot_id) ✓
    
- يوزر البوت 👾 : [ @$bot_username ]✓
    
- ايدي البوت 🆔 : $bot_id ✓
    
- وضع الخصوصية : $bot_privacy ✓
    
- رابط الويب هوك : ممنوع ارساله للخصوصيه ❌
",
            "parse_mode" => "markdown",
        ]);
    } else {
        bot("sendmessage",["chat_id" => $chat_id, "text" => "التوكن خاطئ ❌"]);
    }
} elseif ($command == "delete_file") {
    // تأكد من أن المسار صحيح
    $url = $bot["Info_from_upload"][$cr]["url"];
    $file_path = realpath($url);

    // تحقق من وجود الملف
    if (file_exists($file_path) && is_file($file_path)) {
        if (unlink($file_path)) {
            unset($bot["Info_from_upload"][$cr]);
$bot["from_php"][$from_id]--;
if(isset($bot["all_file"]) && $bot["all_file"] > 0) {
    $bot["all_file"]--;
}
s();

            bot('answerCallbackQuery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "• تم حذف الملف بنجاح ✅",
                'show_alert' => true
            ]);
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "• فشل في حذف الملف. يرجى التحقق من الأذونات.❌",
                'show_alert' => true
            ]);
        }
    } else {
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "• الملف تم حذفه من قبل • ❌",
            'show_alert' => true
        ]);
    }
} elseif ($command == "delete_file_all") {
    $mainFolder = __DIR__ . "/all";
    $folderToDelete = $mainFolder . DIRECTORY_SEPARATOR . $cr;
    if (realpath($folderToDelete) !== realpath($mainFolder) && strpos(realpath($folderToDelete), realpath($mainFolder)) === 0) {
        if (deleteFolder($folderToDelete)) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $update->callback_query->id,
                'text' => "
• تم حذف جميع ملفاتك بنجاح ✅
                ",
                'show_alert' => true
            ]);
        } else {
            echo "حدث خطأ";
        }
    }
}

function deleteFolder($folderPath) {
    global $update;
    if (!is_dir($folderPath)) {
        bot('answerCallbackQuery', [
            'callback_query_id' => $update->callback_query->id,
            'text' => "
• تم حذف ملفاتك من قبل أو لا يوجد ملفات حالياً ❌•
            ",
            'show_alert' => true
        ]);
        return false;
    }
    $files = array_diff(scandir($folderPath), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }
    global $bot;
$files = array_diff(scandir($folderPath), ['.', '..']);
$file_count = 0;
foreach ($files as $file) {
    $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
    if (is_file($filePath)) {
        $file_count++;
    }
}

if(isset($bot["all_file"]) && $bot["all_file"] >= $file_count) {
    $bot["all_file"] -= $file_count;
} else {
    $bot["all_file"] = 0;
}

if(isset($bot["from_php"][$cr])) {
    $bot["from_php"][$cr] = 0;
}
s();
    return rmdir($folderPath);
}