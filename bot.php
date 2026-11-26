<?php
// =========================================================================
// 0. تجاوز الدوال المعطلة في الاستضافات المشتركة (حل خطأ getenv)
// =========================================================================
namespace danog\MadelineProto {
    function getenv($name = null, $local_only = false) {
        if (\function_exists('\getenv')) {
            return $name === null ? \getenv() : \getenv($name, $local_only);
        }
        if ($name === null) {
            return $_ENV ?: $_SERVER ?: [];
        }
        return $_ENV[$name] ?? $_SERVER[$name] ?? false;
    }

    function putenv($assignment) {
        if (\function_exists('\putenv')) {
            return \putenv($assignment);
        }
        if (\strpos($assignment, '=') !== false) {
            list($k, $v) = \explode('=', $assignment, 2);
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
        return true;
    }

    function set_time_limit($seconds) {
        if (\function_exists('\set_time_limit')) {
            return @\set_time_limit($seconds);
        }
        return false;
    }
}

namespace {

// تعريف الدوال المعطلة على مستوى global (تعمل لكل مساحات الأسماء في المكتبة)
if (!function_exists('getenv')) {
    function getenv($name = null, $local_only = false) {
        if ($name === null) {
            return array_merge($_SERVER, $_ENV);
        }
        if (isset($_ENV[$name])) return $_ENV[$name];
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) return $_SERVER[$name];
        return false;
    }
}
if (!function_exists('putenv')) {
    function putenv($assignment) {
        if (strpos($assignment, '=') !== false) {
            list($k, $v) = explode('=', $assignment, 2);
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
        return true;
    }
}
if (!function_exists('set_time_limit')) {
    function set_time_limit($seconds) { return false; }
}
if (!function_exists('ini_set')) {
    function ini_set($option, $value) { return false; }
}
if (!function_exists('ini_get')) {
    function ini_get($option) { return false; }
}
if (!function_exists('madeline_assert')) {
    function madeline_assert($assertion = true, $description = null) { return true; }
}
if (!function_exists('gc_collect_cycles')) {
    function gc_collect_cycles() { return 0; }
}
if (!function_exists('ignore_user_abort')) {
    function ignore_user_abort($enable = null) { return 0; }
}

// =========================================================================
// 1. الإعدادات الأساسية (عدّل هذه البيانات فقط قبل رفع الملف)
// =========================================================================
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '9063996986:AAFPimW78jUqHONp6hoY4v5eoye3oWdW47Q');
define('ADMIN_ID', 7012982464); // ضع آيدي حسابك هنا

define('API_ID', 31105957); // ضع API_ID من my.telegram.org
define('API_HASH', 'ca49c4e79d723e65458fb5ab2c9c692e'); // ضع API_HASH من my.telegram.org

define('SECRET_KEY', md5(BOT_TOKEN . '_worker_secret'));
define('DB_PATH', __DIR__ . '/bot_data.sqlite');
define('SESSIONS_DIR', __DIR__ . '/sessions');
define('ML_SRC', __DIR__ . '/ml_src');

if (!is_dir(SESSIONS_DIR)) {
    @mkdir(SESSIONS_DIR, 0777, true);
}

// =========================================================================
// 2. قاعدة البيانات SQLite والدوال المساعدة
// =========================================================================
$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA busy_timeout = 5000;");

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    name TEXT,
    username TEXT,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    acc_num INTEGER UNIQUE,
    phone TEXT,
    session_path TEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS account_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    acc_num INTEGER,
    target_bot TEXT,
    ref_code TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(acc_num, target_bot)
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    chat_id INTEGER,
    message_id INTEGER,
    target_bot TEXT,
    ref_code TEXT,
    last_acc_num INTEGER DEFAULT 0,
    report_log TEXT DEFAULT '',
    success_count INTEGER DEFAULT 0,
    status TEXT DEFAULT 'running',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

$defaults = [
    'bot_enabled'   => '1',
    'notifications' => '1',
    'force_sub'     => '',
    'admin_state'   => '',
    'pending_acc'   => ''
];
foreach ($defaults as $k => $v) {
    $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)")->execute([$k, $v]);
}

function getSetting($key) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function setSetting($key, $val) {
    global $db;
    $db->prepare("REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $val]);
}

function botApi($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// تحميل مكتبة MadelineProto تلقائياً بدون Composer أو Shell
function loadMadeline() {
    if (is_file(ML_SRC . '/.done') && is_file(ML_SRC . '/vendor/autoload.php')) {
        require_once ML_SRC . '/vendor/autoload.php';
        return;
    }
    $madelineFile = __DIR__ . '/madeline.php';
    if (!file_exists($madelineFile) || filesize($madelineFile) < 1000) {
        $ch = curl_init('https://phar.madelineproto.xyz/madeline.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $code = curl_exec($ch);
        curl_close($ch);
        if ($code && strpos($code, '<?php') !== false) {
            file_put_contents($madelineFile, $code);
        } else {
            throw new Exception("فشل تحميل مكتبة MadelineProto من الاستضافة.");
        }
    }
    require_once $madelineFile;
}

function getMadelineInstance($sessionPath) {
    if (!is_file(ML_SRC . '/.done') && !function_exists('assert')) {
        throw new Exception('المكتبة تحتاج تجهيز للاستضافة: أرسل الأمر /patch أولاً وانتظر رسالة النجاح.');
    }
    loadMadeline();
    $settings = new \danog\MadelineProto\Settings();
    $settings->getAppInfo()->setApiId((int)API_ID)->setApiHash(API_HASH);
    $logLevel = defined('\danog\MadelineProto\Logger::LEVEL_FATAL')
        ? \danog\MadelineProto\Logger::LEVEL_FATAL
        : 0;
    $settings->getLogger()->setLevel($logLevel);
    try {
        if (method_exists($settings, 'getIpc')) {
            $settings->getIpc()->setEnable(false);
        }
    } catch (\Throwable $e) {}
    return new \danog\MadelineProto\API($sessionPath, $settings);
}


function selfUrl() {
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if (php_sapi_name() === 'cli-server') {
        return 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 8080) . $path;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($host)) return null;
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    return "{$proto}://{$host}{$path}";
}

// تشغيل الخطوة التالية في الخلفية عبر استدعاء ذاتي لتجنب مهلة الاستضافة
function triggerWorkerStep($taskId) {
    $base = selfUrl();

    if (!$base) return false;

    $url = "{$base}?run_task={$taskId}&key=" . SECRET_KEY;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    curl_close($ch);
    return true;
}

function deleteDir($dirPath) {
    if (!file_exists($dirPath)) return;
    if (is_file($dirPath)) {
        @unlink($dirPath);
        return;
    }
    $list = @scandir($dirPath);
    if (!is_array($list)) { @rmdir($dirPath); return; }
    $files = array_diff($list, ['.', '..']);
    foreach ($files as $file) {
        deleteDir($dirPath . '/' . $file);
    }
    @rmdir($dirPath);
}


// BEGIN_PATCH
function mlLog($m) {
    @file_put_contents(__DIR__ . '/ml_patch.log', date('H:i:s') . ' ' . $m . "\n", FILE_APPEND);
}

// تعديل مكتبة MadelineProto لتعمل على استضافة معطلة assert()
function patchAssertCalls($code) {
    $code = preg_replace('/(?<![\w$>:\\\\])\\\\assert\s*\(/', '\\madeline_assert(', $code);
    $code = preg_replace('/(?<![\w$>:\\\\])(?<!function )(?<!function&)assert\s*\(/', '\\madeline_assert(', $code);
    return $code;
}


// قراءة ملف phar يدوياً بدون إضافة phar (فورمات phar موثّق)
function readPharEntries($file) {
    $fh = @fopen($file, 'rb');
    if (!$fh) return [null, 'تعذر فتح الملف'];
    $head = fread($fh, 262144);
    $tok = '__HALT_COMPILER();';
    $i = strpos($head, $tok);
    if ($i === false) {
        fclose($fh);
        return [null, 'ليس ملف phar صالحاً (magic=' . bin2hex(substr($head, 0, 4)) . ')'];
    }
    $pos = $i + strlen($tok);
    if (substr($head, $pos, 3) === ' ?>') $pos += 3;
    if (substr($head, $pos, 2) === "\r\n") $pos += 2;
    elseif (substr($head, $pos, 1) === "\n") $pos += 1;
    fseek($fh, $pos);
    $b = fread($fh, 4);
    if (strlen($b) < 4) { fclose($fh); return [null, 'manifest مقطوع']; }
    $mlen = unpack('V', $b)[1];
    $man = fread($fh, $mlen);
    fclose($fh);
    if (strlen($man) < $mlen) return [null, 'manifest مقطوع (الملف ناقص)'];
    $dataStart = $pos + 4 + $mlen;
    $o = 0;
    $count = unpack('V', substr($man, $o, 4))[1]; $o += 4;
    $o += 2; // api
    $o += 4; // flags
    $al = unpack('V', substr($man, $o, 4))[1]; $o += 4 + $al;
    $ml = unpack('V', substr($man, $o, 4))[1]; $o += 4 + $ml;
    $entries = [];
    $off = $dataStart;
    for ($n = 0; $n < $count; $n++) {
        $fl = unpack('V', substr($man, $o, 4))[1]; $o += 4;
        $name = substr($man, $o, $fl); $o += $fl;
        $d = unpack('Vsize/Vtime/Vcsize/Vcrc/Vflags/Vmeta', substr($man, $o, 24)); $o += 24;
        $o += $d['meta'];
        $entries[] = [$name, $d['size'], $d['csize'], $d['crc'], $d['flags'], $off];
        $off += $d['csize'];
    }
    return [$entries, null];
}


function runNetTest() {
    $out = ["PHP " . PHP_VERSION . " | sapi=" . php_sapi_name()];
    $exts = ['openssl', 'sockets', 'mbstring', 'gmp', 'xml', 'dom', 'zlib', 'curl', 'phar', 'pcntl', 'posix', 'ev', 'uv', 'event'];
    $e = [];
    foreach ($exts as $x) $e[] = $x . '=' . (extension_loaded($x) ? '✓' : '✗');
    $out[] = implode(' ', $e);
    $fns = ['stream_socket_client', 'fsockopen', 'stream_select', 'proc_open', 'exec', 'shell_exec', 'socket_create', 'stream_set_blocking', 'openssl_encrypt'];
    $f = [];
    foreach ($fns as $x) $f[] = $x . '=' . (function_exists($x) ? '✓' : '✗');
    $out[] = implode(' ', $f);
    $out[] = 'Fiber=' . (class_exists('Fiber') ? '✓' : '✗');

    $targets = [
        ['149.154.167.50', 443], ['149.154.167.51', 443], ['149.154.167.50', 80],
        ['149.154.175.50', 443], ['venus.web.telegram.org', 443], ['api.telegram.org', 443],
    ];
    foreach ($targets as $t) {
        $t0 = microtime(true);
        $r = false; $errno = 0; $errstr = '';
        if (function_exists('stream_socket_client')) {
            $r = @stream_socket_client('tcp://' . $t[0] . ':' . $t[1], $errno, $errstr, 5);
        } elseif (function_exists('fsockopen')) {
            $r = @fsockopen($t[0], $t[1], $errno, $errstr, 5);
        } else {
            $errstr = 'no socket function';
        }
        $ms = round((microtime(true) - $t0) * 1000);
        $out[] = $t[0] . ':' . $t[1] . ' → ' . ($r ? "OK ({$ms}ms)" : "FAIL ($errno $errstr, {$ms}ms)");
        if ($r) fclose($r);
    }
    return implode("\n", $out);
}

function listDirSafe($dir) {
    $out = [];
    try {
        foreach (new DirectoryIterator($dir) as $f) {
            if ($f->isDot()) continue;
            $out[] = $f->getPathname();
        }
    } catch (\Throwable $e) {}
    return $out;
}

function findMadelinePhar() {
    foreach (get_included_files() as $f) {
        if (preg_match('#^phar://(.+?\.phar)(?:/|$)#', $f, $m) && is_file($m[1])) return $m[1];
    }
    $cands = [];
    foreach (listDirSafe(__DIR__) as $f) {
        if (substr($f, -5) === '.phar' && is_file($f)) $cands[] = $f;
        elseif (is_dir($f)) {
            foreach (listDirSafe($f) as $g) {
                if (substr($g, -5) === '.phar' && is_file($g)) $cands[] = $g;
            }
        }
    }
    if (!$cands) return null;
    usort($cands, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    return $cands[0];
}

// يعيد: done | more | nophar
function patchMadelineStep($budget = 15) {
    $start = microtime(true);
    mlLog('step start');
    if (is_file(ML_SRC . '/.done')) return 'done';

    $phar = findMadelinePhar();
    if (!$phar) {
        mlLog('no phar yet, running loader');
        try { loadMadeline(); } catch (\Throwable $e) { mlLog('loader exc: ' . $e->getMessage()); } // ينزّل المكتبة
        $phar = findMadelinePhar();
        mlLog('after loader, phar=' . var_export($phar, true));
        if (!$phar) return 'nophar';
    }
    mlLog('phar=' . $phar . ' size=' . @filesize($phar));
    if (!is_dir(ML_SRC)) @mkdir(ML_SRC, 0777, true);
    $copied = 0;

    list($entries, $err) = readPharEntries($phar);
    if ($entries === null) {
        mlLog('phar parse error: ' . $err);
        return 'error:' . $err;
    }
    mlLog('phar entries=' . count($entries));
    $fh = fopen($phar, 'rb');
    foreach ($entries as $e) {
        list($name, $size, $csize, $crc, $flags, $off) = $e;
        if ($name === '' || substr($name, -1) === '/' || strpos($name, '..') !== false) continue;
        $rel = ltrim($name, '/');
        $dst = ML_SRC . '/' . $rel;
        if (is_file($dst)) continue;
        if (microtime(true) - $start > $budget) { fclose($fh); mlLog('more, copied=' . $copied); return 'more'; }
        fseek($fh, $off);
        $data = $csize > 0 ? fread($fh, $csize) : '';
        if (strlen($data) !== $csize) {
            fclose($fh);
            @unlink($phar);
            mlLog('truncated phar at ' . $rel);
            return 'error:ملف المكتبة المنزّل ناقص/تالف وتم حذفه. أرسل /patch لإعادة التنزيل.';
        }
        if ($flags & 0x1000) {
            $data = @gzinflate($data);
        } elseif ($flags & 0x2000) {
            $data = @bzdecompress($data);
        }
        if ($data === false || !is_string($data) || sprintf('%u', crc32($data)) !== sprintf('%u', $crc)) {
            fclose($fh);
            @unlink($phar);
            mlLog('crc/decompress fail at ' . $rel);
            return 'error:ملف المكتبة تالف (فشل فك الضغط) وتم حذفه. أرسل /patch لإعادة التنزيل.';
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        if (substr($rel, -4) === '.php') $data = patchAssertCalls($data);
        $tmp = $dst . '.tmp';
        file_put_contents($tmp, $data);
        rename($tmp, $dst);
        $copied++;
    }
    fclose($fh);
    mlLog('finished loop, copied=' . $copied);
    if (!is_file(ML_SRC . '/vendor/autoload.php')) return 'nophar';
    file_put_contents(ML_SRC . '/.done', '1');
    return 'done';
}

function triggerPatchStep($chatId, $msgId, $try = 0) {
    $base = selfUrl();
    if (!$base) return false;
    $url = "{$base}?patch_ml=1&chat={$chatId}&msg={$msgId}&try={$try}&key=" . SECRET_KEY;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    @curl_exec($ch);
    curl_close($ch);
    return true;
}

function runPatchJob($pChat, $pMsg, $pTry, $auto) {
    register_shutdown_function(function () use ($pChat, $pMsg, $pTry, $auto) {
        $e = error_get_last();
        if (!$e || !in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) return;
        mlLog('FATAL: ' . $e['message'] . ' @' . basename($e['file']) . ':' . $e['line']);
        if (stripos($e['message'], 'Maximum execution time') !== false) {
            if ($auto && $pTry < 60) { triggerPatchStep($pChat, $pMsg, $pTry + 1); return; }
            botApi('editMessageText', ['chat_id' => $pChat, 'message_id' => $pMsg,
                'text' => "⏳ انتهت مهلة الاستضافة قبل الاكتمال (التقدم محفوظ). أرسل /patch مرة أخرى للمتابعة."]);
            return;
        }
        botApi('editMessageText', [
            'chat_id' => $pChat, 'message_id' => $pMsg,
            'text' => "❌ توقف التنفيذ بخطأ:\n" . mb_substr($e['message'], 0, 350) . "\n(" . basename($e['file']) . ':' . $e['line'] . ')'
        ]);
    });
    mlLog('job start auto=' . ($auto ? 1 : 0) . ' try=' . $pTry);
    try {
        $res = patchMadelineStep(18);
    } catch (\Throwable $e) {
        $res = 'error:' . $e->getMessage();
        mlLog('EXC ' . $e->getMessage());
    }
    mlLog('job result=' . $res);
    if ($res === 'more') {
        if ($auto && $pTry < 200) {
            triggerPatchStep($pChat, $pMsg, $pTry + 1);
        } else {
            botApi('editMessageText', ['chat_id' => $pChat, 'message_id' => $pMsg,
                'text' => "⏳ تمت دفعة من التجهيز ولم تكتمل بعد. أرسل /patch مرة أخرى للمتابعة (كرر حتى تصلك رسالة النجاح)."]);
        }
        return;
    }
    if ($res === 'done') {
        $msgTxt = "✅ تم تجهيز المكتبة بنجاح! الآن جرّب إضافة الحساب برقم الهاتف من لوحة الحسابات.";
    } elseif ($res === 'nophar') {
        $msgTxt = "❌ تعذر العثور على ملف المكتبة (phar) بعد تنزيلها. أرسل /patchlog.";
    } else {
        $msgTxt = "❌ فشل التجهيز:\n" . $res;
    }
    botApi('editMessageText', ['chat_id' => $pChat, 'message_id' => $pMsg, 'text' => $msgTxt]);
}
// END_PATCH

if (isset($_GET['patch_ml']) && ($_GET['key'] ?? '') === SECRET_KEY) {
    ignore_user_abort(true);
    @set_time_limit(0);
    header("Connection: close");
    header("Content-Length: 0");
    @ob_end_flush();
    @flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

    runPatchJob((int)($_GET['chat'] ?? 0), (int)($_GET['msg'] ?? 0), (int)($_GET['try'] ?? 0), true);
    exit;
}

// =========================================================================
// 3. معالج تنفيذ الإحالات في الخلفية (يعالج حساباً واحداً بكل طلب)
// =========================================================================
if (isset($_GET['run_task']) && ($_GET['key'] ?? '') === SECRET_KEY) {
    ignore_user_abort(true);
    @set_time_limit(0);

    header("Connection: close");
    header("Content-Length: 0");
    @ob_end_flush();
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $taskId = (int)$_GET['run_task'];
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND status = 'running'");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) exit;

    $chatId       = $task['chat_id'];
    $msgId        = $task['message_id'];
    $targetBot    = $task['target_bot'];
    $refCode      = $task['ref_code'];
    $reqUser      = $task['user_id'];
    $lastAccNum   = (int)$task['last_acc_num'];
    $reportLines  = $task['report_log'] ? explode("\n", $task['report_log']) : [];
    $successCount = (int)$task['success_count'];

    $accStmt = $db->prepare("SELECT * FROM accounts WHERE status = 'active' AND acc_num > ? ORDER BY acc_num ASC LIMIT 1");
    $accStmt->execute([$lastAccNum]);
    $acc = $accStmt->fetch(PDO::FETCH_ASSOC);

    if (!$acc) {
        $db->prepare("UPDATE tasks SET status = 'done' WHERE id = ?")->execute([$taskId]);
        $finalText = "🏁 <b>اكتملت عملية فحص وتفعيل الإحالة لـ @$targetBot:</b>\n\n"
                   . implode("\n", $reportLines)
                   . "\n\n✨ تم الانتهاء من فحص جميع الحسابات.";

        botApi('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $msgId,
            'text'       => $finalText,
            'parse_mode' => 'HTML'
        ]);

        if ($reqUser != ADMIN_ID && getSetting('notifications') == '1') {
            botApi('sendMessage', [
                'chat_id' => ADMIN_ID,
                'text' => "📊 <b>تقرير تفعيل إحالة:</b>\n• المستخدم: <code>$reqUser</code>\n• البوت: @$targetBot\n• الحسابات الناجحة: <b>$successCount</b>",
                'parse_mode' => 'HTML'
            ]);
        }
        exit;
    }

    $accNum      = (int)$acc['acc_num'];
    $sessionPath = $acc['session_path'];

    $chk = $db->prepare("SELECT COUNT(*) FROM account_logs WHERE acc_num = ? AND target_bot = ?");
    $chk->execute([$accNum, $targetBot]);

    if ($chk->fetchColumn() > 0) {
        $reportLines[] = "⚠️ <b>الحساب رقم ($accNum)</b> مفعل هذا البوت من قبل.";
    } else {
        try {
            if (!file_exists($sessionPath)) {
                throw new Exception("ملف الجلسة غير موجود");
            }

            $MadelineProto = getMadelineInstance($sessionPath);
            $MadelineProto->start();

            $MadelineProto->messages->startBot([
                'bot'         => '@' . $targetBot,
                'peer'        => '@' . $targetBot,
                'start_param' => $refCode
            ]);

            sleep(3);

            $history = $MadelineProto->messages->getHistory([
                'peer'  => '@' . $targetBot,
                'limit' => 2
            ]);

            $channelsToJoin = [];
            $verifyCallbackData = null;
            $verifyMsgId = null;

            foreach ($history['messages'] ?? [] as $m) {
                $mText = $m['message'] ?? '';
                preg_match_all('/(?:t\.me\/|@)([a-zA-Z0-9_+]+)/i', $mText, $textMatches);
                foreach ($textMatches[1] ?? [] as $found) {
                    $channelsToJoin[] = $found;
                }

                if (!empty($m['reply_markup']['rows'])) {
                    foreach ($m['reply_markup']['rows'] as $row) {
                        foreach ($row['buttons'] as $btn) {
                            if (!empty($btn['url']) && preg_match('/t\.me\/([a-zA-Z0-9_+]+)/i', $btn['url'], $uMatch)) {
                                $channelsToJoin[] = $uMatch[1];
                            }
                            if (!empty($btn['data'])) {
                                $btnTxt = $btn['text'] ?? '';
                                if (preg_match('/(تحقق|اشتراك|تأكيد|تم|verify|check|done)/iu', $btnTxt)) {
                                    $verifyCallbackData = $btn['data'];
                                    $verifyMsgId = $m['id'];
                                }
                            }
                        }
                    }
                }
            }

            $channelsToJoin = array_unique($channelsToJoin);
            foreach ($channelsToJoin as $ch) {
                if (strtolower($ch) == $targetBot || strtolower($ch) == 'share') continue;
                try {
                    if (strpos($ch, '+') === 0) {
                        $MadelineProto->messages->importChatInvite(['hash' => ltrim($ch, '+')]);
                    } else {
                        $MadelineProto->channels->joinChannel(['channel' => '@' . $ch]);
                    }
                    sleep(2);
                } catch (\Throwable $e) {}
            }

            if (!empty($channelsToJoin)) {
                if ($verifyCallbackData && $verifyMsgId) {
                    try {
                        $MadelineProto->messages->getBotCallbackAnswer([
                            'peer'   => '@' . $targetBot,
                            'msg_id' => $verifyMsgId,
                            'data'   => $verifyCallbackData
                        ]);
                    } catch (\Throwable $e) {}
                }
                sleep(2);
                $MadelineProto->messages->startBot([
                    'bot'         => '@' . $targetBot,
                    'peer'        => '@' . $targetBot,
                    'start_param' => $refCode
                ]);
            }

            $db->prepare("INSERT OR IGNORE INTO account_logs (acc_num, target_bot, ref_code) VALUES (?, ?, ?)")
               ->execute([$accNum, $targetBot, $refCode]);

            $reportLines[] = "✅ <b>الحساب رقم ($accNum)</b> تم الدخول وتفعيل الإحالة بنجاح.";
            $successCount++;
            unset($MadelineProto);

            sleep(5);

        } catch (\Throwable $e) {
            $reportLines[] = "❌ <b>الحساب رقم ($accNum)</b> تعذر التفعيل (الجلسة غير صالحة أو مقيدة).";
        }
    }

    $newReportStr = implode("\n", $reportLines);
    $db->prepare("UPDATE tasks SET last_acc_num = ?, report_log = ?, success_count = ? WHERE id = ?")
       ->execute([$accNum, $newReportStr, $successCount, $taskId]);

    $progressText = "🔄 <b>جاري تنفيذ الإحالة لـ @$targetBot...</b>\n\n"
                  . $newReportStr
                  . "\n\n⏳ جاري الانتقال للحساب التالي...";
    botApi('editMessageText', [
        'chat_id'    => $chatId,
        'message_id' => $msgId,
        'text'       => $progressText,
        'parse_mode' => 'HTML'
    ]);

    triggerWorkerStep($taskId);
    exit;
}

// =========================================================================
// 4. استقبال تحديثات تيليجرام (Webhook) ولوحتي الأدمن
// =========================================================================
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message  = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

$chat_id = $message['chat']['id'] ?? $callback['message']['chat']['id'] ?? null;
$user_id = $message['from']['id'] ?? $callback['from']['id'] ?? null;
$msg_id  = $message['message_id'] ?? $callback['message']['message_id'] ?? null;
$text    = trim($message['text'] ?? '');
$data    = $callback['data'] ?? '';
$name    = $message['from']['first_name'] ?? $callback['from']['first_name'] ?? 'مستخدم';
$uname   = $message['from']['username'] ?? $callback['from']['username'] ?? '';

if (!$user_id) exit;
$isAdmin = ($user_id == ADMIN_ID);

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() == 0) {
    $db->prepare("INSERT INTO users (user_id, name, username) VALUES (?, ?, ?)")->execute([$user_id, $name, $uname]);

    if (!$isAdmin && getSetting('notifications') == '1') {
        $uTag = $uname ? "@$uname" : "لا يوجد";
        botApi('sendMessage', [
            'chat_id' => ADMIN_ID,
            'text' => "🔔 <b>عضو جديد دخل البوت:</b>\n• الاسم: " . htmlspecialchars($name) . "\n• اليوزر: $uTag\n• الآيدي: <code>$user_id</code>",
            'parse_mode' => 'HTML'
        ]);
    }
}

if (!$isAdmin && getSetting('bot_enabled') == '0') {
    if ($message) {
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ البوت متوقف حالياً للصيانة، يرجى المحاولة لاحقاً."]);
    }
    exit;
}

$forceChannel = getSetting('force_sub');
if (!$isAdmin && !empty($forceChannel)) {
    $member = botApi('getChatMember', ['chat_id' => '@' . ltrim($forceChannel, '@'), 'user_id' => $user_id]);
    $status = $member['result']['status'] ?? 'left';
    if (in_array($status, ['left', 'kicked'])) {
        $chClean = ltrim($forceChannel, '@');
        botApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🔒 <b>عذراً عزيزي، يجب عليك الاشتراك في قناة البوت أولاً لتتمكن من استخدامه:</b>\n\n👉 @$chClean",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'اشترك في القناة 📢', 'url' => "https://t.me/$chClean"]],
                    [['text' => 'تحقق من الاشتراك ✅', 'callback_data' => 'check_sub']]
                ]
            ])
        ]);
        exit;
    }
}

if ($data == 'check_sub') {
    botApi('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'تم التحقق بنجاح!']);
    botApi('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $msg_id,
        'text' => "✅ تم التحقق من اشتراكك! أرسل الآن رابط الإحالة الخاص ببوت التمويل."
    ]);
    exit;
}

function showMainAdmin($chat_id, $msg_id = null) {
    $text = "🛠 <b>لوحة تحكم الأدمن الرئيسية</b>\nاختر اللوحة المطلوبة من الأزرار أدناه:";
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '⚙️ لوحة إعدادات البوت', 'callback_data' => 'panel_settings']],
            [['text' => '📱 لوحة إدارة الحسابات (الأرقام)', 'callback_data' => 'panel_accounts']]
        ]
    ]);
    if ($msg_id) {
        botApi('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
    } else {
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
    }
}

function showSettingsPanel($chat_id, $msg_id) {
    $botState   = getSetting('bot_enabled') == '1' ? 'مفعل ✅' : 'معطل ❌';
    $notifState = getSetting('notifications') == '1' ? 'مفعلة 🔔' : 'معطلة 🔕';
    $subCh      = getSetting('force_sub') ? '@' . getSetting('force_sub') : 'غير محدد';

    $text = "⚙️ <b>لوحة إعدادات البوت:</b>\n\n"
          . "• حالة الدخول للبوت: <b>$botState</b>\n"
          . "• إشعارات الأدمن: <b>$notifState</b>\n"
          . "• الاشتراك الإجباري: <b>$subCh</b>";

    $kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '📊 إحصائيات البوت', 'callback_data' => 'st_stats'],
                ['text' => '📢 إذاعة للكل', 'callback_data' => 'st_broadcast']
            ],
            [
                ['text' => '🔒 تعيين اشتراك إجباري', 'callback_data' => 'st_set_sub'],
                ['text' => '🗑 حذف الاشتراك', 'callback_data' => 'st_del_sub']
            ],
            [
                ['text' => '🔔 تفعيل/تعطيل الإشعارات', 'callback_data' => 'st_toggle_notif'],
                ['text' => '🚪 تفعيل/تعطيل الدخول', 'callback_data' => 'st_toggle_bot']
            ],
            [
                ['text' => '🔙 رجوع للقائمة الرئيسية', 'callback_data' => 'admin_home']
            ]
        ]
    ]);
    botApi('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
}

function showAccountsPanel($chat_id, $msg_id) {
    global $db;
    $activeAcc = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'active'")->fetchColumn();

    $text = "📱 <b>لوحة إدارة الحسابات (الأرقام):</b>\n\n"
          . "• الحسابات النشطة حالياً: <b>$activeAcc</b>\n\n"
          . "يمكنك إضافة حساباتك مباشرة برقم الهاتف أو عبر كود الجلسة:";

    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '📲 إضافة حساب (برقم الهاتف والكود)', 'callback_data' => 'acc_add_phone']],
            [['text' => '🔑 إضافة حساب (بكود جلسة جاهز)', 'callback_data' => 'acc_add_session']],
            [
                ['text' => '📋 عرض الحسابات', 'callback_data' => 'acc_list'],
                ['text' => '🗑 حذف حساب', 'callback_data' => 'acc_delete']
            ],
            [['text' => '🧹 تصفير سجل بوت معين', 'callback_data' => 'acc_clear_bot']],
            [['text' => '🔙 رجوع للقائمة الرئيسية', 'callback_data' => 'admin_home']]
        ]
    ]);
    botApi('editMessageText', ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
}

// =========================================================================
// 5. أوامر وأزرار الأدمن
// =========================================================================
if ($isAdmin) {
    if ($text == '/admin' || $text == '/start') {
        setSetting('admin_state', '');
        showMainAdmin($chat_id);
        exit;
    }

    if ($callback) {
        botApi('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        setSetting('admin_state', '');

        if ($data == 'admin_home') showMainAdmin($chat_id, $msg_id);
        elseif ($data == 'panel_settings') showSettingsPanel($chat_id, $msg_id);
        elseif ($data == 'panel_accounts') showAccountsPanel($chat_id, $msg_id);

        elseif ($data == 'st_toggle_bot') {
            setSetting('bot_enabled', getSetting('bot_enabled') == '1' ? '0' : '1');
            showSettingsPanel($chat_id, $msg_id);
        }
        elseif ($data == 'st_toggle_notif') {
            setSetting('notifications', getSetting('notifications') == '1' ? '0' : '1');
            showSettingsPanel($chat_id, $msg_id);
        }
        elseif ($data == 'st_del_sub') {
            setSetting('force_sub', '');
            showSettingsPanel($chat_id, $msg_id);
        }
        elseif ($data == 'st_set_sub') {
            setSetting('admin_state', 'awaiting_sub');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "📢 أرسل الآن يوزر القناة بدون @ (تأكد أن البوت مشرف فيها):",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_settings']]]])
            ]);
        }
        elseif ($data == 'st_broadcast') {
            setSetting('admin_state', 'awaiting_broadcast');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "📣 أرسل الآن نص الإذاعة ليتم إرساله لجميع المشتركين:",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_settings']]]])
            ]);
        }
        elseif ($data == 'st_stats') {
            $usersCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $accCount   = $db->query("SELECT COUNT(*) FROM accounts WHERE status='active'")->fetchColumn();
            $doneCount  = $db->query("SELECT COUNT(*) FROM account_logs")->fetchColumn();

            $statTxt = "📊 <b>إحصائيات البوت التفصيلية:</b>\n\n"
                     . "👥 عدد المشتركين: <b>$usersCount</b>\n"
                     . "📱 الحسابات النشطة: <b>$accCount</b>\n"
                     . "✅ إجمالي الإحالات المنفذة: <b>$doneCount</b>";
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => $statTxt, 'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'panel_settings']]]])
            ]);
        }
        elseif ($data == 'acc_add_phone') {
            setSetting('admin_state', 'awaiting_phone');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "📲 <b>إضافة حساب برقم الهاتف:</b>\n\nأرسل الآن رقم الهاتف مع مفتاح الدولة (مثال: <code>+9647701234567</code>):",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_accounts']]]])
            ]);
        }
        elseif ($data == 'acc_add_session') {
            setSetting('admin_state', 'awaiting_session');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "🔑 <b>إضافة حساب بكود جلسة:</b>\n\nأرسل الآن <b>كود الجلسة</b> الخاص بالحساب:",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_accounts']]]])
            ]);
        }
        elseif ($data == 'acc_list') {
            $rows = $db->query("SELECT * FROM accounts WHERE status = 'active' ORDER BY acc_num ASC")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                $listTxt = "📭 لا توجد حسابات نشطة مضافة حالياً.";
            } else {
                $listTxt = "📋 <b>قائمة الحسابات المضافة:</b>\n\n";
                foreach ($rows as $r) {
                    $cStmt = $db->prepare("SELECT COUNT(*) FROM account_logs WHERE acc_num = ?");
                    $cStmt->execute([$r['acc_num']]);
                    $usedBots = $cStmt->fetchColumn();
                    $ph = $r['phone'] ? " ({$r['phone']})" : "";
                    $listTxt .= "• <b>حساب رقم ({$r['acc_num']})</b>$ph | فعّل: <b>$usedBots</b> بوت\n";
                }
            }
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => $listTxt, 'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 رجوع', 'callback_data' => 'panel_accounts']]]])
            ]);
        }
        elseif ($data == 'acc_delete') {
            setSetting('admin_state', 'awaiting_del_acc');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "🗑 أرسل <b>رقم الحساب</b> (مثلاً: <code>1</code>) لحذفه من البوت:",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_accounts']]]])
            ]);
        }
        elseif ($data == 'acc_clear_bot') {
            setSetting('admin_state', 'awaiting_clear_bot');
            botApi('editMessageText', [
                'chat_id' => $chat_id, 'message_id' => $msg_id,
                'text' => "🧹 أرسل يوزر بوت التمويل (بدون @) لمسح سجل التفعيل الخاص به:",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 إلغاء', 'callback_data' => 'panel_accounts']]]])
            ]);
        }
        exit;
    }

    if ($text == '/patch') {
        setSetting('admin_state', '');
        $r = botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ جاري تنزيل وتجهيز المكتبة للاستضافة... قد يستغرق دقيقة أو أكثر، ستصلك رسالة عند الانتهاء."]);
        ignore_user_abort(true);
        @set_time_limit(0);
        header("Connection: close");
        header("Content-Length: 0");
        @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        runPatchJob($chat_id, $r['result']['message_id'] ?? 0, 0, false);
        exit;
    }

    if ($text == '/patchlog') {
        $l = @file_get_contents(__DIR__ . '/ml_patch.log');
        $info = "done=" . (is_file(ML_SRC . '/.done') ? 'yes' : 'no')
              . " | max_exec=" . var_export(@ini_get('max_execution_time'), true)
              . " | mem=" . var_export(@ini_get('memory_limit'), true)
              . " | dir_writable=" . (is_writable(__DIR__) ? 'yes' : 'no');
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => $info . "\n\n" . ($l ? mb_substr($l, -3000) : 'لا يوجد سجل بعد')]);
        exit;
    }

    if ($text == '/nettest') {
        setSetting('admin_state', '');
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ جاري فحص الشبكة والإضافات (حتى 30 ثانية)..."]);
        ignore_user_abort(true);
        @set_time_limit(0);
        header("Connection: close");
        header("Content-Length: 0");
        @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { $rep = runNetTest(); } catch (\Throwable $e) { $rep = 'خطأ: ' . $e->getMessage(); }
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => $rep]);
        exit;
    }

    $state = getSetting('admin_state');

    if ($state == 'awaiting_sub' && !empty($text)) {
        $cleanCh = ltrim($text, '@');
        setSetting('force_sub', $cleanCh);
        setSetting('admin_state', '');
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ تم تعيين قناة الاشتراك الإجباري: @$cleanCh"]);
        showMainAdmin($chat_id);
        exit;
    }

    if ($state == 'awaiting_broadcast' && !empty($text)) {
        setSetting('admin_state', '');
        $users = $db->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $sent = 0;
        foreach ($users as $uid) {
            $res = botApi('sendMessage', ['chat_id' => $uid, 'text' => $text]);
            if (!empty($res['ok'])) $sent++;
        }
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ تمت الإذاعة بنجاح إلى <b>$sent</b> مشترك.", 'parse_mode' => 'HTML']);
        exit;
    }

    if ($state == 'awaiting_phone' && !empty($text)) {
        $phone = preg_replace('/[^0-9+]/', '', $text);
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ جاري الاتصال بتيليجرام وإرسال كود التحقق إلى $phone ..."]);

        try {
            $lastNum = (int)$db->query("SELECT MAX(acc_num) FROM accounts WHERE status = 'active'")->fetchColumn();
            $nextNum = $lastNum + 1;
            $sessionPath = SESSIONS_DIR . "/acc_{$nextNum}.madeline";
            deleteDir($sessionPath);

            $MadelineProto = getMadelineInstance($sessionPath);
            $MadelineProto->phoneLogin($phone);

            $db->prepare("REPLACE INTO accounts (acc_num, phone, session_path, status) VALUES (?, ?, ?, 'pending')")
               ->execute([$nextNum, $phone, $sessionPath]);

            setSetting('pending_acc', (string)$nextNum);
            setSetting('admin_state', 'awaiting_login_code');

            botApi('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📩 <b>تم إرسال كود التحقق إلى حسابك في تيليجرام!</b>\n\n"
                        . "أرسل الكود الآن (⚠️ <b>مهم:</b> ضع مسافات بين الأرقام مثل <code>1 2 3 4 5</code> حتى لا يقوم تيليجرام بإلغاء الكود تلقائياً):",
                'parse_mode' => 'HTML'
            ]);
        } catch (\Throwable $e) {
            setSetting('admin_state', '');
            botApi('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ <b>حدث خطأ أثناء إرسال الكود:</b>\n<code>" . htmlspecialchars($e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()) . "</code>",
                'parse_mode' => 'HTML'
            ]);
        }
        exit;
    }

    if ($state == 'awaiting_login_code' && !empty($text)) {
        $code = preg_replace('/\D/', '', $text);
        $accNum = (int)getSetting('pending_acc');
        $stmt = $db->prepare("SELECT session_path FROM accounts WHERE acc_num = ?");
        $stmt->execute([$accNum]);
        $sessionPath = $stmt->fetchColumn();

        try {
            $MadelineProto = getMadelineInstance($sessionPath);
            $auth = $MadelineProto->completePhoneLogin($code);

            if (($auth['_'] ?? '') === 'account.password') {
                setSetting('admin_state', 'awaiting_2fa');
                botApi('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "🔐 هذا الحساب محمي بالتحقق بخطوتين.\nأرسل الآن <b>كلمة مرور التحقق بخطوتين (2FA)</b>:",
                    'parse_mode' => 'HTML'
                ]);
            } else {
                $db->prepare("UPDATE accounts SET status = 'active' WHERE acc_num = ?")->execute([$accNum]);
                setSetting('admin_state', '');
                setSetting('pending_acc', '');
                botApi('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✅ <b>تم تسجيل الدخول وتفعيل الحساب بنجاح!</b>\n• الرقم التسلسلي: <b>حساب رقم ($accNum)</b>",
                    'parse_mode' => 'HTML'
                ]);
            }
        } catch (\Throwable $e) {
            setSetting('admin_state', '');
            botApi('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ <b>فشل التحقق من الكود:</b>\n<code>" . htmlspecialchars($e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()) . "</code>",
                'parse_mode' => 'HTML'
            ]);
        }
        exit;
    }

    if ($state == 'awaiting_2fa' && !empty($text)) {
        $accNum = (int)getSetting('pending_acc');
        $stmt = $db->prepare("SELECT session_path FROM accounts WHERE acc_num = ?");
        $stmt->execute([$accNum]);
        $sessionPath = $stmt->fetchColumn();

        try {
            $MadelineProto = getMadelineInstance($sessionPath);
            $MadelineProto->complete2falogin($text);

            $db->prepare("UPDATE accounts SET status = 'active' WHERE acc_num = ?")->execute([$accNum]);
            setSetting('admin_state', '');
            setSetting('pending_acc', '');
            botApi('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ <b>تم تأكيد كلمة المرور وتفعيل الحساب بنجاح!</b>\n• الرقم التسلسلي: <b>حساب رقم ($accNum)</b>",
                'parse_mode' => 'HTML'
            ]);
        } catch (\Throwable $e) {
            setSetting('admin_state', '');
            botApi('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ <b>كلمة المرور غير صحيحة:</b>\n<code>" . htmlspecialchars($e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()) . "</code>",
                'parse_mode' => 'HTML'
            ]);
        }
        exit;
    }

    if ($state == 'awaiting_session' && !empty($text)) {
        setSetting('admin_state', '');
        $decoded = base64_decode($text, true);
        if (!$decoded || strlen($decoded) < 50) {
            botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ كود الجلسة غير صالح! تأكد من نسخه بشكل كامل."]);
            exit;
        }

        $lastNum = (int)$db->query("SELECT MAX(acc_num) FROM accounts WHERE status = 'active'")->fetchColumn();
        $nextNum = $lastNum + 1;
        $sessionPath = SESSIONS_DIR . "/acc_{$nextNum}.madeline";

        file_put_contents($sessionPath, $decoded);
        $db->prepare("REPLACE INTO accounts (acc_num, session_path, status) VALUES (?, ?, 'active')")->execute([$nextNum, $sessionPath]);

        botApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ <b>تمت إضافة الحساب بنجاح!</b>\n• الرقم التسلسلي: <b>حساب رقم ($nextNum)</b>",
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    if ($state == 'awaiting_del_acc' && is_numeric($text)) {
        setSetting('admin_state', '');
        $num = (int)$text;
        $stmt = $db->prepare("SELECT session_path FROM accounts WHERE acc_num = ?");
        $stmt->execute([$num]);
        $path = $stmt->fetchColumn();

        if ($path) {
            deleteDir($path);
            $db->prepare("DELETE FROM accounts WHERE acc_num = ?")->execute([$num]);
            $db->prepare("DELETE FROM account_logs WHERE acc_num = ?")->execute([$num]);
            botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "🗑 تم حذف <b>حساب رقم ($num)</b> وسجلاته بنجاح.", 'parse_mode' => 'HTML']);
        } else {
            botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ لا يوجد حساب بهذا الرقم."]);
        }
        exit;
    }

    if ($state == 'awaiting_clear_bot' && !empty($text)) {
        setSetting('admin_state', '');
        $botName = strtolower(ltrim($text, '@'));
        $db->prepare("DELETE FROM account_logs WHERE target_bot = ?")->execute([$botName]);
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "🧹 تم مسح سجل البوت <b>@$botName</b> من جميع الحسابات.", 'parse_mode' => 'HTML']);
        exit;
    }
}

// =========================================================================
// 6. واجهة المستخدم واستقبال روابط الإحالة
// =========================================================================
if ($text == '/start') {
    botApi('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "👋 <b>أهلاً بك في بوت تفعيل الإحالات!</b>\n\n"
                . "أرسل الآن <b>رابط الإحالة</b> الخاص ببوت التمويل (مثال:\n<code>https://t.me/ExampleBot?start=12345</code>)\n\n"
                . "وسيقوم البوت بإدخال الحسابات المتاحة والاشتراك بالقنوات الإجبارية تلقائياً.",
        'parse_mode' => 'HTML'
    ]);
    exit;
}

if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:t\.me|telegram\.me)\/([a-zA-Z0-9_]+)\?start=([a-zA-Z0-9_=-]+)/i', $text, $matches)) {
    $targetBot = strtolower($matches[1]);
    $refCode   = $matches[2];

    $activeCount = (int)$db->query("SELECT COUNT(*) FROM accounts WHERE status = 'active'")->fetchColumn();
    if ($activeCount == 0) {
        botApi('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ لا توجد حسابات مضافة في البوت حالياً، يرجى مراجعة الإدارة."]);
        exit;
    }

    $sentMsg = botApi('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "⏳ <b>تم استلام رابط الإحالة!</b>\n"
                . "• البوت المستهدف: <b>@$targetBot</b>\n"
                . "• كود الإحالة: <code>$refCode</code>\n\n"
                . "🔄 جاري فحص الحسابات وبدء التفعيل...",
        'parse_mode' => 'HTML'
    ]);

    $statusMsgId = $sentMsg['result']['message_id'] ?? 0;
    $db->prepare("INSERT INTO tasks (user_id, chat_id, message_id, target_bot, ref_code, status) VALUES (?, ?, ?, ?, ?, 'running')")
       ->execute([$user_id, $chat_id, $statusMsgId, $targetBot, $refCode]);

    $taskId = $db->lastInsertId();
    triggerWorkerStep($taskId);
    exit;
}

if (!empty($text)) {
    botApi('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "❌ <b>الرابط غير صحيح!</b>\nيرجى إرسال رابط إحالة صحيح بهذا الشكل:\n<code>https://t.me/BotUser?start=123456</code>",
        'parse_mode' => 'HTML'
    ]);
}
}
