# Form Builder Roadmap

Form Builder ماژول فعلی مدیریت فرم‌های درخواست از پنل افزونه است و به ثبت درخواست public/admin متصل است.

## وضعیت انجام‌شده

- فاز ۷A انجام شده است: Default Forms فقط seed اولیه هستند، Form Registry از storage می‌خواند و seed پس از حذف عمدی همه فرم‌ها تکرار نمی‌شود.
- فاز ۷B انجام شده است: render، validation، sanitize و ثبت درخواست public/admin از schema مشترک استفاده می‌کنند.
- درخواست‌ها `form_id`، `form_version`، `request_type = form_id`، `request_data` و snapshot سبک labelها را ذخیره می‌کنند.
- فعال/غیرفعال کردن فرم‌ها فقط از Form Builder انجام می‌شود.
- labelهای نمایشی فرم غیرفعال از همه فرم‌های موجود و label فرم حذف‌شده از snapshot درخواست خوانده می‌شود.

## موارد آینده

- بهبود UX مدیریت فرم‌ها.
- field typeهای بیشتر در فازهای آینده.
- drag/drop و امکانات پیشرفته‌تر در فازهای آینده.

- انواع فیلد اولیه: `text`، `textarea` و `select`.
- این ماژول با feature به نام `form_builder` کنترل می‌شود.
- feature `form_builder` فقط دسترسی به مدیریت فرم‌ها را کنترل می‌کند؛ خاموش بودن آن نباید نمایش فرم‌های فعال یا ثبت درخواست را متوقف کند.
- فرم‌های فعلی ابتدا به Default Forms منتقل و سپس به‌عنوان seed اولیه Form Builder استفاده می‌شوند.
- هر فرم باید `form_id`، `form_version`، فیلدها و گزینه‌های فیلدهای select را تعریف کند؛ `request_type` برابر `form_id` است.
- گزینه‌های خودرو در فرم‌های پیش‌فرض فعلی نمونه‌ای از options داخلی یک فیلد `select` هستند و تنظیم مستقل محسوب نمی‌شوند.
- هر سایت در Form Builder آینده می‌تواند فیلدهای `select` و گزینه‌های خودش را بدون افزودن مفهوم اختصاصی به core بسازد.
- Form Builder نباید به Business Profile، Safaei، Ajax، خودرو یا سایت خاص وابسته باشد.
- نسخه اول فرم‌ساز فرم‌ها را در option عمومی `crpcrm_custom_forms` ذخیره می‌کند و CRUD پایه `text`، `textarea` و `select` را فراهم می‌کند.
- در فاز ۷A، Default Forms فقط هنگام خالی بودن storage به‌عنوان seed اولیه استفاده می‌شوند.
- Form Registry منبع اصلی خود را از فرم‌های ذخیره‌شده در `crpcrm_custom_forms` می‌گیرد. Request Type Registry برای submission فرم‌های فعال و برای display همه فرم‌های موجود را می‌خواند.
- خاموش بودن feature `form_builder` فقط UI مدیریت فرم‌ها را خاموش می‌کند و هیچ اثری روی خواندن یا استفاده از فرم‌های ذخیره‌شده ندارد.
- rendering و submit processing عمومی/ادمین با helper مشترک schema-driven انجام می‌شود.
