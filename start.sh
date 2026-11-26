#!/usr/bin/env bash
# تشغيل البوت على GitHub Codespaces وربطه بتيليجرام تلقائياً
cd "$(dirname "$0")" || exit 1

TOKEN="${BOT_TOKEN:-$(grep -oP "BOT_TOKEN'\) \?: '\K[^']+" bot.php)}"
[ -z "$TOKEN" ] && { echo "لم أجد BOT_TOKEN"; exit 1; }

# 1) المتطلبات
if ! (php -m | grep -qi pdo_sqlite && php -m | grep -qi curl && php -m | grep -qi mbstring); then
  echo ">> تثبيت إضافات PHP..."
  sudo apt-get update -qq
  sudo apt-get install -y -qq php-cli php-curl php-sqlite3 php-mbstring php-xml php-gmp php-bcmath
fi

# 2) نفق عام مجاني (بدون حساب)
if [ ! -x ./cloudflared ]; then
  echo ">> تنزيل cloudflared..."
  curl -sL -o cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 && chmod +x cloudflared
fi

pkill -f "php -S" 2>/dev/null
pkill -f "cloudflared tunnel" 2>/dev/null
sleep 1

# 3) تشغيل السيرفر (عدة عمال لأن البوت يستدعي نفسه)
PHP_CLI_SERVER_WORKERS=8 nohup php -S 127.0.0.1:8080 bot.php > server.log 2>&1 &
nohup ./cloudflared tunnel --url http://127.0.0.1:8080 --no-autoupdate > tunnel.log 2>&1 &

# 4) انتظار رابط النفق
URL=""
for i in $(seq 1 40); do
  URL=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' tunnel.log | head -1)
  [ -n "$URL" ] && break
  sleep 1
done
[ -z "$URL" ] && { echo "فشل الحصول على رابط النفق، راجع tunnel.log"; exit 1; }

# 5) ربط الويب هوك
sleep 3
curl -s "https://api.telegram.org/bot${TOKEN}/setWebhook" --data-urlencode "url=${URL}/" --data-urlencode "drop_pending_updates=true"
echo
echo ">> جاهز. الرابط: $URL"
echo ">> أرسل /start لبوتك في تيليجرام."
