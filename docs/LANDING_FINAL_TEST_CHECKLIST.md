# چک‌لیست نهایی لندینگ و Attribution

- [ ] ورود با `?u=slug` معتبر یک کلیک معتبر ثبت می‌کند.
- [ ] ورود با `?u=slug` روی مقصد ناهماهنگ هیچ کلیکی ثبت نمی‌کند.
- [ ] preview botهای واضح مثل `facebookexternalhit` و `TelegramBot` ثبت نمی‌شوند.
- [ ] preview واتساپ فقط وقتی الگوی preview/crawler دارد حذف می‌شود و مرورگر واقعی کاربر بی‌جهت حذف نمی‌شود.
- [ ] `crpcrm_first_touch` فقط در اولین ورود معتبر ساخته می‌شود.
- [ ] `crpcrm_last_touch` در ورود معتبر جدید به‌روزرسانی می‌شود.
- [ ] بعد از OTP یا اولین بارگذاری پرتال، attribution معتبر روی customer ذخیره می‌شود.
- [ ] اگر مشتری بعداً request ثبت کند، `request_source` و `request_campaign` از landing معتبر به‌جای `direct` پر می‌شوند.
- [ ] request پیگیری خودکار 24 ساعته attribution ذخیره‌شده روی customer را به ارث می‌برد.
- [ ] آمار هر لندینگ از `request_landing_slug` و `request_landing_id` خوانده می‌شود و به `LIKE` خام روی JSON متکی نیست.
- [ ] AJAX جستجوی محصول فقط برای کاربران مجاز CRM در دسترس است.
- [ ] AJAX آپلود فایل فقط برای کاربران مجاز CRM در دسترس است.
- [ ] payload فایل جدید بدون `upload_token` معتبر رد می‌شود.
