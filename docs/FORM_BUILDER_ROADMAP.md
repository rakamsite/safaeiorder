# Form Builder Roadmap

Form Builder ماژول آینده مدیریت فرم‌های درخواست از پنل افزونه است.

- انواع فیلد اولیه: `text`، `textarea` و `select`.
- این ماژول با feature به نام `form_builder` کنترل می‌شود.
- feature `form_builder` فقط دسترسی به مدیریت فرم‌ها را کنترل می‌کند؛ خاموش بودن آن نباید نمایش فرم‌های فعال یا ثبت درخواست را متوقف کند.
- فرم‌های فعلی ابتدا به Default Forms منتقل و سپس به‌عنوان seed اولیه Form Builder استفاده می‌شوند.
- هر فرم باید `form_id`، `form_version`، `request_type`، فیلدها و گزینه‌های فیلدهای select را تعریف کند.
- گزینه‌های خودرو در فرم‌های پیش‌فرض فعلی نمونه‌ای از options داخلی یک فیلد `select` هستند و تنظیم مستقل محسوب نمی‌شوند.
- هر سایت در Form Builder آینده می‌تواند فیلدهای `select` و گزینه‌های خودش را بدون افزودن مفهوم اختصاصی به core بسازد.
- Form Builder نباید به Business Profile، Safaei، Ajax، خودرو یا سایت خاص وابسته باشد.
- نسخه اول فرم‌ساز فرم‌ها را در option عمومی `crpcrm_custom_forms` ذخیره می‌کند و CRUD پایه `text`، `textarea` و `select` را فراهم می‌کند.
- تا فاز ۷، Form Registry و ثبت درخواست همچنان فقط از Default Forms استفاده می‌کنند.
