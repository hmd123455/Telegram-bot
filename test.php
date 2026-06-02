<?php
ob_start();
// التوكن مالتك شغال وصحيح 100%
$API_KEY = "7494548602:AAFE8A7XPQaAc73Lp3cB3ldwNWHc9ofyAs4";
define('API_KEY', $API_KEY);

function bot($method, $datas=[]){
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    return json_decode($res);
}

$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$chat_id = $message->chat->id;
$text = $message->text;
$name = $message->from->first_name;

if($text == '/start' || $text){
    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"هلا بيك يا $name 🤝\nالسيرفر شغال ويه التليجرام 100% والكود استجاب!"
    ]);
}
?>
