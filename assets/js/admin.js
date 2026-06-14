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
		remove.setAttribute('aria-label', labels.productRemoveLabel || 'حذف محصول');
		remove.textContent = '×';
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
				results.innerHTML = '';
				if (!items.length) {
					var empty = document.createElement('div');
					empty.className = 'crpcrm-product-search-empty';
					empty.textContent = labels.productSearchEmpty || 'محصولی پیدا نشد.';
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
						results.innerHTML = '';
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
					results.innerHTML = '';
					return;
				}

				results.hidden = false;
				results.innerHTML = '<div class="crpcrm-product-search-empty">' + (labels.productSearchLoading || 'در حال جستجو...') + '</div>';

				if (abortController) {
					abortController.abort();
				}
				abortController = new AbortController();

				var params = new URLSearchParams({
					action: 'crpcrm_search_products',
					nonce: labels.productSearchNonce || '',
					term: term
				});

				fetch((labels.ajaxUrl || '') + '?' + params.toString(), {
					credentials: 'same-origin',
					signal: abortController.signal
				})
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

			document.addEventListener('click', function (event) {
				if (!wrapper.contains(event.target)) {
					results.hidden = true;
				}
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

	function setPreviewContent(preview, file) {
		preview.innerHTML = '';
		if (!file) {
			return;
		}

		if (String(file.type || '').indexOf('image/') === 0) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'crpcrm-file-thumb';
			var image = document.createElement('img');
			image.src = file.url || file.previewUrl || '';
			image.alt = file.name || '';
			button.appendChild(image);
			button.addEventListener('click', function () {
				var modal = ensureFilePreviewModal();
				modal.querySelector('img').src = image.src;
				modal.querySelector('.crpcrm-file-preview-name').textContent = file.name || '';
				modal.classList.add('is-open');
			});
			preview.appendChild(button);
		} else {
			var label = document.createElement('span');
			label.className = 'crpcrm-file-upload-name';
			label.textContent = file.name || '';
			preview.appendChild(label);
		}
	}

	function uploadSelectedFile(input, preview, wrapper, config) {
		if (!input.files || !input.files[0] || !config || !config.ajaxUrl || !config.fileUploadNonce) {
			return;
		}

		var row = input.closest('.crpcrm-file-upload-row');
		var file = input.files[0];
		var formData = new FormData();
		formData.append('action', 'crpcrm_upload_request_file');
		formData.append('nonce', config.fileUploadNonce);
		formData.append('file', file);

		preview.innerHTML = '<span class="crpcrm-file-upload-name">' + (config.fileUploadLoading || 'Uploading...') + '</span>';
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
					uploaded.previewUrl = URL.createObjectURL(file);
				}

				row.setAttribute('data-uploaded', JSON.stringify(uploaded));
				input.value = '';
				input.removeAttribute('name');
				input.disabled = true;
				input.removeAttribute('data-required');
				setPreviewContent(preview, uploaded);
				syncUploadedStore(wrapper);
			})
			.catch(function (error) {
				preview.innerHTML = '<span class="crpcrm-file-upload-name">' + ((error && error.message) || config.fileUploadError || 'Upload failed') + '</span>';
				row.removeAttribute('data-uploaded');
				syncUploadedStore(wrapper);
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
			input.addEventListener('change', function () {
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
		if (required) {
			input.setAttribute('data-required', '1');
		}
		row.appendChild(input);

		var preview = document.createElement('div');
		preview.className = 'crpcrm-file-upload-preview';
		row.appendChild(preview);

		input.addEventListener('change', function () {
			uploadSelectedFile(input, preview, wrapper, config);
		});

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'crpcrm-file-upload-remove';
		remove.textContent = '×';
		remove.addEventListener('click', function () {
			row.remove();
		});
		row.appendChild(remove);

		return row;
	}

	function ensureFilePreviewModal() {
		var modal = document.querySelector('.crpcrm-file-preview-modal');
		if (modal) {
			return modal;
		}

		modal = document.createElement('div');
		modal.className = 'crpcrm-file-preview-modal';
		modal.innerHTML = '<button type="button" class="crpcrm-file-preview-close">×</button><img alt=""><div class="crpcrm-file-preview-name"></div>';
		document.body.appendChild(modal);
		modal.addEventListener('click', function (event) {
			if (event.target === modal || event.target.classList.contains('crpcrm-file-preview-close')) {
				modal.classList.remove('is-open');
			}
		});
		return modal;
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

			add.addEventListener('click', function () {
				var required = !!list.querySelector('[data-required="1"]');
				list.appendChild(createUploadRow(name, required, wrapper, config));
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

				if (list.children.length > 1) {
					row.remove();
				} else {
					row.replaceWith(createUploadRow(name, !!list.querySelector('[data-required="1"]'), wrapper, config));
				}
				syncUploadedStore(wrapper);
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

	function cleanupRequestList() {
		var table = document.querySelector('.crpcrm-requests-table');
		if (!table) {
			return;
		}

		[7, 4, 2].forEach(function (index) {
			table.querySelectorAll('tr').forEach(function (row) {
				if (row.children[index]) {
					row.children[index].remove();
				}
			});
		});

		table.querySelectorAll('tbody tr').forEach(function (row) {
			var ownerCell = row.children[5];
			if (ownerCell) {
				ownerCell.textContent = ownerCell.textContent.replace(/^در حال پیگیری توسط:\s*/u, '').trim();
			}
		});
	}

	function showNotificationsToast(config) {
		if (!config || !config.notificationsToast || !config.unreadNotifications || !config.notificationsPageUrl) {
			return;
		}

		var toast = document.createElement('a');
		toast.className = 'crpcrm-notifications-toast';
		toast.href = config.notificationsPageUrl;
		toast.innerHTML = '<strong>اعلان جدید دارید</strong><span>' + config.unreadNotifications + ' مورد خوانده‌نشده</span>';
		document.body.appendChild(toast);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var config = getAdminConfig();
		initJalaliDatePickers();
		initRegistrationFieldSorting();
		initFormBuilder();
		cleanupRequestList();
		showNotificationsToast(config);
		document.querySelectorAll('.crpcrm-manual-request-form').forEach(function (form) {
			updateManualCustomerFields(form);
			initProductSearch(form, config);
			initFileUploads(form, config);
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
