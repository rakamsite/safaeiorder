# چک‌لیست تست افزونه پرتال درخواست و CRM مشتریان

این فایل برای تست دستی/سناریویی قبل از استفاده واقعی افزونه است. پیشنهاد می‌شود تست‌ها روی یک وردپرس تمیز و سپس روی staging مشابه سایت اصلی اجرا شوند.

## معماری فرم و درخواست

- [ ] نصب اولیه Default Forms را فقط یک بار در Form Builder seed می‌کند.
- [ ] حذف عمدی همه فرم‌ها باعث seed مجدد نمی‌شود.
- [ ] فرم‌های public و ثبت دستی admin فقط فرم‌های فعال Form Builder را نمایش می‌دهند.
- [ ] خاموش بودن feature `form_builder` فقط منوی مدیریت فرم‌ها را مخفی می‌کند و ثبت درخواست با فرم‌های فعال ادامه دارد.
- [ ] هر درخواست `form_id`، `form_version`، `request_type` و `request_data` دارد.
- [ ] اگر `request_type` فرم خالی باشد، هنگام ثبت درخواست به `form_id` fallback می‌شود.
- [ ] `request_data` شامل snapshot سبک عنوان فرم و label فیلدهای زمان ثبت است.
- [ ] label فرم غیرفعال از Form Registry و label فرم حذف‌شده از snapshot درخواست نمایش داده می‌شود.
- [ ] فرم فعال بدون فیلد فعال و select فعال بدون options ذخیره نمی‌شود.

## نصب، فعال‌سازی و نگهداری

- [ ] افزونه روی وردپرس تمیز بدون fatal error فعال می‌شود.
- [ ] جدول‌های افزونه با `dbDelta` ساخته می‌شوند.
- [ ] فعال‌سازی دوباره باعث duplicate یا خراب‌شدن جدول‌ها نمی‌شود.
- [ ] نقش‌ها و capabilityها ساخته می‌شوند.
- [ ] administrator همه capabilityهای افزونه را دارد.
- [ ] ابزار repair tables جدول/ستون گمشده را بدون حذف داده ترمیم می‌کند.
- [ ] deactivation داده‌ها را حذف نمی‌کند.
- [ ] uninstall بدون `delete_data_on_uninstall = yes` داده‌ها را حذف نمی‌کند.
- [ ] uninstall با `delete_data_on_uninstall = yes` جدول‌ها، گزینه‌ها و cron افزونه را حذف می‌کند.
- [ ] cron پاکسازی لاگ duplicate schedule نمی‌شود و هنگام deactivation/uninstall پاک می‌شود.

## OTP

- [ ] شماره جدید OTP دریافت می‌کند و فقط `otp_hash` ذخیره می‌شود.
- [ ] OTP خام در دیتابیس ذخیره نمی‌شود.
- [ ] OTP خام در UI عمومی نمایش داده نمی‌شود.
- [ ] کد درست user وردپرس با نقش `customer` می‌سازد.
- [ ] رکورد customer ساخته می‌شود.
- [ ] user_meta با کلید `crpcrm_phone_normalized` ذخیره می‌شود.
- [ ] کاربر بعد از تأیید کد login می‌شود.
- [ ] شماره قبلی user جدید نمی‌سازد و همان user را login می‌کند.
- [ ] کد اشتباه attempts را افزایش می‌دهد و پیام فارسی نمایش می‌دهد.
- [ ] بعد از تلاش بیش از حد status به `blocked` تغییر می‌کند.
- [ ] کد منقضی‌شده login نمی‌کند و status به `expired` تغییر می‌کند.
- [ ] ارسال مجدد قبل از زمان مجاز رد می‌شود.
- [ ] وقتی ثبت‌نام مشتریان خاموش است، شماره جدید OTP/login نمی‌گیرد.
- [ ] وقتی ثبت‌نام مشتریان خاموش است، شماره موجود می‌تواند login کند.
- [ ] تنظیمات ناقص ملی پیامک fatal error نمی‌دهد و لاگ مناسب ثبت می‌شود.

## پروفایل مشتری

- [ ] مشتری با `profile_completed = 0` فقط فرم تکمیل پروفایل را می‌بیند.
- [ ] بدون نام و نام خانوادگی نمی‌تواند ادامه دهد.
- [ ] بدون استان نمی‌تواند ادامه دهد.
- [ ] بدون شهر نمی‌تواند ادامه دهد.
- [ ] موبایل readonly نمایش داده می‌شود.
- [ ] ذخیره معتبر `profile_completed = 1` می‌کند.
- [ ] `display_name` وردپرس بروزرسانی می‌شود.
- [ ] صفحه ویرایش پروفایل کار می‌کند.
- [ ] فیلدهای اختیاری خارج از scope مثل کد ملی، ایمیل، آدرس یا مدل خودرو در فرم پروفایل وجود ندارد.

## Attribution و UTM

- [ ] کاربر ناشناس با `utm_source=instagram` وارد می‌شود و current attribution اینستاگرام است.
- [ ] event attribution ثبت می‌شود.
- [ ] بعد از OTP، `first_source` و `last_source` برابر instagram می‌شوند.
- [ ] ورود جدید با لینک اینستاگرام برای کاربر قبلی، `last_source` را بروزرسانی می‌کند و `first_source` را تغییر نمی‌دهد.
- [ ] چرخیدن بین صفحات داخلی source را direct نمی‌کند.
- [ ] ورود بدون UTM/referrer/session به عنوان direct ثبت می‌شود.
- [ ] referrer داخلی attribution جدید نمی‌سازد.
- [ ] cookie خراب fatal error ایجاد نمی‌کند.

## ثبت درخواست مشتری

- [ ] مشتری با پروفایل کامل هر سه فرم را می‌بیند.
- [ ] requiredها در هر فرم اعتبارسنجی می‌شوند.
- [ ] فرم ثبت‌نام خودرو `form_id=safaei_car_registration` می‌سازد و در صورت خالی بودن `request_type` همان مقدار استفاده می‌شود.
- [ ] فرم قطعات `form_id=safaei_parts_request` می‌سازد و در صورت خالی بودن `request_type` همان مقدار استفاده می‌شود.
- [ ] فرم تعمیرات `form_id=safaei_repair_booking` می‌سازد و در صورت خالی بودن `request_type` همان مقدار استفاده می‌شود.
- [ ] هر ارسال فرم یک request مستقل می‌سازد.
- [ ] POST/Redirect/GET مانع ثبت تکراری با refresh می‌شود.
- [ ] `request_code` ساخته می‌شود.
- [ ] `request_source` از current attribution ذخیره می‌شود.
- [ ] rate limit درخواست کار می‌کند.
- [ ] «درخواست‌های من» فقط درخواست‌های همان مشتری را نشان می‌دهد.
- [ ] مشتری با دستکاری `request_code` درخواست دیگران را نمی‌بیند.

## CRM فروش

- [ ] `sales_agent` فقط درخواست‌های بدون مسئول و درخواست‌های خودش را می‌بیند.
- [ ] `sales_agent` درخواست بدون مسئول را claim می‌کند.
- [ ] `owner_id` درست ثبت می‌شود.
- [ ] status از `new` به `in_progress` تغییر می‌کند.
- [ ] `first_assigned_at` ثبت می‌شود.
- [ ] activity ثبت می‌شود.
- [ ] کارشناس دوم درخواست متعلق به کارشناس اول را نمی‌بیند یا نمی‌تواند تغییر دهد.
- [ ] `sales_manager` همه درخواست‌ها را می‌بیند.
- [ ] `sales_manager` مالک را تغییر می‌دهد.
- [ ] `sales_manager` درخواست را آزاد می‌کند.
- [ ] دستکاری URL برای request غیرمجاز رد می‌شود.

## Workflow فروش

- [ ] تماس پاسخ داده ثبت می‌شود.
- [ ] تماس پاسخ داده نشد ثبت می‌شود.
- [ ] واتساپ ثبت می‌شود.
- [ ] یادداشت داخلی ثبت می‌شود.
- [ ] پیگیری بعدی بدون تاریخ خطا می‌دهد.
- [ ] پیگیری بعدی با تاریخ آینده ثبت می‌شود.
- [ ] تاریخ پیگیری گذشته خطا می‌دهد.
- [ ] ناموفق بدون دلیل خطا می‌دهد.
- [ ] ناموفق با دلیل ثبت می‌شود.
- [ ] نامعتبر بدون دلیل خطا می‌دهد.
- [ ] موفق درخواست را می‌بندد.
- [ ] درخواست بسته‌شده از صف کاری عادی خارج می‌شود.
- [ ] مشتری یادداشت داخلی را نمی‌بیند.
- [ ] پیگیری امروز و عقب‌افتاده درست فیلتر می‌شود.

## پروفایل تحلیلی مشتری

- [ ] `sales_manager` پروفایل هر مشتری را می‌بیند.
- [ ] `sales_agent` فقط مشتری مجاز را می‌بیند.
- [ ] first attribution درست نمایش داده می‌شود.
- [ ] last attribution درست نمایش داده می‌شود.
- [ ] تعداد درخواست‌ها درست است.
- [ ] تفکیک نوع درخواست‌ها درست است.
- [ ] کارشناسان مرتبط درست نمایش داده می‌شوند.
- [ ] فعالیت‌های اخیر مشتری نمایش داده می‌شوند.
- [ ] تاریخچه ورودها نمایش داده می‌شود.
- [ ] دستکاری `customer_id` برای مشتری نامرتبط رد می‌شود.

## گزارش‌ها

- [ ] `sales_manager` گزارش‌ها را می‌بیند.
- [ ] `sales_agent` گزارش مدیریتی را نمی‌بیند.
- [ ] فیلتر تاریخ درست کار می‌کند.
- [ ] `source=instagram` درست محاسبه می‌شود.
- [ ] campaign و content درست محاسبه می‌شوند.
- [ ] گزارش نوع درخواست درست است.
- [ ] ماتریس source × request_type درست است.
- [ ] قیف وضعیت‌ها درست است.
- [ ] عملکرد کارشناسان درست است.
- [ ] پیگیری عقب‌افتاده درست است.
- [ ] دلایل ناموفق/نامعتبر درست است.
- [ ] جدول جزئیات درخواست‌ها با فیلترها و pagination هماهنگ است.

## پنل کارکنان

- [ ] `sales_agent` گزارش روزانه ثبت می‌کند.
- [ ] `internal_employee` گزارش روزانه ثبت می‌کند.
- [ ] `customer` دسترسی ندارد.
- [ ] هر کارمند فقط آیتم‌های خودش را می‌بیند.
- [ ] مدیر همه آیتم‌ها را می‌بیند.
- [ ] مدیر امکان ثبت گزارش روزانه، درخواست از مدیریت یا مشکل/مانع برای خودش ندارد.
- [ ] مدیر جزئیات کامل گزارش روزانه و snapshot آمار CRM را فقط در صفحه مشاهده جزئیات می‌بیند.
- [ ] مدیر جزئیات درخواست از مدیریت و مشکل/مانع را می‌بیند و در همان صفحه پاسخ می‌دهد.
- [ ] کارمند و کارشناس فروش جزئیات کامل وظیفه خود را با دکمه «مشاهده جزئیات» می‌بینند.
- [ ] درخواست از مدیریت ثبت می‌شود.
- [ ] مشکل/مانع ثبت می‌شود.

## Notification Center

- [ ] notification جدید برای user فعلی در صفحه اعلانات نمایش داده می‌شود.
- [ ] unreadها با ظاهر متفاوت مشخص می‌شوند.
- [ ] دکمه `مشاهده` notification را read می‌کند و فقط به URL داخلی امن redirect می‌کند.
- [ ] دکمه `خوانده شد` فقط notification همان user را read می‌کند.
- [ ] دکمه `خواندن همه` فقط اعلان‌های user فعلی را read می‌کند.
- [ ] badge تعداد unread از `Notification_Service::get_unread_count()` خوانده می‌شود.
- [ ] وقتی feature `notifications` خاموش است، صفحه و عملیات‌ها رفتار امن دارند.

## File Upload

- [ ] فایل جدید `file_upload` در `uploads/crpcrm-protected/request-files/YYYY/MM/` ذخیره می‌شود.
- [ ] `upload_dir` فقط هنگام آپلود همین فیلد اعمال می‌شود.
- [ ] تصویر در جزئیات درخواست به‌صورت thumbnail نمایش داده می‌شود.
- [ ] کلیک روی thumbnail تصویر modal را باز می‌کند.
- [ ] PDF به‌صورت آیکن PDF نمایش داده می‌شود و با کلیک دانلود می‌شود.
- [ ] URL خام فایل در UI چاپ نمی‌شود.
- [ ] فایل قدیمی ذخیره‌شده در مسیر پیش‌فرض وردپرس همچنان نمایش داده می‌شود.
- [ ] مدیر پاسخ می‌دهد و وضعیت تغییر می‌کند.
- [ ] task ساخته می‌شود.
- [ ] کارمند task خودش را تغییر وضعیت می‌دهد.
- [ ] اطلاعیه ساخته می‌شود.
- [ ] مدیر اطلاعیه را ویرایش و حذف می‌کند.
- [ ] فهرست اطلاعیه کارمند ستون‌های مخاطب، ایجادکننده و تعداد مشاهده را نمایش نمی‌دهد.
- [ ] مشاهده‌شدن اطلاعیه ثبت می‌شود.
- [ ] رکورد read تکراری ساخته نمی‌شود.

## گزارش روزانه فروشنده و snapshot CRM

- [ ] فروشنده آمار CRM امروز خودش را می‌بیند.
- [ ] snapshot هنگام ثبت گزارش ذخیره می‌شود.
- [ ] snapshot با تغییر داده‌های بعدی تغییر نمی‌کند.
- [ ] manager snapshot را می‌بیند.
- [ ] `internal_employee` این بخش را نمی‌بیند.
- [ ] `sales_agent` آمار فروشنده دیگر را نمی‌بیند.

## CSV

- [ ] CSV درخواست‌ها خروجی درست دارد.
- [ ] CSV مشتریان خروجی درست دارد.
- [ ] CSV گزارش روزانه کارکنان خروجی درست دارد.
- [ ] CSV درخواست‌های کارکنان از مدیریت خروجی درست دارد.
- [ ] CSV مشکلات/موانع خروجی درست دارد.
- [ ] CSV فارسی در Excel درست باز می‌شود.
- [ ] مقادیر شروع‌شونده با `=`, `+`, `-`, `@` برای جلوگیری از CSV injection امن می‌شوند.

## نقش‌ها و دسترسی‌ها

- [ ] customer به پنل ادمین/کارکنان/گزارش‌ها دسترسی ندارد.
- [ ] sales_agent به درخواست‌های دیگران دسترسی غیرمجاز ندارد.
- [ ] internal_employee به CRM و گزارش فروش دسترسی ندارد.
- [ ] مدیر فقط با capability مناسب به تنظیمات و ابزارها دسترسی دارد.
- [ ] همه فرم‌های POST nonce دارند.
- [ ] همه عملیات مدیریتی capability check دارند.
- [ ] با `WP_DEBUG` روشن notice/warning جدی دیده نمی‌شود.

## حذف مشتری و همگام‌سازی با وردپرس

- [ ] حذف یک کاربر مشتری از بخش کاربران وردپرس، پروفایل مشتری CRM، همه درخواست‌ها، فعالیت‌های درخواست‌ها و تاریخچه ورودی CRM مرتبط با او را حذف می‌کند.
- [ ] شماره موبایل کاربر حذف‌شده پس از حذف کاربر وردپرس می‌تواند دوباره ثبت‌نام کند.
- [ ] مدیر وردپرس می‌تواند از فهرست مشتریان، مشتری باقی‌مانده/خراب را با دکمه «حذف مشتری» حذف کند.
- [ ] حذف دستی مشتری، درخواست‌ها و اطلاعات CRM مرتبط را حذف می‌کند اما حساب وردپرس مرتبط را حذف نمی‌کند.
- [ ] کاربر فاقد دسترسی `manage_options` دکمه حذف مشتری را نمی‌بیند و ارسال مستقیم فرم حذف نیز برای او رد می‌شود.
## چک‌لیست نهایی

### نصب و ارتقا
- [ ] افزونه بدون fatal error فعال می‌شود.
- [ ] `dbDelta` و upgrade schema در فعال‌سازی/به‌روزرسانی اجرا می‌شوند.
- [ ] اگر schema تغییر نکرده، `CRPCRM_DB_VERSION` بدون دلیل تغییر نکرده است.

### Feature Toggle
- [ ] `portal`، `staff`، `reports`، `form_builder` و `notifications` هرکدام رفتار جداگانه دارند.
- [ ] خاموش بودن `form_builder` فقط مدیریت فرم‌ها را مخفی می‌کند.
- [ ] خاموش بودن `notifications` داده‌ها را حذف نمی‌کند و toast/page امن می‌مانند.
- [ ] خاموش بودن `staff` پنل کارکنان را به‌درستی محدود می‌کند.

### Form Builder
- [ ] فرم فعال بدون فیلد فعال ذخیره نمی‌شود.
- [ ] `display_html` فقط نمایشی است و در `request_data` ذخیره نمی‌شود.
- [ ] `file_upload`، `product_search`، `text`، `textarea` و `select` طبق انتظار render و sanitize می‌شوند.

### درخواست مشتری
- [ ] ثبت درخواست مشتری بدون فایل کار می‌کند.
- [ ] ثبت درخواست مشتری با تصویر کار می‌کند.
- [ ] ثبت درخواست مشتری با PDF کار می‌کند.
- [ ] خلاصه درخواست فقط فیلدهای واقعی فرم را نشان می‌دهد.
- [ ] پیوست فایل بدون چاپ URL خام نمایش داده می‌شود.
- [ ] پاسخ مشتری فقط برای همان درخواست و با nonce معتبر ثبت می‌شود.

### پاسخ و گفت‌وگو
- [ ] پاسخ مشتری، کارشناس و مدیر در تایم‌لاین درست دیده می‌شوند.
- [ ] `reply_added` برای طرف مقابل notification می‌سازد.
- [ ] فرستنده برای پیام خودش notification نمی‌گیرد.

### Notification Center
- [ ] toast فقط notificationهای جدید یا اخیر را نشان می‌دهد.
- [ ] badge unread از `Notification_Service::get_unread_count()` خوانده می‌شود.
- [ ] `mark_read` و `mark_all_read` فقط روی user فعلی اثر می‌گذارند.
- [ ] صفحه اعلانات روی کاربر دیگر دسترسی نمی‌دهد.

### File Upload
- [ ] فایل جدید در `uploads/crpcrm-protected/request-files/YYYY/MM/` ذخیره می‌شود.
- [ ] `upload_dir` فقط در زمان آپلود همین فیلد اعمال می‌شود.
- [ ] token/pending upload برای فایل جدید verify می‌شود.
- [ ] فایل orphan منقضی‌شده توسط cleanup حذف می‌شود.
- [ ] فایل legacy که فقط URL دارد برای نمایش نشکسته است.

### Staff
- [ ] ثبت درخواست از مدیریت توسط کارمند بدون فایل کار می‌کند.
- [ ] ثبت درخواست از مدیریت با تصویر/PDF کار می‌کند.
- [ ] پاسخ مدیر با تصویر/PDF کار می‌کند.
- [ ] کارمند به فایل یا درخواست کارمند دیگر دسترسی ندارد.
- [ ] مدیر بدون capability مناسب دسترسی اضافه ندارد.

### UI و متن‌ها
- [ ] متن‌های mojibake در فایل‌های تغییرکرده باقی نمانده‌اند.
- [ ] `staff.php` یا viewهای شکسته fatal error نمی‌دهند.
- [ ] JS/CSS فقط در صفحات لازم enqueue می‌شوند.
- [ ] صفحات افزونه از Design System جدید و کلاس‌های prefix‌دار `crpcrm` استفاده می‌کنند.
- [ ] کارت‌ها، جدول‌ها، badgeها، فیلترها و empty stateها از نظر ظاهری یکدست شده‌اند.
- [ ] modal فایل و toast notification از نظر ظاهر خام و شلوغ نیستند.

### Landing Manager
- [ ] feature `landing_manager` در بخش امکانات دیده می‌شود.
- [ ] منوی «مدیریت لندینگ‌ها» فقط وقتی feature روشن است نمایش داده می‌شود.
- [ ] ساخت لندینگ با slug معتبر انجام می‌شود.
- [ ] slug تکراری خطا می‌دهد.
- [ ] slug فارسی یا نامعتبر پذیرفته نمی‌شود.
- [ ] لینک نهایی با `?u=slug` درست ساخته می‌شود.
- [ ] اگر مقصد query داشته باشد، `u` با `&` اضافه می‌شود.
- [ ] مقصد خارجی پذیرفته نمی‌شود.

### Landing Tracking
- [ ] وقتی صفحه با `?u=slug` معتبر باز می‌شود، کلیک معتبر ثبت می‌شود.
- [ ] slug نامعتبر یا inactive هیچ trackingی ایجاد نمی‌کند.
- [ ] مقصد ناهماهنگ با landing ثبت نمی‌شود.
- [ ] botها و preview crawlerها ثبت نمی‌شوند.
- [ ] `crpcrm_visitor_id` ساخته و برای بازدیدهای بعدی بازاستفاده می‌شود.
- [ ] `crpcrm_first_touch` فقط یک‌بار ست می‌شود.
- [ ] `crpcrm_last_touch` در هر landing معتبر به‌روزرسانی می‌شود.
- [ ] JS fallback فقط وقتی `u` در URL وجود دارد tracking می‌فرستد.
- [ ] اگر feature `landing_manager` خاموش باشد، هیچ tracking یا cookie جدیدی ساخته نمی‌شود.

## Landing Hardening

- [ ] AJAX fallback با `current_url` معتبر و same-site کلیک را فقط برای مقصد هم‌خوان ثبت می‌کند.
- [ ] AJAX fallback با `current_url` خارجی یا جعلی هیچ کلیکی ثبت نمی‌کند.
- [ ] Origin/Referer خارجی یا نامعتبر باعث ignore شدن tracking می‌شود.
- [ ] dedupe کلیک‌ها بر اساس `user_agent_hash` و `ip_hash` کار می‌کند.
- [ ] rate limit سبک، درخواست‌های تکراری را بدون خراب کردن صفحه رد می‌کند.
- [ ] `bot` و `preview` user-agent های واضح ثبت نمی‌شوند.
- [ ] upload endpoint فقط برای کاربران مجاز CRM باز است.
- [ ] request جدید با payload فایل بدون `upload_token` رد می‌شود.
- [ ] فایل legacy فقط برای نمایش درخواست‌های قدیمی کار می‌کند و submit جدید را دور نمی‌زند.
- [ ] uninstall، cron و option مربوط به pending upload cleanup را پاک می‌کند.

## Reports Dashboard

- [ ] The reports page opens only for users with the correct capability.
- [ ] KPI cards update correctly for the selected date range.
- [ ] Filters are sanitized and persist across pagination.
- [ ] Line, source, status, and staff charts render without errors when data exists.
- [ ] Empty states appear instead of broken charts or empty tables when there is no data.
- [ ] If `staff` is disabled, staff performance is hidden.
- [ ] If `landing_manager` is disabled, landing sections are hidden.
- [ ] Chart.js loads only on the reports page.
- [ ] The dashboard remains readable on RTL and narrow screens.

## Customer Landing Attribution

- [ ] attribution معتبر بعد از OTP یا اولین بارگذاری پرتال روی customer ذخیره می‌شود.
- [ ] `first_touch` روی customer فقط یک‌بار ثبت می‌شود و `last_touch` با ورود معتبر جدید به‌روزرسانی می‌شود.
- [ ] request پیگیری خودکار 24 ساعته attribution ذخیره‌شده روی customer را استفاده می‌کند.
- [ ] آمار request هر لندینگ از `request_landing_slug` و `request_landing_id` محاسبه می‌شود.
- [ ] AJAX جستجوی محصول برای کاربر لاگین‌شده نامرتبط CRM در دسترس نیست.

## Staff Operational UI

- [ ] Staff dashboard shows operational KPI cards for new requests, my requests, customer replies, today follow-ups, overdue items, and auto follow-ups.
- [ ] Quick filters/tabs for all, new, mine, unassigned, customer replies, today follow-ups, overdue follow-ups, and lead follow-up work without breaking existing filters.
- [ ] Request rows highlight mine, unassigned, customer-reply, overdue, and lead-follow-up states with badges or row emphasis.
- [ ] Request detail pages keep the compact header, source attribution, attachments, conversation, and action panel visible and readable.
- [ ] Staff manager pages remain functional while using the cleaner shared design system.
- [ ] If `staff` is disabled, staff-only operational UI does not fatal.
- [ ] RTL layout and narrow-screen overflow stay usable for request tables and detail cards.
