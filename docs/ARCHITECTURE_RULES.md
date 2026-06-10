# قوانین توسعه و معماری افزونه

این فایل باید **پیش از هر تغییر کد، افزودن Business Profile، توسعه قابلیت عمومی یا افزودن فیچر جدید** خوانده شود. هدف این قوانین، حفظ معماری ماژولار افزونه و جلوگیری از وابستگی core به پروژه‌های خاص است.

## قانون ۱: Core نباید به Business Profile خاص وابسته باشد

هسته افزونه نباید بداند صفایی، خودرو، Ajax یا هر پروژه خاص دیگری چیست.

ممنوع:

```php
if ( 'safaei' === $profile ) {
	// ...
}

if ( 'ajax' === $profile ) {
	// ...
}
```

منطق اختصاصی هر کسب‌وکار، فرم‌ها، عنوان‌ها، گزینه‌ها و رفتارهای مخصوص آن باید داخل Business Profile همان کسب‌وکار قرار بگیرد.

## قانون ۲: کسب‌وکار جدید فقط از مسیر Business Profile اضافه می‌شود

برای هر کسب‌وکار جدید، یک کلاس Business Profile مستقل بسازید؛ برای مثال:

```text
includes/business-profiles/class-ajax-business-profile.php
```

کلاس جدید باید `CRPCRM_Business_Profile_Interface` را پیاده‌سازی کند. فرم‌ها، request typeها، labelها، تنظیمات اختصاصی و defaultهای پروژه باید داخل همان profile تعریف شوند.

ثبت profile فقط با hook مرکزی انجام می‌شود:

```php
add_action( 'crpcrm_register_business_profiles', function ( $manager ) {
	$manager->register_profile( new CRPCRM_Ajax_Business_Profile() );
} );
```

`CRPCRM_Business_Profile_Manager` نباید هیچ Business Profile مشخصی را مستقیم `new` یا register کند.

## قانون ۳: Business Profile بعد از setup قفل است

- profile فقط یک‌بار و با تأیید مدیر دارای `manage_options` در setup اولیه انتخاب می‌شود.
- تنظیمات عمومی نباید امکان تغییر profile را فراهم کنند.
- profile فعال نباید از `POST`، query string یا تنظیمات عمومی خوانده یا تغییر داده شود.
- تغییر profile پس از setup فقط با ابزار توسعه‌ای محافظت‌شده و صریح مجاز است.

## قانون ۴: تنظیمات اختصاصی هر profile باید namespaced باشد

تنظیمات اختصاصی profile نباید وارد option تنظیمات عمومی CRM شوند.

ساختار option:

```text
crpcrm_profile_settings_{profile_id}
```

- تنظیمات هر پروژه فقط داخل Business Profile همان پروژه قرار می‌گیرد.
- صفحه settings عمومی فقط API عمومی مانند `render_active_profile_settings()` را صدا می‌زند.
- تنظیمات nested باید با merge بازگشتی امن ترکیب شوند و داده ذخیره‌شده کاربر را خراب نکنند.

## قانون ۵: Requestها همیشه باید profile-aware باشند

هر request جدید باید ستون‌های زیر را مستقیم پر کند:

```text
business_profile
form_id
form_version
```

- queryهای requests باید هرجا منطقی است با `business_profile` قفل‌شده فیلتر شوند.
- ستون‌های مستقل منبع اصلی metadata هستند.
- برای نمایش داده request باید از `CRPCRM_Request_Repository::get_merged_request_data()` استفاده شود.
- decode پراکنده `request_data` برای تعیین metadata ممنوع است.

## قانون ۶: Request typeهای سیستمی باید مرکزی باشند

- request typeهایی مانند `lead_follow_up` نباید پراکنده hardcode شوند.
- تمام system request typeها باید از `CRPCRM_System_Request_Types` خوانده شوند.
- queryهای public/customer باید همه system typeهای registry مرکزی را حذف کنند.
- جزئیات public نیز نباید system request typeها را نمایش دهد.

## قانون ۷: Featureهای عمومی نباید مخصوص profile خاص پیاده شوند

ممنوع:

```php
if ( 'ajax' === $profile ) {
	hide_staff();
}
```

اگر Feature Toggle در آینده اضافه شد، استفاده باید عمومی باشد:

```php
if ( ! CRPCRM_Feature_Manager::is_enabled( 'staff' ) ) {
	// ...
}
```

## قانون ۸: هسته‌های اصلی خاموش‌شدنی یا profile-specific نیستند

موارد زیر core افزونه هستند و نباید به منطق اختصاصی profile تبدیل شوند:

- database
- تنظیمات پایه
- Business Profile Manager
- customers پایه
- requests پایه
- security، nonce و capability

## قانون ۹: دیتابیس نباید با تغییر profile قاطی شود

- profile قفل‌شده منبع اصلی سایت است.
- هیچ فیچری نباید profile فعال را از `POST` یا تنظیمات عمومی تغییر دهد.
- migrationها باید idempotent باشند.
- داده‌های موجود نباید در migration معمولی حذف شوند.
- حذف داده فقط در uninstall و با `delete_data_on_uninstall` مجاز است.
- حذف تنظیمات profile در uninstall باید با prefix عمومی `crpcrm_profile_settings_` انجام شود.

## قانون ۱۰: امنیت وردپرس الزامی است

- تمام actionهای admin باید capability check داشته باشند.
- nonce check برای عملیات تغییردهنده داده الزامی است.
- ورودی‌ها sanitize و خروجی‌ها escape شوند.
- queryهای دارای مقدار dynamic با `$wpdb->prepare()` نوشته شوند.

## قانون ۱۱: توسعه نباید رفتار فعلی صفایی را بشکند

هر تغییر جدید باید موارد زیر را سالم نگه دارد:

- فرم‌های صفایی
- تنظیمات خودرو و vehicle catalog
- ثبت درخواست public و admin
- لیست‌ها، جزئیات درخواست و صفحه مشتریان
- گزارش‌ها و آمارهای profile-aware

## قانون ۱۲: پیش از پایان هر تغییر، تست الزامی است

- `php -l` روی همه فایل‌های PHP اجرا شود.
- setup اولیه و قفل profile تست شوند.
- ثبت درخواست public و admin تست شود.
- تنظیمات اختصاصی profile فعال تست شود.
- queryها و نمایش metadata درخواست بررسی شوند.
- فایل‌های تغییرکرده و تست‌های اجراشده گزارش شوند.

## چک‌لیست افزودن Business Profile جدید

- [ ] آیا core بدون تغییر وابسته به پروژه جدید مانده است؟
- [ ] آیا profile با hook `crpcrm_register_business_profiles` ثبت شده است؟
- [ ] آیا فرم‌ها و request typeها داخل profile هستند؟
- [ ] آیا تنظیمات اختصاصی namespaced هستند؟
- [ ] آیا هیچ شرط `if ( $profile === '...' )` در core اضافه نشده است؟

## چک‌لیست تغییر قابلیت عمومی

- [ ] آیا تغییر برای همه profileها عمومی است؟
- [ ] آیا منطق اختصاصی به profile مربوط منتقل شده است؟
- [ ] آیا queryها profile-aware هستند؟
- [ ] آیا security رعایت شده است؟
- [ ] آیا رفتار فعلی صفایی حفظ شده است؟
- [ ] آیا تست‌ها و `php -l` انجام و گزارش شده‌اند؟
