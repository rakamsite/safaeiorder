(function () {
	'use strict';

	if (window.NodeList && !NodeList.prototype.forEach) {
		NodeList.prototype.forEach = Array.prototype.forEach;
	}

	if (window.HTMLCollection && !HTMLCollection.prototype.forEach) {
		HTMLCollection.prototype.forEach = Array.prototype.forEach;
	}

	if (window.Element && !Element.prototype.matches) {
		Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
	}

	if (window.Element && !Element.prototype.closest) {
		Element.prototype.closest = function (selector) {
			var node = this;
			while (node && node.nodeType === 1) {
				if (node.matches && node.matches(selector)) {
					return node;
				}
				node = node.parentElement || node.parentNode;
			}
			return null;
		};
	}

	var persianDigits = ['Û°', 'Û±', 'Û²', 'Û³', 'Û´', 'Ûµ', 'Û¶', 'Û·', 'Û¸', 'Û¹'];
	var monthNames = ['ÙØ±ÙˆØ±Ø¯ÛŒÙ†', 'Ø§Ø±Ø¯ÛŒØ¨Ù‡Ø´Øª', 'Ø®Ø±Ø¯Ø§Ø¯', 'ØªÛŒØ±', 'Ù…Ø±Ø¯Ø§Ø¯', 'Ø´Ù‡Ø±ÛŒÙˆØ±', 'Ù…Ù‡Ø±', 'Ø¢Ø¨Ø§Ù†', 'Ø¢Ø°Ø±', 'Ø¯ÛŒ', 'Ø¨Ù‡Ù…Ù†', 'Ø§Ø³ÙÙ†Ø¯'];
	var weekDays = ['Ø´', 'ÛŒ', 'Ø¯', 'Ø³', 'Ú†', 'Ù¾', 'Ø¬'];
	var activePicker = null;

	function toLatin(value) {
		return String(value || '').replace(/[Û°-Û¹Ù -Ù©]/g, function (digit) {
			return 'Û°Û±Û²Û³Û´ÛµÛ¶Û·Û¸Û¹Ù Ù¡Ù¢Ù£Ù¤Ù¥Ù¦Ù§Ù¨Ù©'.indexOf(digit) % 10;
		});
	}

	function toPersian(value) {
		return String(value || '').replace(/\d/g, function (digit) {
			return persianDigits[parseInt(digit, 10)];
		});
	}

	function pad(number) {
		number = parseInt(number, 10) || 0;
		return number < 10 ? '0' + number : String(number);
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
		next.textContent = 'â€¹';
		var title = document.createElement('strong');
		title.textContent = monthNames[month - 1] + ' ' + toPersian(year);
		var prev = document.createElement('button');
		prev.type = 'button';
		prev.textContent = 'â€º';
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
		var todayDate = new Date();
		var todayJalali = gregorianToJalali(todayDate.getFullYear(), todayDate.getMonth() + 1, todayDate.getDate());
		for (var dayNumber = 1; dayNumber <= daysInJalaliMonth(year, month); dayNumber++) {
			var button = document.createElement('button');
			button.type = 'button';
			button.textContent = toPersian(dayNumber);
			if (todayJalali[0] === year && todayJalali[1] === month && todayJalali[2] === dayNumber) {
				button.classList.add('is-today');
			}
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
		today.textContent = 'Ø§Ù…Ø±ÙˆØ²';
		today.addEventListener('click', function () {
			var now = new Date();
			input.value = displayFromIso(now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()));
			syncHidden(input);
			closePicker();
		});
		var clear = document.createElement('button');
		clear.type = 'button';
		clear.textContent = 'Ù¾Ø§Ú© Ú©Ø±Ø¯Ù†';
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
		if (create) { create.style.display = isNew ? 'block' : 'none'; }
		form.querySelectorAll('[name="new_customer_name"], [name="new_customer_phone"], [name="new_customer_province"], [name="new_customer_city"], [name="new_customer_source"]').forEach(function (field) { field.required = isNew; field.disabled = !isNew; });
		var customer = form.querySelector('[name="customer_id"]');
		if (customer) { customer.required = !isNew; customer.disabled = isNew; }
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

	function initRegistrationFieldSorting() {
		if (!window.jQuery || !window.jQuery.fn.sortable) {
			return;
		}

		window.jQuery('.crpcrm-sortable-registration-fields').sortable({
			axis: 'y',
			cursor: 'grabbing',
			handle: '.crpcrm-drag-handle',
			helper: function (event, row) {
				row.children().each(function () {
					window.jQuery(this).width(window.jQuery(this).width());
				});
				return row;
			},
			placeholder: 'crpcrm-registration-field-placeholder',
			update: function (event, ui) {
				window.jQuery(ui.item).parent().children('tr').each(function (index) {
					window.jQuery(this).find('.crpcrm-registration-field-order').val(index);
				});
			}
		});
	}

	function getAdminConfig() {
		return window.crpcrmAdmin || {};
	}

	function clearElement(element) {
		if (!element) {
			return;
		}

		while (element.firstChild) {
			element.removeChild(element.firstChild);
		}
	}

	function renderStatusMessage(container, className, message) {
		clearElement(container);
		if (!container) {
			return;
		}

		var notice = document.createElement('div');
		notice.className = className;
		notice.textContent = message;
		container.appendChild(notice);
	}

	function normalizeManualPhone(value) {
		var normalized = toLatin(String(value || '')).replace(/[^0-9+]/g, '');
		if (normalized.indexOf('+98') === 0) {
			normalized = normalized.substring(1);
		} else if (normalized.indexOf('0098') === 0) {
			normalized = normalized.substring(2);
		} else if (normalized.indexOf('09') === 0 && normalized.length === 11) {
			normalized = '98' + normalized.substring(1);
		} else if (normalized.indexOf('9') === 0 && normalized.length === 10) {
			normalized = '98' + normalized;
		}
		return normalized;
	}

	function isValidManualPhone(value) {
		return /^989\d{9}$/.test(String(value || ''));
	}

	function renderManualCustomerResult(form, state) {
		var result = form.querySelector('.crpcrm-manual-customer-result');
		var editor = form.querySelector('.crpcrm-manual-customer-editor');
		var hiddenCustomer = form.querySelector('input[name="customer_id"]');
		if (!result || !editor || !hiddenCustomer) {
			return;
		}

		form._crpcrmManualCustomerState = state || null;
		hiddenCustomer.value = state && state.customer_id ? String(state.customer_id) : '';
		clearElement(result);

		if (!state || !state.state || state.state === 'idle') {
			result.classList.remove('is-visible');
			result.setAttribute('data-customer-state', 'idle');
			editor.hidden = true;
			editor.classList.remove('is-visible');
			clearElement(editor);
			return;
		}

		result.classList.add('is-visible');
		result.setAttribute('data-customer-state', state.state);

		if (state.summary) {
			var summary = document.createElement('p');
			summary.className = 'crpcrm-manual-customer-summary';
			summary.textContent = state.summary;
			result.appendChild(summary);
		}

		if (state.message) {
			var message = document.createElement('p');
			message.className = 'crpcrm-manual-customer-message';
			message.appendChild(document.createTextNode(state.message + (state.action_label ? ' ' : '')));
			if (state.action_label) {
				var action = document.createElement('button');
				action.type = 'button';
				action.className = 'button-link crpcrm-manual-customer-toggle';
				action.textContent = state.action_label;
				message.appendChild(action);
			}
			result.appendChild(message);
		}

		if (state.state === 'incomplete' || state.state === 'not_found') {
			editor.innerHTML = state.form_html || '';
			toggleManualCustomerEditor(editor, false);
		} else {
			toggleManualCustomerEditor(editor, false);
			clearElement(editor);
		}
	}

	function setManualCustomerEditorEnabled(editor, enabled) {
		if (!editor) {
			return;
		}

		Array.prototype.forEach.call(editor.querySelectorAll('input, select, textarea'), function (field) {
			field.disabled = !enabled;
		});

		Array.prototype.forEach.call(editor.querySelectorAll('button'), function (button) {
			if (button.classList.contains('crpcrm-manual-customer-toggle')) {
				return;
			}
			button.disabled = !enabled;
		});
	}

	function toggleManualCustomerEditor(editor, visible) {
		if (!editor) {
			return;
		}

		editor.hidden = !visible;
		editor.classList.toggle('is-visible', !!visible);
		setManualCustomerEditorEnabled(editor, !!visible);
	}

	function validateManualCustomerEditor(editor, messages) {
		var fields = editor ? editor.querySelectorAll('input, select, textarea') : [];
		var fallbackMessage = (messages && messages.manualCustomerSaveError) || 'Ø°Ø®ÛŒØ±Ù‡ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.';

		for (var i = 0; i < fields.length; i += 1) {
			var field = fields[i];
			if (!field || field.disabled) {
				continue;
			}
			if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
				if (typeof field.reportValidity === 'function') {
					field.reportValidity();
				}
				return field.validationMessage || fallbackMessage;
			}
		}

		return '';
	}

	function buildManualRequestUrl(form, formId) {
		var baseUrl = form.getAttribute('data-request-page-url') || '';
		if (!baseUrl || !formId || !window.URL) {
			return '';
		}

		var url = new URL(baseUrl, window.location.origin);
		var phone = form.querySelector('.crpcrm-manual-customer-phone');
		var customer = form.querySelector('input[name="customer_id"]');
		var source = form.querySelector('select[name="request_source"]');
		var statusField = form.querySelector('select[name="request_status"]');

		url.searchParams.set('form_id', formId);
		if (phone && phone.value.trim()) {
			url.searchParams.set('customer_phone', phone.value.trim());
		}
		if (customer && customer.value) {
			url.searchParams.set('customer_id', customer.value);
		}
		if (source && source.value) {
			url.searchParams.set('request_source', source.value);
		}
		if (statusField && statusField.value) {
			url.searchParams.set('request_status', statusField.value);
		}

		return url.toString();
	}

	function submitManualCustomerEditor(form, config) {
		var editor = form.querySelector('.crpcrm-manual-customer-editor');
		if (!editor || !editor.querySelector('.crpcrm-manual-customer-editor-form')) {
			return;
		}

		var editorForm = editor.querySelector('.crpcrm-manual-customer-editor-form');
		var status = editor.querySelector('.crpcrm-manual-customer-editor-status');
		var button = editor.querySelector('.crpcrm-manual-customer-save');
		if (!button) {
			return;
		}

		var validationError = validateManualCustomerEditor(editorForm, config);
		if (validationError) {
			if (status) {
				status.textContent = validationError;
			}
			return;
		}

		if (!window.fetch || !config.ajaxUrl || !config.manualCustomerSaveNonce) {
			if (status) {
				status.textContent = config.manualCustomerSaveError || 'Ø°Ø®ÛŒØ±Ù‡ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.';
			}
			return;
		}

		var formData = new FormData();
		formData.append('action', 'crpcrm_manual_request_customer_save');
		formData.append('nonce', config.manualCustomerSaveNonce);
		formData.append('mode', editorForm.getAttribute('data-mode') || 'create');
		formData.append('customer_id', editorForm.getAttribute('data-customer-id') || '');
		Array.prototype.forEach.call(editorForm.querySelectorAll('input, select, textarea'), function (field) {
			if (!field.name || field.disabled) {
				return;
			}
			formData.append(field.name, field.value);
		});

		if (status) {
			status.textContent = config.manualCustomerSaveLoading || 'Ø¯Ø± Ø­Ø§Ù„ Ø°Ø®ÛŒØ±Ù‡ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÛŒ...';
		}
		button.disabled = true;

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					throw new Error((payload && payload.data && payload.data.message) || config.manualCustomerSaveError || 'Ø°Ø®ÛŒØ±Ù‡ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.');
				}

				renderManualCustomerResult(form, payload.data);
				if (status) {
					status.textContent = payload.data.message || '';
				}
			})
			.catch(function (error) {
				if (status) {
					status.textContent = (error && error.message) || config.manualCustomerSaveError || 'Ø°Ø®ÛŒØ±Ù‡ Ø§Ø·Ù„Ø§Ø¹Ø§Øª Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.';
				}
			})
			.finally(function () {
				button.disabled = false;
			});
	}

	function initManualRequestFlow(form, config) {
		var phoneInput = form.querySelector('.crpcrm-manual-customer-phone');
		var status = form.querySelector('.crpcrm-manual-customer-status');
		var typeSelect = form.querySelector('.crpcrm-manual-request-type-selector');
		var editor = form.querySelector('.crpcrm-manual-customer-editor');
		var lookupTimer = null;
		var requestId = 0;

		if (!phoneInput) {
			return;
		}

		if (editor && editor.hidden) {
			toggleManualCustomerEditor(editor, false);
		}

		function runLookup() {
			var phone = phoneInput.value.trim();
			var normalized = normalizeManualPhone(phone);
			var hiddenCustomer = form.querySelector('input[name="customer_id"]');

			if (!isValidManualPhone(normalized)) {
				if (hiddenCustomer) {
					hiddenCustomer.value = '';
				}
				if (status) {
					status.textContent = '';
				}
				renderManualCustomerResult(form, { state: 'idle' });
				return;
			}

			if (!window.fetch || !config.ajaxUrl || !config.manualCustomerLookupNonce) {
				return;
			}

			requestId += 1;
			var currentRequestId = requestId;
			if (status) {
				status.textContent = config.manualCustomerLoading || 'Ø¯Ø± Ø­Ø§Ù„ Ø¬Ø³ØªØ¬ÙˆÛŒ Ù…Ø´ØªØ±ÛŒ...';
			}

			var formData = new FormData();
			formData.append('action', 'crpcrm_manual_request_customer_lookup');
			formData.append('nonce', config.manualCustomerLookupNonce);
			formData.append('phone', phone);
			if (hiddenCustomer && hiddenCustomer.value) {
				formData.append('customer_id', hiddenCustomer.value);
			}

			fetch(config.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					if (currentRequestId !== requestId) {
						return;
					}
					if (!payload || !payload.success || !payload.data) {
						throw new Error(config.manualCustomerLookupError || 'Ø¬Ø³ØªØ¬ÙˆÛŒ Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.');
					}
					if (status) {
						status.textContent = '';
					}
					renderManualCustomerResult(form, payload.data);
				})
				.catch(function () {
					if (currentRequestId !== requestId) {
						return;
					}
					if (status) {
						status.textContent = config.manualCustomerLookupError || 'Ø¬Ø³ØªØ¬ÙˆÛŒ Ù…Ø´ØªØ±ÛŒ Ø§Ù†Ø¬Ø§Ù… Ù†Ø´Ø¯.';
					}
					renderManualCustomerResult(form, { state: 'idle' });
				});
		}

		phoneInput.addEventListener('input', function () {
			var hiddenCustomer = form.querySelector('input[name="customer_id"]');
			if (hiddenCustomer) {
				hiddenCustomer.value = '';
			}
			window.clearTimeout(lookupTimer);
			lookupTimer = window.setTimeout(runLookup, 350);
		});

		form.addEventListener('click', function (event) {
			if (event.target.classList.contains('crpcrm-manual-customer-toggle')) {
				event.preventDefault();
				if (!editor) {
					return;
				}
				toggleManualCustomerEditor(editor, editor.hidden);
				return;
			}

			if (event.target.classList.contains('crpcrm-manual-customer-save')) {
				event.preventDefault();
				submitManualCustomerEditor(form, config);
			}
		});

		if (typeSelect) {
			typeSelect.addEventListener('change', function () {
				var url = buildManualRequestUrl(form, typeSelect.value);
				if (url) {
					window.location.href = url;
				}
			});
		}

		form.addEventListener('submit', function (event) {
			var hiddenCustomer = form.querySelector('input[name="customer_id"]');
			if (hiddenCustomer && !hiddenCustomer.value) {
				event.preventDefault();
				if (status) {
					status.textContent = config.manualCustomerCreateRequired || 'Ø¨Ø±Ø§ÛŒ Ø«Ø¨Øª Ø¯Ø±Ø®ÙˆØ§Ø³Øª Ø§Ø¨ØªØ¯Ø§ Ø¨Ø§ÛŒØ¯ Ù…Ø´ØªØ±ÛŒ Ø§ÛŒØ¬Ø§Ø¯ Ø´ÙˆØ¯.';
				}
			}
		});
	}

	function createProductChip(container, hidden, product, labels) {
		var chip = document.createElement('span');
		chip.className = 'crpcrm-product-chip';
		chip.setAttribute('data-product-id', String(product.id));

		if (product.thumbnail) {
			var image = document.createElement('img');
			image.src = product.thumbnail;
			image.alt = product.name;
			chip.appendChild(image);
		}

		var text = document.createElement('span');
		text.textContent = product.name;
		chip.appendChild(text);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'crpcrm-product-chip-remove';
		remove.setAttribute('aria-label', labels.productRemoveLabel || 'Ø­Ø°Ù Ù…Ø­ØµÙˆÙ„');
		remove.textContent = 'Ã—';
		remove.addEventListener('click', function () {
			chip.remove();
			syncProductHiddenValue(container, hidden);
		});
		chip.appendChild(remove);

		container.appendChild(chip);
		syncProductHiddenValue(container, hidden);
	}

	function syncProductHiddenValue(container, hidden) {
		var ids = Array.prototype.map.call(container.querySelectorAll('.crpcrm-product-chip'), function (chip) {
			return chip.getAttribute('data-product-id');
		});
		hidden.value = ids.join(',');
	}

	function initProductSearch(scope, labels) {
		if (!document.body.dataset.crpcrmProductSearchDismissBound) {
			document.body.dataset.crpcrmProductSearchDismissBound = '1';
			document.addEventListener('click', function (event) {
				if (event.target.closest('.crpcrm-product-search')) {
					return;
				}

				document.querySelectorAll('.crpcrm-product-search-results').forEach(function (results) {
					results.hidden = true;
				});
			});
		}

		scope.querySelectorAll('.crpcrm-product-search').forEach(function (wrapper) {
			if (wrapper.dataset.initialized === '1') {
				return;
			}
			wrapper.dataset.initialized = '1';

			var hidden = wrapper.querySelector('.crpcrm-product-search-value');
			var input = wrapper.querySelector('.crpcrm-product-search-input');
			var results = wrapper.querySelector('.crpcrm-product-search-results');
			var selected = wrapper.querySelector('.crpcrm-product-search-selected');
			var abortController = null;

			function renderResults(items) {
				clearElement(results);
				if (!items.length) {
					var empty = document.createElement('div');
					empty.className = 'crpcrm-product-search-empty';
					empty.textContent = labels.productSearchEmpty || 'Ù…Ø­ØµÙˆÙ„ÛŒ Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯.';
					results.appendChild(empty);
					results.hidden = false;
					return;
				}

				items.forEach(function (product) {
					if (selected.querySelector('[data-product-id="' + product.id + '"]')) {
						return;
					}
					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'crpcrm-product-search-result';
					if (product.thumbnail) {
						var image = document.createElement('img');
						image.src = product.thumbnail;
						image.alt = product.name;
						button.appendChild(image);
					}
					var name = document.createElement('span');
					name.textContent = product.name;
					button.appendChild(name);
					button.addEventListener('click', function () {
						createProductChip(selected, hidden, product, labels);
						input.value = '';
						results.hidden = true;
						clearElement(results);
						input.focus();
					});
					results.appendChild(button);
				});

				results.hidden = !results.children.length;
			}

			input.addEventListener('input', function () {
				var term = input.value.trim();
				if (term.length < (labels.productSearchMin || 2)) {
					results.hidden = true;
					clearElement(results);
					return;
				}

				if (!labels.ajaxUrl || !window.fetch || !window.URLSearchParams) {
					results.hidden = true;
					clearElement(results);
					return;
				}

				results.hidden = false;
				renderStatusMessage(results, 'crpcrm-product-search-empty', labels.productSearchLoading || 'Ø¯Ø± Ø­Ø§Ù„ Ø¬Ø³ØªØ¬Ùˆ...');

				if (window.AbortController) {
					if (abortController) {
						abortController.abort();
					}
					abortController = new AbortController();
				}

				var params = new URLSearchParams({
					action: 'crpcrm_search_products',
					nonce: labels.productSearchNonce || '',
					term: term
				});

				var requestOptions = {
					credentials: 'same-origin'
				};
				if (abortController) {
					requestOptions.signal = abortController.signal;
				}

				fetch((labels.ajaxUrl || '') + '?' + params.toString(), requestOptions)
					.then(function (response) { return response.json(); })
					.then(function (payload) {
						renderResults(payload && payload.success && Array.isArray(payload.data) ? payload.data : []);
					})
					.catch(function (error) {
						if (error && error.name === 'AbortError') {
							return;
						}
						results.hidden = true;
					});
			});
		});
	}

	function syncUploadedStore(wrapper) {
		var store = wrapper.querySelector('.crpcrm-uploaded-files-store');
		if (!store) {
			return;
		}

		var files = Array.prototype.map.call(wrapper.querySelectorAll('.crpcrm-file-upload-row[data-uploaded]'), function (row) {
			try {
				return JSON.parse(row.getAttribute('data-uploaded'));
			} catch (error) {
				return null;
			}
		}).filter(function (item) {
			return !!item;
		});

		store.value = JSON.stringify(files);
	}

	function isAsyncUploadSupported() {
		return !!(window.fetch && window.FormData && window.Promise && window.JSON);
	}

	function getUploadConfigLabel(config, key, fallback) {
		return config && config[key] ? config[key] : fallback;
	}

	function getFileExtension(file) {
		var parts = String(file && file.name ? file.name : '').toLowerCase().split('.');
		return parts.length > 1 ? parts.pop() : '';
	}

	function isAllowedUploadFile(file) {
		var extension = getFileExtension(file);
		var type = String(file && file.type ? file.type : '').toLowerCase();
		return /^(image\/(jpeg|png|gif|webp)|application\/pdf)$/i.test(type) || /^(jpg|jpeg|png|gif|webp|pdf)$/.test(extension);
	}

	function isUploadFieldRequired(wrapper) {
		return !!(wrapper && wrapper.dataset && '1' === wrapper.dataset.fieldRequired);
	}

	function getUploadRows(wrapper) {
		var list = wrapper ? wrapper.querySelector('.crpcrm-file-upload-list') : null;
		return list ? list.querySelectorAll('.crpcrm-file-upload-row') : [];
	}

	function getUploadedFiles(wrapper) {
		var store = wrapper ? wrapper.querySelector('.crpcrm-uploaded-files-store') : null;
		if (!store || !store.value) {
			return [];
		}

		try {
			return JSON.parse(store.value) || [];
		} catch (error) {
			return [];
		}
	}

	function getUploadedFilesCount(wrapper) {
		return getUploadedFiles(wrapper).length;
	}

	function getUploadedFilesTotalSize(wrapper) {
		return getUploadedFiles(wrapper).reduce(function (total, file) {
			return total + parseInt((file && file.size) ? file.size : 0, 10);
		}, 0);
	}

	function getWrapperMaxFiles(wrapper, config) {
		var local = wrapper && wrapper.dataset ? parseInt(wrapper.dataset.maxFiles || '', 10) : 0;
		var globalValue = config ? parseInt(config.fileUploadMaxFiles || '', 10) : 0;
		return local || globalValue || 5;
	}

	function getWrapperMaxTotalSize(wrapper, config) {
		var local = wrapper && wrapper.dataset ? parseInt(wrapper.dataset.maxTotalSize || '', 10) : 0;
		var globalValue = config ? parseInt(config.fileUploadMaxTotalSize || '', 10) : 0;
		return local || globalValue || 26214400;
	}

	function refreshUploadRequirements(wrapper) {
		if (!wrapper) {
			return;
		}

		var rows = getUploadRows(wrapper);
		var requireOne = isUploadFieldRequired(wrapper);
		var hasUploaded = !!wrapper.querySelector('.crpcrm-file-upload-row[data-uploaded]');
		var markRequired = requireOne && !hasUploaded;
		var assigned = false;
		var requiredInput = null;

		Array.prototype.forEach.call(rows, function (row) {
			var input = row.querySelector('input[type="file"]');
			if (!input) {
				return;
			}

			if (row.hasAttribute('data-uploaded') || !markRequired) {
				input.required = false;
				input.removeAttribute('required');
				input.removeAttribute('data-required');
				if ('function' === typeof input.setCustomValidity) {
					input.setCustomValidity('');
				}
				return;
			}

			if (!assigned) {
				input.required = true;
				input.setAttribute('data-required', '1');
				assigned = true;
				requiredInput = input;
			} else {
				input.required = false;
				input.removeAttribute('required');
				input.removeAttribute('data-required');
				if ('function' === typeof input.setCustomValidity) {
					input.setCustomValidity('');
				}
				return;
			}

			if ('function' === typeof input.setCustomValidity) {
				input.setCustomValidity(input.files && input.files.length ? '' : 'Ø­Ø¯Ø§Ù‚Ù„ ÛŒÚ© ÙØ§ÛŒÙ„ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.');
				return;
			}
		});

		if (requiredInput && 'function' === typeof requiredInput.setCustomValidity && requiredInput.files && requiredInput.files.length) {
			requiredInput.setCustomValidity('');
		}
	}

	function renderFallbackFilePreview(preview, file, config) {
		preview.innerHTML = '';
		if (!file) {
			return;
		}

		var label = document.createElement('span');
		label.className = 'crpcrm-file-upload-name';
		label.textContent = getFileDisplayName(file, getUploadConfigLabel(config, 'fileUploadedLabel', 'Uploaded'));
		preview.appendChild(label);
	}

	function getUploadSelectedFallbackLabel(config) {
		return getUploadConfigLabel(config, 'fileUploadNoSelection', 'هنوز فایلی انتخاب نشده است.');
	}

	function updateUploadSelectedLabel(row, input, config, fallbackText) {
		var selected = row ? row.querySelector('.crpcrm-file-upload-selected') : null;
		if (!selected) {
			return;
		}

		var emptyLabel = getUploadSelectedFallbackLabel(config);
		var label = fallbackText || emptyLabel;
		if (input && input.files && input.files.length) {
			label = input.files[0].name || label;
		}

		selected.textContent = label;
		selected.classList.toggle('is-empty', label === emptyLabel);
	}

	function ensureUploadInputChrome(row, input, config) {
		if (!row || !input) {
			return;
		}

		if (!input.classList.contains('crpcrm-file-upload-input')) {
			input.classList.add('crpcrm-file-upload-input');
		}

		var picker = row.querySelector('.crpcrm-file-upload-picker');
		if (!picker) {
			picker = document.createElement('label');
			picker.className = 'crpcrm-file-upload-picker';

			var pickerLabel = document.createElement('span');
			pickerLabel.className = 'crpcrm-file-upload-picker-label';
			pickerLabel.textContent = getUploadConfigLabel(config, 'fileChooseLabel', 'انتخاب فایل');

			row.insertBefore(picker, input);
			picker.appendChild(pickerLabel);
			picker.appendChild(input);
		}

		var selected = row.querySelector('.crpcrm-file-upload-selected');
		if (!selected) {
			selected = document.createElement('span');
			selected.className = 'crpcrm-file-upload-selected is-empty';
			picker.insertAdjacentElement('afterend', selected);
		}

		updateUploadSelectedLabel(row, input, config);
	}

	function getFileDisplayName(file, fallback) {
		if (!file) {
			return fallback || '';
		}

		return file.display_name || file.original_name || file.name || file.filename || fallback || '';
	}

	function isPdfUpload(file) {
		var type = String((file && (file.type || file.mime_type || '')) || '').toLowerCase();
		var name = String(getFileDisplayName(file, '')).toLowerCase();
		return type === 'application/pdf' || /\.pdf$/i.test(name) || !!file.is_pdf;
	}

	function getUploadedRowData(row) {
		if (!row || !row.hasAttribute('data-uploaded')) {
			return null;
		}

		try {
			return JSON.parse(row.getAttribute('data-uploaded'));
		} catch (error) {
			return null;
		}
	}

	function showUploadRowMessage(row, message) {
		var preview = row ? row.querySelector('.crpcrm-file-upload-preview') : null;
		if (!preview) {
			return;
		}

		preview.innerHTML = '';
		var notice = document.createElement('span');
		notice.className = 'crpcrm-file-upload-name';
		notice.textContent = message;
		preview.appendChild(notice);
	}

	function replaceOrRemoveUploadRow(row, list, name, wrapper, config) {
		if (!row || !list) {
			return;
		}

		if (list.children.length > 1) {
			row.remove();
		} else {
			row.replaceWith(createUploadRow(name, isUploadFieldRequired(wrapper), wrapper, config));
		}

		syncUploadedStore(wrapper);
		refreshUploadRequirements(wrapper);
	}

	function deletePendingUploadRow(row, list, name, wrapper, config) {
		var uploaded = getUploadedRowData(row);
		if (!uploaded || !uploaded.upload_token) {
			replaceOrRemoveUploadRow(row, list, name, wrapper, config);
			return;
		}

		if (row.dataset.pendingDelete === '1') {
			return;
		}

		if (!window.fetch || !config || !config.ajaxUrl || !config.fileDeletePendingNonce) {
			showUploadRowMessage(row, getUploadConfigLabel(config, 'fileDeletePendingFallback', 'Unable to remove this file in this browser.'));
			return;
		}

		row.dataset.pendingDelete = '1';
		row.classList.add('is-uploading');

		var formData = new FormData();
		formData.append('action', 'crpcrm_delete_pending_request_file');
		formData.append('nonce', config.fileDeletePendingNonce);
		formData.append('upload_token', String(uploaded.upload_token || ''));
		formData.append('field_key', (wrapper && wrapper.dataset && wrapper.dataset.fieldName) ? wrapper.dataset.fieldName : '');

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success) {
					throw new Error((payload && payload.data && payload.data.message) || getUploadConfigLabel(config, 'fileDeletePendingError', 'Delete failed.'));
				}

				replaceOrRemoveUploadRow(row, list, name, wrapper, config);
			})
			.catch(function (error) {
				showUploadRowMessage(row, (error && error.message) || getUploadConfigLabel(config, 'fileDeletePendingError', 'Delete failed.'));
				row.classList.remove('is-uploading');
				delete row.dataset.pendingDelete;
			});
	}

	function setPreviewContent(preview, file, config) {
		preview.innerHTML = '';
		if (!file) {
			return;
		}

		var fileName = getFileDisplayName(file, '');

		if (String(file.type || '').indexOf('image/') === 0) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'crpcrm-file-thumb';
			button.setAttribute('aria-label', getUploadConfigLabel(config, 'filePreviewLabel', 'View file'));
			button.setAttribute('title', getUploadConfigLabel(config, 'filePreviewLabel', 'View file'));
			button.setAttribute('data-crpcrm-file-preview', '1');
			button.setAttribute('data-full-url', file.previewUrl || file.url || '');
			button.setAttribute('data-download-url', file.download_url || file.url || file.previewUrl || '');
			button.setAttribute('data-filename', fileName);
			button.setAttribute('data-mime-type', file.type || file.mime_type || '');
			var image = document.createElement('img');
			image.src = file.previewUrl || file.url || '';
			image.alt = fileName;
			button.appendChild(image);
			preview.appendChild(button);
		} else if (isPdfUpload(file)) {
			var pdfCard = document.createElement('button');
			pdfCard.type = 'button';
			pdfCard.className = 'crpcrm-file-upload-card crpcrm-file-upload-card-pdf crpcrm-file-pdf';
			pdfCard.setAttribute('data-crpcrm-file-preview', '1');
			pdfCard.setAttribute('data-full-url', file.url || file.download_url || '');
			pdfCard.setAttribute('data-download-url', file.download_url || file.url || '');
			pdfCard.setAttribute('data-filename', fileName);
			pdfCard.setAttribute('data-mime-type', file.type || file.mime_type || 'application/pdf');
			pdfCard.setAttribute('aria-label', getUploadConfigLabel(config, 'filePreviewLabel', 'View file'));
			pdfCard.setAttribute('title', getUploadConfigLabel(config, 'filePreviewLabel', 'View file'));
			var pdfIcon = document.createElement('span');
			pdfIcon.className = 'crpcrm-file-upload-card-icon';
			pdfIcon.textContent = 'PDF';
			var pdfLabel = document.createElement('span');
			pdfLabel.className = 'crpcrm-file-upload-name';
			pdfLabel.textContent = fileName;
			pdfCard.appendChild(pdfIcon);
			pdfCard.appendChild(pdfLabel);
			preview.appendChild(pdfCard);
			if (file.download_url) {
				var pdfDownload = document.createElement('a');
				pdfDownload.className = 'crpcrm-file-download-link';
				pdfDownload.href = file.download_url;
				pdfDownload.textContent = getUploadConfigLabel(config, 'fileDownloadLabel', 'Download file');
				if (fileName) {
					pdfDownload.setAttribute('download', fileName);
				}
				preview.appendChild(pdfDownload);
			}
		} else {
			var label = document.createElement('span');
			label.className = 'crpcrm-file-upload-name';
			label.textContent = fileName;
			preview.appendChild(label);
			if (file.download_url) {
				var download = document.createElement('a');
				download.className = 'crpcrm-file-download-link';
				download.href = file.download_url;
				download.textContent = getUploadConfigLabel(config, 'fileDownloadLabel', 'Download file');
				if (fileName) {
					download.setAttribute('download', fileName);
				}
				preview.appendChild(download);
			}
		}
	}

	function uploadSelectedFile(input, preview, wrapper, config) {
		if (!input.files || !input.files[0] || !config) {
			return;
		}

		var row = input.closest('.crpcrm-file-upload-row');
		if (!row) {
			return;
		}
		var file = input.files[0];
		var fallbackMessage = getUploadConfigLabel(config, 'fileUploadFallback', 'This browser does not support automatic uploads.');

		if (!isAllowedUploadFile(file)) {
			preview.innerHTML = '';
			var invalid = document.createElement('span');
			invalid.className = 'crpcrm-file-upload-name';
			invalid.textContent = getUploadConfigLabel(config, 'fileUploadInvalid', 'Invalid file type.');
			preview.appendChild(invalid);
			input.value = '';
			refreshUploadRequirements(wrapper);
			return;
		}

		if (file.size && config.fileUploadMaxSize && file.size > parseInt(config.fileUploadMaxSize, 10)) {
			preview.innerHTML = '';
			var tooLarge = document.createElement('span');
			tooLarge.className = 'crpcrm-file-upload-name';
			tooLarge.textContent = getUploadConfigLabel(config, 'fileUploadTooLarge', 'File is too large.');
			preview.appendChild(tooLarge);
			input.value = '';
			refreshUploadRequirements(wrapper);
			return;
		}

		if ((getUploadedFilesCount(wrapper) + 1) > getWrapperMaxFiles(wrapper, config)) {
			preview.innerHTML = '';
			var tooMany = document.createElement('span');
			tooMany.className = 'crpcrm-file-upload-name';
			tooMany.textContent = getUploadConfigLabel(config, 'fileUploadMaxFilesMessage', 'حداکثر 5 فایل مجاز است.');
			preview.appendChild(tooMany);
			input.value = '';
			refreshUploadRequirements(wrapper);
			return;
		}

		if ((getUploadedFilesTotalSize(wrapper) + (file.size || 0)) > getWrapperMaxTotalSize(wrapper, config)) {
			preview.innerHTML = '';
			var totalTooLarge = document.createElement('span');
			totalTooLarge.className = 'crpcrm-file-upload-name';
			totalTooLarge.textContent = getUploadConfigLabel(config, 'fileUploadTotalTooLarge', 'مجموع حجم فایل‌ها بیش از حد مجاز است.');
			preview.appendChild(totalTooLarge);
			input.value = '';
			refreshUploadRequirements(wrapper);
			return;
		}

		if (!isAsyncUploadSupported() || !config.ajaxUrl || !config.fileUploadNonce) {
			renderFallbackFilePreview(preview, file, config);
			refreshUploadRequirements(wrapper);
			return;
		}

		var formData = new FormData();
		formData.append('action', 'crpcrm_upload_request_file');
		formData.append('nonce', config.fileUploadNonce);
		formData.append('field_key', (wrapper && wrapper.dataset && wrapper.dataset.fieldName) ? wrapper.dataset.fieldName : '');
		formData.append('current_file_count', String(getUploadedFilesCount(wrapper)));
		formData.append('current_total_size', String(getUploadedFilesTotalSize(wrapper)));
		formData.append('file', file);

		clearElement(preview);
		var loading = document.createElement('span');
		loading.className = 'crpcrm-file-upload-name';
		loading.textContent = config.fileUploadLoading || 'Uploading...';
		preview.appendChild(loading);
		row.classList.add('is-uploading');

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					throw new Error((payload && payload.data && payload.data.message) || config.fileUploadError || 'Upload failed');
				}

				var uploaded = payload.data;
				if (String(uploaded.type || '').indexOf('image/') === 0 && !uploaded.previewUrl) {
					uploaded.previewUrl = window.URL && URL.createObjectURL ? URL.createObjectURL(file) : uploaded.previewUrl;
				}

				row.setAttribute('data-uploaded', JSON.stringify(uploaded));
				input.value = '';
				input.removeAttribute('name');
				input.disabled = true;
				input.removeAttribute('data-required');
				setPreviewContent(preview, uploaded, config);
				syncUploadedStore(wrapper);
				refreshUploadRequirements(wrapper);
			})
			.catch(function (error) {
				var message = (error && error.message) || config.fileUploadError || 'Upload failed';
				if (!window.fetch) {
					message = fallbackMessage;
				} else if (error && ('TypeError' === error.name || /failed to fetch/i.test(message))) {
					message = getUploadConfigLabel(config, 'fileUploadNetwork', message);
				}
				preview.innerHTML = '';
				var notice = document.createElement('span');
				notice.className = 'crpcrm-file-upload-name';
				notice.textContent = message;
				preview.appendChild(notice);
				row.removeAttribute('data-uploaded');
				syncUploadedStore(wrapper);
				refreshUploadRequirements(wrapper);
			})
			.finally(function () {
				row.classList.remove('is-uploading');
			});
	}

	function bindUploadRow(row, wrapper, config) {
		var input = row.querySelector('input[type="file"]');
		var preview = row.querySelector('.crpcrm-file-upload-preview');
		if (!preview) {
			preview = document.createElement('div');
			preview.className = 'crpcrm-file-upload-preview';
			if (input && input.nextSibling) {
				row.insertBefore(preview, input.nextSibling);
			} else {
				row.appendChild(preview);
			}
		}

		if (row.dataset.boundUpload === '1') {
			return;
		}
		row.dataset.boundUpload = '1';

		if (input) {
			input.setAttribute('aria-label', input.getAttribute('aria-label') || 'انتخاب فایل');
			ensureUploadInputChrome(row, input, config);
			input.addEventListener('change', function () {
				updateUploadSelectedLabel(row, input, config);
				uploadSelectedFile(input, preview, wrapper, config);
			});
		}
	}

	function createUploadRow(name, required, wrapper, config) {
		var row = document.createElement('div');
		row.className = 'crpcrm-file-upload-row';

		var input = document.createElement('input');
		input.type = 'file';
		input.name = name + '[]';
		input.accept = '.jpg,.jpeg,.png,.webp,.gif,.pdf';
		input.className = 'crpcrm-file-upload-input';
		input.setAttribute('aria-label', 'انتخاب فایل');
		if (required) {
			input.required = true;
			input.setAttribute('data-required', '1');
		}
		row.appendChild(input);

		var preview = document.createElement('div');
		preview.className = 'crpcrm-file-upload-preview';
		row.appendChild(preview);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'crpcrm-file-upload-remove';
		remove.setAttribute('aria-label', 'حذف فایل');
		remove.setAttribute('title', 'حذف فایل');
		remove.textContent = '×';
		row.appendChild(remove);

		bindUploadRow(row, wrapper, config);
		return row;
	}

	function bindSubmitGuard(form) {
		if (!form || form.dataset.submitGuardBound === '1') {
			return;
		}

		function setButtonsDisabled(disabled) {
			Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'), function (button) {
				if (disabled) {
					if (!button.disabled) {
						button.disabled = true;
						button.setAttribute('data-crpcrm-temporarily-disabled', '1');
					}
					return;
				}

				if (button.getAttribute('data-crpcrm-temporarily-disabled') === '1') {
					button.disabled = false;
					button.removeAttribute('data-crpcrm-temporarily-disabled');
				}
			});
		}

		function releaseSubmitGuard() {
			if (form.dataset.submitGuardTimer) {
				window.clearTimeout(Number(form.dataset.submitGuardTimer));
				delete form.dataset.submitGuardTimer;
			}
			delete form.dataset.crpcrmSubmitting;
			setButtonsDisabled(false);
		}

		form.dataset.submitGuardBound = '1';
		form.addEventListener('invalid', function () {
			releaseSubmitGuard();
		}, true);
		window.addEventListener('pageshow', releaseSubmitGuard);
		form.addEventListener('submit', function (event) {
			if (form.dataset.crpcrmSubmitting === '1') {
				event.preventDefault();
				return;
			}

			if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
				event.preventDefault();
				releaseSubmitGuard();
				return;
			}

			form.dataset.crpcrmSubmitting = '1';
			setButtonsDisabled(true);
			form.dataset.submitGuardTimer = String(window.setTimeout(function () {
				if (!document.body.contains(form) || form.dataset.crpcrmSubmitting !== '1') {
					return;
				}

				releaseSubmitGuard();
			}, 45000));
		});
	}

	function initFileUploads(scope, config) {
		scope.querySelectorAll('.crpcrm-file-upload').forEach(function (wrapper) {
			if (wrapper.dataset.initialized === '1') {
				return;
			}
			wrapper.dataset.initialized = '1';

			var name = wrapper.getAttribute('data-field-name');
			var list = wrapper.querySelector('.crpcrm-file-upload-list');
			var add = wrapper.querySelector('.crpcrm-file-upload-add');
			if (!name || !list || !add) {
				return;
			}

			list.querySelectorAll('.crpcrm-file-upload-row').forEach(function (row) {
				bindUploadRow(row, wrapper, config);
			});
			syncUploadedStore(wrapper);
			refreshUploadRequirements(wrapper);

			add.addEventListener('click', function () {
				if (getUploadRows(wrapper).length >= getWrapperMaxFiles(wrapper, config)) {
					return;
				}
				list.appendChild(createUploadRow(name, false, wrapper, config));
				refreshUploadRequirements(wrapper);
			});

			list.addEventListener('click', function (event) {
				if (!event.target.classList.contains('crpcrm-file-upload-remove')) {
					return;
				}

				event.preventDefault();
				var row = event.target.closest('.crpcrm-file-upload-row');
				if (!row) {
					return;
				}

				deletePendingUploadRow(row, list, name, wrapper, config);
			});
		});
	}

	function syncFieldCardType(card) {
		var typeSelect = card.querySelector('.crpcrm-form-builder-field-type');
		if (!typeSelect) {
			return;
		}
		var type = typeSelect.value;
		var options = card.querySelector('.crpcrm-field-type-options');
		var content = card.querySelector('.crpcrm-field-type-content');
		if (options) {
			options.classList.toggle('is-hidden', type !== 'select');
		}
		if (content) {
			content.classList.toggle('is-hidden', type !== 'display_html');
		}
	}

	function renumberFieldCards(container) {
		container.querySelectorAll('.crpcrm-form-builder-field-card').forEach(function (card, index) {
			var label = card.querySelector('.crpcrm-field-order-label');
			var orderInput = card.querySelector('.crpcrm-field-sort-order');
			if (label) {
				label.textContent = String(index + 1);
			}
			if (orderInput) {
				orderInput.value = index;
			}
		});
	}

	function initFormBuilder() {
		document.querySelectorAll('.crpcrm-form-builder-fields').forEach(function (builder) {
			var addButton = builder.querySelector('.crpcrm-add-field-button');
			var list = builder.querySelector('.crpcrm-form-builder-field-list');
			var template = document.getElementById('tmpl-crpcrm-form-builder-field');
			var count = parseInt(builder.getAttribute('data-field-count'), 10) || 0;

			if (!addButton || !list || !template) {
				return;
			}

			list.querySelectorAll('.crpcrm-form-builder-field-card').forEach(syncFieldCardType);
			renumberFieldCards(list);

			addButton.addEventListener('click', function () {
				var html = template.innerHTML
					.replace(/__INDEX__/g, String(count))
					.replace(/__ORDER__/g, String(count + 1));
				var frame = document.createElement('div');
				frame.innerHTML = html;
				var card = frame.firstElementChild;
				list.appendChild(card);
				syncFieldCardType(card);
				count += 1;
			});

			list.addEventListener('change', function (event) {
				var card = event.target.closest('.crpcrm-form-builder-field-card');
				if (!card) {
					return;
				}
				if (event.target.classList.contains('crpcrm-form-builder-field-type')) {
					syncFieldCardType(card);
				}
			});

			list.addEventListener('click', function (event) {
				if (!event.target.classList.contains('crpcrm-remove-field-button')) {
					return;
				}
				event.preventDefault();
				var card = event.target.closest('.crpcrm-form-builder-field-card');
				if (card && list.children.length > 1) {
					card.remove();
					renumberFieldCards(list);
				}
			});
		});
	}

	function initRequestDetailTabs() {
		document.querySelectorAll('[data-crpcrm-tabs="request-detail"]').forEach(function (container) {
			var buttons = container.querySelectorAll('.crpcrm-request-tab-button');
			var panels = container.querySelectorAll('.crpcrm-request-tab-panel');

			if (!buttons.length || !panels.length) {
				return;
			}

			function activateTab(target) {
				buttons.forEach(function (button) {
					var isActive = button.getAttribute('data-tab-target') === target;
					button.classList.toggle('is-active', isActive);
					button.setAttribute('aria-selected', isActive ? 'true' : 'false');
				});

				panels.forEach(function (panel) {
					var isActive = panel.getAttribute('data-tab-panel') === target;
					panel.classList.toggle('is-active', isActive);
				});
			}

			container.classList.add('is-ready');
			activateTab('request-main');

			buttons.forEach(function (button) {
				button.addEventListener('click', function () {
					var target = button.getAttribute('data-tab-target') || '';
					if (target) {
						activateTab(target);
					}
				});
			});
		});
	}


	document.addEventListener('DOMContentLoaded', function () {
		var config = getAdminConfig();
		initJalaliDatePickers();
		initRegistrationFieldSorting();
		initFormBuilder();
		document.querySelectorAll('.crpcrm-manual-request-form, .crpcrm-sales-action-form, .crpcrm-staff-form').forEach(bindSubmitGuard);
		document.querySelectorAll('.crpcrm-manual-request-form').forEach(function (form) {
			initProductSearch(form, config);
			initFileUploads(form, config);
			initManualRequestFlow(form, config);
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
		document.querySelectorAll('.crpcrm-staff-admin').forEach(function (section) {
			initFileUploads(section, config);
		});
		initRequestDetailTabs();
	});
}());

