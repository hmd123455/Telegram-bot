import telebot
import sqlite3
import gdown
import os

# 1. ضع توكين البوت الخاص بك هنا
TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN'
bot = telebot.TeleBot(TOKEN)

# 2. تحميل قاعدة البيانات من الدرايف (لأول مرة فقط)
DB_URL = 'https://drive.google.com/uc?id=1mvcBXohUEqbJIUs_EnvGCsVLLyOaMYV2'
DB_NAME = 'database.db'

if not os.path.exists(DB_NAME):
    print("جاري تحميل قاعدة البيانات العملاقة... يرجى الانتظار")
    gdown.download(DB_URL, DB_NAME, quiet=False)
    print("✅ تم التحميل بنجاح!")

# 3. دالة البحث
def search_in_db(user_id):
    conn = sqlite3.connect(DB_NAME)
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM users WHERE id = ?", (str(user_id),))
    result = cursor.fetchone()
    conn.close()
    return result

# 4. أوامر البوت
@bot.message_handler(commands=['start'])
def send_welcome(message):
    bot.reply_to(message, "مرحباً! أرسل الآيدي (ID) وسأقوم بالبحث عنه في القاعدة.")

@bot.message_handler(func=lambda message: True)
def handle_query(message):
    user_id = message.text.strip()
    result = search_in_db(user_id)
    
    if result:
        bot.reply_to(message, f"✅ تم العثور على المعلومات:\n\nالاسم: {result[1]}\nالهاتف: {result[2]}")
    else:
        bot.reply_to(message, "❌ لم يتم العثور على هذا الرقم.")

if __name__ == '__main__':
    bot.polling(none_stop=True)
