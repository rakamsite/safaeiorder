(function () {
	'use strict';

	var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	var monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	var weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
	var activePicker = null;

	function toLatin(value) {
		return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(digit) % 10;
		});
	}

	function toPersian(value) {
		return String(value || '').replace(/\d/g, function (digit) {
			return persianDigits[parseInt(digit, 10)];
		});
	}

	function pad(number) {
		return String(number).padStart(2, '0');
	}

	function gregorianToJalali(gy, gm, gd) {
		var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		var gy2 = gm > 2 ? gy + 1 : gy;
		var days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];
		var jy = -1595 + (33 * Math.floor(days / 12053));
		days %= 12053;
		jy += 4 * Math.floor(days / 1461);
		days %= 1461;
		if (days > 365) {
			jy += Math.floor((days - 1) / 365);
			days = (days - 1) % 365;
		}
		var jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
		var jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
		return [jy, jm, jd];
	}

	function jalaliToGregorian(jy, jm, jd) {
		jy += 1595;
		var days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
		var gy = 400 * Math.floor(days / 146097);
		days %= 146097;
		if (days > 36524) {
			gy += 100 * Math.floor(--days / 36524);
			days %= 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * Math.floor(days / 1461);
		days %= 1461;
		if (days > 365) {
			gy += Math.floor((days - 1) / 365);
			days = (days - 1) % 365;
		}
		var gd = days + 1;
		var leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
		var months = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		var gm;
		for (gm = 1; gm <= 12 && gd > months[gm]; gm++) {
			gd -= months[gm];
		}
		return [gy, gm, gd];
	}

	function parseJalali(value) {
		var parts = toLatin(value).trim().replace(/-/g, '/').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
		if (!parts) {
			return null;
		}
		return [parseInt(parts[1], 10), parseInt(parts[2], 10), parseInt(parts[3], 10)];
	}

	function displayFromIso(iso) {
		var match = String(iso || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
		if (!match) {
			return '';
		}
		var jalali = gregorianToJalali(parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10));
		return toPersian(jalali[0] + '/' + pad(jalali[1]) + '/' + pad(jalali[2]));
	}

	function isoFromDisplay(value) {
		var jalali = parseJalali(value);
		if (!jalali) {
			return '';
		}
		var gregorian = jalaliToGregorian(jalali[0], jalali[1], jalali[2]);
		return gregorian[0] + '-' + pad(gregorian[1]) + '-' + pad(gregorian[2]);
	}

	function daysInJalaliMonth(year, month) {
		if (month <= 6) {
			return 31;
		}
		if (month <= 11) {
			return 30;
		}
		var g1 = jalaliToGregorian(year + 1, 1, 1);
		var g0 = jalaliToGregorian(year, 1, 1);
		var d1 = Date.UTC(g1[0], g1[1] - 1, g1[2]);
		var d0 = Date.UTC(g0[0], g0[1] - 1, g0[2]);
		return ((d1 - d0) / 86400000) === 366 ? 30 : 29;
	}

	function weekdayForJalali(year, month, day) {
		var gregorian = jalaliToGregorian(year, month, day);
		var date = new Date(gregorian[0], gregorian[1] - 1, gregorian[2]);
		return (date.getDay() + 1) % 7;
	}

	function syncHidden(input) {
		var hidden = document.getElementById(input.dataset.crpcrmDateTarget || '');
		if (!hidden) {
			return;
		}
		var iso = isoFromDisplay(input.value);
		if (!iso) {
			hidden.value = '';
			return;
		}
		var timeId = input.dataset.crpcrmDatetimeTime;
		if (timeId) {
			var time = document.getElementById(timeId);
			hidden.value = iso + 'T' + ((time && time.value) ? time.value : '00:00');
		} else {
			hidden.value = iso;
		}
	}

	function renderPicker(input, year, month) {
		closePicker();
		activePicker = document.createElement('div');
		activePicker.className = 'crpcrm-jalali-picker';
		activePicker.setAttribute('dir', 'rtl');

		var header = document.createElement('div');
		header.className = 'crpcrm-jalali-picker-header';
		var next = document.createElement('button');
		next.type = 'button';
		next.textContent = '‹';
		var title = document.createElement('strong');
		title.textContent = monthNames[month - 1] + ' ' + toPersian(year);
		var prev = document.createElement('button');
		prev.type = 'button';
		prev.textContent = '›';
		header.appendChild(next);
		header.appendChild(title);
		header.appendChild(prev);
		activePicker.appendChild(header);

		var grid = document.createElement('div');
		grid.className = 'crpcrm-jalali-picker-grid';
		weekDays.forEach(function (day) {
			var cell = document.createElement('span');
			cell.className = 'crpcrm-jalali-weekday';
			cell.textContent = day;
			grid.appendChild(cell);
		});
		for (var blank = 0; blank < weekdayForJalali(year, month, 1); blank++) {
			grid.appendChild(document.createElement('span'));
		}
		for (var dayNumber = 1; dayNumber <= daysInJalaliMonth(year, month); dayNumber++) {
			var button = document.createElement('button');
			button.type = 'button';
			button.textContent = toPersian(dayNumber);
			button.addEventListener('click', (function (selectedDay) {
				return function () {
					input.value = toPersian(year + '/' + pad(month) + '/' + pad(selectedDay));
					syncHidden(input);
					closePicker();
				};
			}(dayNumber)));
			grid.appendChild(button);
		}
		activePicker.appendChild(grid);

		var tools = document.createElement('div');
		tools.className = 'crpcrm-jalali-picker-tools';
		var today = document.createElement('button');
		today.type = 'button';
		today.textContent = 'امروز';
		today.addEventListener('click', function () {
			var now = new Date();
			input.value = displayFromIso(now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()));
			syncHidden(input);
			closePicker();
		});
		var clear = document.createElement('button');
		clear.type = 'button';
		clear.textContent = 'پاک کردن';
		clear.addEventListener('click', function () {
			input.value = '';
			syncHidden(input);
			closePicker();
		});
		tools.appendChild(today);
		tools.appendChild(clear);
		activePicker.appendChild(tools);

		next.addEventListener('click', function () {
			month++;
			if (month > 12) {
				month = 1;
				year++;
			}
			renderPicker(input, year, month);
		});
		prev.addEventListener('click', function () {
			month--;
			if (month < 1) {
				month = 12;
				year--;
			}
			renderPicker(input, year, month);
		});

		input.parentNode.appendChild(activePicker);
	}

	function openPicker(input) {
		var jalali = parseJalali(input.value);
		if (!jalali) {
			var now = new Date();
			jalali = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
		}
		renderPicker(input, jalali[0], jalali[1]);
	}

	function closePicker() {
		if (activePicker && activePicker.parentNode) {
			activePicker.parentNode.removeChild(activePicker);
		}
		activePicker = null;
	}

	function initJalaliDatePickers() {
		document.querySelectorAll('.crpcrm-jalali-date').forEach(function (input) {
			var hidden = document.getElementById(input.dataset.crpcrmDateTarget || '');
			if (hidden && hidden.value && !input.value) {
				input.value = displayFromIso(hidden.value);
			}
			input.addEventListener('focus', function () { openPicker(input); });
			input.addEventListener('click', function () { openPicker(input); });
			input.addEventListener('input', function () { syncHidden(input); });
		});
		document.querySelectorAll('[data-crpcrm-datetime-target]').forEach(function (time) {
			time.addEventListener('change', function () {
				var dateInput = document.querySelector('[data-crpcrm-date-target="' + time.dataset.crpcrmDatetimeTarget + '"]');
				if (dateInput) {
					syncHidden(dateInput);
				}
			});
		});
		document.addEventListener('click', function (event) {
			if (activePicker && !event.target.closest('.crpcrm-jalali-field')) {
				closePicker();
			}
		});
		document.querySelectorAll('form').forEach(function (form) {
			form.addEventListener('submit', function () {
				form.querySelectorAll('.crpcrm-jalali-date').forEach(syncHidden);
			});
		});
	}

	function updateManualCustomerFields(form) {
		var selected = form.querySelector('input[name="customer_mode"]:checked');
		var isNew = selected && 'new' === selected.value;
		var existing = form.querySelector('.crpcrm-existing-customer-fields');
		var create = form.querySelector('.crpcrm-new-customer-fields');
		if (existing) { existing.style.display = isNew ? 'none' : ''; }
		if (create) { create.style.display = isNew ? '' : 'none'; }
		form.querySelectorAll('[name="new_customer_name"], [name="new_customer_phone"], [name="new_customer_province"], [name="new_customer_city"]').forEach(function (field) { field.required = isNew; });
		var customer = form.querySelector('[name="customer_id"]');
		if (customer) { customer.required = !isNew; }
	}

	function updateSalesActionFields(form) {
		var select = form.querySelector('.crpcrm-action-type-select');
		var action = select ? select.value : '';
		var fields = form.querySelectorAll('.crpcrm-conditional-field');
		fields.forEach(function (field) {
			field.style.display = 'none';
		});

		var followUp = form.querySelector('.crpcrm-follow-up-field');
		var lost = form.querySelector('.crpcrm-lost-reason-field');
		var invalid = form.querySelector('.crpcrm-invalid-reason-field');

		if ('schedule_follow_up' === action && followUp) {
			followUp.style.display = '';
		} else if ('mark_lost' === action && lost) {
			lost.style.display = '';
		} else if ('mark_invalid' === action && invalid) {
			invalid.style.display = '';
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		initJalaliDatePickers();
		document.querySelectorAll('.crpcrm-manual-request-form').forEach(function (form) {
			updateManualCustomerFields(form);
			form.querySelectorAll('input[name="customer_mode"]').forEach(function (radio) {
				radio.addEventListener('change', function () { updateManualCustomerFields(form); });
			});
		});
		document.querySelectorAll('.crpcrm-sales-action-form').forEach(function (form) {
			updateSalesActionFields(form);
			var select = form.querySelector('.crpcrm-action-type-select');
			if (select) {
				select.addEventListener('change', function () {
					updateSalesActionFields(form);
				});
			}
		});
	});
}());
