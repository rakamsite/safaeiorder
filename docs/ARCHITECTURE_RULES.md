# قوانین توسعه و معماری افزونه

این فایل باید پیش از هر تغییر کد، قابلیت عمومی، فرم یا refactor خوانده شود.

## وضعیت فعلی معماری

- `Business Profile` حذف شده است و توسعه جدید نباید آن را برگرداند.
- Form Builder به render، validation و ثبت درخواست public و admin متصل است.
- `form_builder` فقط منوی مدیریت فرم‌ها و عملیات مدیریتی آن را کنترل می‌کند؛ فرم‌های فعال در runtime مستقل از این feature قابل استفاده‌اند.
- فعال یا غیرفعال کردن فرم‌ها فقط از Form Builder انجام می‌شود.
- برای فرم‌های عادی، `request_type` همیشه برابر `form_id` است.
- انتخاب فرم برای ثبت درخواست فقط از فرم‌های فعال انجام می‌شود، اما labelهای نمایشی می‌توانند از همه فرم‌های موجود و snapshot درخواست‌های قدیمی خوانده شوند.

## وضعیت گذار معماری

افزونه از معماری مبتنی بر `Business Profile` به معماری عمومی مبتنی بر `Default Forms` و `Form Builder` منتقل شده است.

- `Business Profile` از flow اجرایی و دیتابیس درخواست‌ها حذف شده است.
- توسعه جدید نباید Business Profile تازه بسازد یا وابستگی تازه‌ای به Business Profile موجود اضافه کند.
- تا زمان تکمیل فازهای حذف، رفتار فعلی public request، admin request، settings، reports، feature toggle و shortcodeها باید حفظ شود.

## معماری هدف

### Core CRM

هسته عمومی افزونه شامل مشتریان، درخواست‌ها، گزارش‌ها، امنیت، دیتابیس و تنظیمات پایه است. Core نباید سایت، پروژه یا صنعت خاصی را بشناسد.

### Feature Manager

`CRPCRM_Feature_Manager` مسئول خاموش/روشن کردن امکانات عمومی افزونه است و مستقل از حذف Business Profile باقی می‌ماند.

### Default Forms

منبع seed/fallback نصب اولیه است و پس از seed شدن، فرم‌های ذخیره‌شده Form Builder منبع runtime هستند.

### Form Builder

ماژول مدیریت فرم‌های سفارشی از پنل مدیریت است. فرم‌ها را در `crpcrm_custom_forms` ذخیره می‌کند و به ثبت درخواست public/admin متصل است.

### Business Profile

یک سازوکار حذف‌شده است و نباید دوباره به flow اجرایی، تنظیمات یا دیتابیس بازگردد.

## قوانین الزامی

### قانون ۱: Core عمومی است

Core نباید به سایت یا پروژه خاصی مانند Safaei، Ajax، خودرو یا هر کسب‌وکار مشخص وابسته شود.

ممنوع:

```php
if ( 'safaei' === $site ) {
	// ...
}

if ( 'ajax' === $site ) {
	// ...
}
```

منطق عمومی باید با قراردادهای عمومی فرم، request، feature و settings پیاده‌سازی شود.

### قانون ۲: Business Profile جدید ممنوع است

- توسعه جدید نباید Business Profile تازه اضافه کند.
- فرم، request type، label یا تنظیم جدید نباید به Business Profile موجود متصل شود.
- بازگرداندن Business Profile یا وابستگی جدید به آن ممنوع است.

### قانون ۳: فرم‌ها باید به معماری فرم منتقل شوند

- فرم‌ها توسط Form Builder تعریف و مدیریت می‌شوند.
- Default Forms فقط seed اولیه نصب هستند و فرم‌های ذخیره‌شده را بازنویسی نمی‌کنند.
- تعریف فرم جدید به‌صورت hardcoded داخل Business Profile ممنوع است.
- Default Forms باید قابل seed شدن و مستقل از سایت خاص باشند.

### قانون ۴: Feature Toggle مستقل باقی می‌ماند

- Feature Toggleها همچنان با `CRPCRM_Feature_Manager` کنترل می‌شوند.
- حذف Business Profile نباید Feature Manager را حذف یا تضعیف کند.
- feature `form_builder` فقط امکان **مدیریت فرم‌ها** را خاموش/روشن می‌کند.
- feature `form_builder` فقط منو و عملیات مدیریت فرم‌ها را کنترل می‌کند.
- خاموش بودن `form_builder` نباید فرم‌های فعال، نمایش فرم‌ها یا ثبت درخواست را از کار بیندازد.
- خاموش کردن هر feature نباید داده‌های ذخیره‌شده را حذف یا reset کند.

### قانون ۵: Requestها باید form-aware باشند

منبع اصلی تشخیص و نمایش request در معماری هدف:

```text
form_id
form_version
request_type
request_data
```

- درخواست جدید باید این metadata را به‌درستی ذخیره کند.
- ستون و مفهوم `business_profile` نباید در request، query، report یا export استفاده شود.
- نمایش metadata باید از helper/repository مرکزی انجام شود و decode پراکنده `request_data` ممنوع است.
- request typeهای سیستمی باید از registry مرکزی خوانده شوند.
- برای فرم‌های عادی، `request_type` باید برابر `form_id` باشد.

### قانون ۶: گزینه‌های فرم داخل تعریف فیلد هستند

- گزینه‌های فیلدهای `select` باید بخشی از تعریف یا داده فرم باشند.
- خودرو یک مفهوم اختصاصی core یا Feature Manager نیست.
- در فرم‌های پیش‌فرض فعلی، خودرو فقط یک فیلد `select` با `options` داخل تعریف همان فرم است.
- تنظیمات مستقل خودرو یا سایت خاص نباید داخل core عمومی ساخته شوند.

### قانون ۷: هسته‌های اصلی خاموش‌شدنی یا سایت‌خاص نیستند

موارد زیر core هستند:

- database
- settings پایه
- customers پایه
- requests پایه
- security، nonce و capability

خاموش بودن UI یا feature نباید قراردادها، جدول‌ها یا داده‌های پایه را حذف کند.

### قانون ۸: دیتابیس و migration امن باشند

- migrationها باید idempotent باشند.
- حذف داده فقط در uninstall و با `delete_data_on_uninstall` مجاز است.
- تغییرات transitional نباید داده‌های تستی موجود را بی‌دلیل حذف کنند.
- requestها باید فقط با metadata عمومی فرم و درخواست مدیریت شوند.

### قانون ۹: امنیت وردپرس الزامی است

- تمام actionهای admin باید capability check داشته باشند.
- nonce برای عملیات تغییردهنده داده الزامی است.
- ورودی‌ها sanitize و خروجی‌ها escape شوند.
- queryهای دارای مقدار dynamic با `$wpdb->prepare()` نوشته شوند.

### قانون ۱۰: رفتار فعلی تا پایان مهاجرت حفظ شود

هر فاز refactor باید public request، admin request، settings، reports، feature toggle و shortcodeهای فعلی را سالم نگه دارد. انتقال معماری باید مرحله‌ای باشد و هر فاز به‌تنهایی قابل اجرا بماند.

## Business Profile Removal Plan

- **Phase 2:** انتقال فرم‌ها و request typeها از Business Profile به Default Forms.
- **Phase 3:** حذف Business Profile و setup/lock مربوط به آن.
- **Phase 4:** پاکسازی دیتابیس و queryها از `business_profile`، انجام‌شده.
- **Phase 5:** حذف تنظیمات خودرو و تبدیل آن به field options فرم، انجام‌شده.
- **Phase 6:** اضافه کردن ماژول سبک Form Builder، انجام‌شده.
- **Phase 7:** اتصال Form Builder به ثبت درخواست و seed فرم‌های اولیه، انجام‌شده.
- **Phase 8:** پاکسازی نهایی docs و code.

### Phase 7A Registry Rules

- `CRPCRM_Form_Registry` must read stored Form Builder forms as its primary source.
- Default Forms are seed/fallback definitions only and must never overwrite stored forms.
- Initial seeding may run only when `crpcrm_custom_forms` is empty.
- `form_builder` controls management access only; runtime registries must never gate stored forms behind this feature.
- `CRPCRM_Request_Type_Registry` must derive normal request types and labels from enabled forms in `CRPCRM_Form_Registry`.

## چک‌لیست قبل از توسعه فرم

- [ ] آیا فرم جدید از Form Builder یا در دوره گذار از Default Forms می‌آید؟
- [ ] آیا Business Profile جدید یا وابستگی جدید به Business Profile اضافه نشده است؟
- [ ] آیا field options داخل تعریف فرم قرار دارند؟
- [ ] آیا ثبت درخواست بدون فعال بودن مدیریت Form Builder کار می‌کند؟
- [ ] آیا request به `form_id`، `form_version`، `request_type` و `request_data` متکی است؟

## چک‌لیست قبل از تغییر قابلیت عمومی

- [ ] آیا core بدون وابستگی به Safaei، Ajax، خودرو یا سایت خاص باقی مانده است؟
- [ ] آیا Feature Manager تنها منبع تصمیم feature است؟
- [ ] آیا تغییر، داده یا تنظیمات موجود را هنگام خاموش شدن feature حذف نمی‌کند؟
- [ ] آیا امنیت، sanitize، escape و nonce رعایت شده‌اند؟
- [ ] آیا رفتار public request، admin request، settings، reports و shortcodeها حفظ شده است؟

## کنترل کیفیت

- فایل‌های PHP تغییرکرده باید با `php -l` بررسی شوند.
- تست‌ها باید متناسب با دامنه تغییر اجرا شوند.
- فایل‌های تغییرکرده، تست‌های اجراشده و محدودیت‌های تست باید گزارش شوند.
