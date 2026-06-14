(function () {
	'use strict';

	function toEnglishDigits(value) {
		var persian = '۰۱۲۳۴۵۶۷۸۹';
		var arabic = '٠١٢٣٤٥٦٧٨٩';

		return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
			var persianIndex = persian.indexOf(digit);
			if (persianIndex > -1) {
				return String(persianIndex);
			}

			return String(arabic.indexOf(digit));
		});
	}

	function onlyDigits(value) {
		return toEnglishDigits(value).replace(/\D+/g, '').slice(0, 3);
	}

	function setupOtpBoxes(group) {
		var boxes = Array.prototype.slice.call(group.querySelectorAll('.crpcrm-otp-code-box'));
		var form = group.closest('form');
		var hidden = form ? form.querySelector('#crpcrm_otp_code') : document.getElementById('crpcrm_otp_code');
		var otpLength = parseInt(group.getAttribute('data-otp-length'), 10) || boxes.length;
		var isSubmitting = false;

		function syncHidden() {
			if (!hidden) {
				return '';
			}

			hidden.value = boxes.map(function (box) {
				return onlyDigits(box.value).slice(0, 1);
			}).join('');

			return hidden.value;
		}

		function submitWhenComplete() {
			var code = syncHidden();
			if (!form || isSubmitting || code.length < otpLength) {
				return;
			}

			isSubmitting = true;
			if ('function' === typeof form.requestSubmit) {
				form.requestSubmit();
				return;
			}

			form.submit();
		}

		function fillBoxes(value) {
			var digits = onlyDigits(value);
			boxes.forEach(function (box, index) {
				box.value = digits.charAt(index) || '';
			});
			syncHidden();

			if (digits.length) {
				boxes[Math.min(digits.length, boxes.length) - 1].focus();
			}

			submitWhenComplete();
		}

		boxes.forEach(function (box, index) {
			box.addEventListener('input', function () {
				var digits = onlyDigits(box.value);
				if (digits.length > 1) {
					fillBoxes(digits);
					return;
				}

				box.value = digits;
				syncHidden();

				if (digits && boxes[index + 1]) {
					boxes[index + 1].focus();
				}

				submitWhenComplete();
			});

			box.addEventListener('keydown', function (event) {
				if ('Backspace' === event.key && !box.value && boxes[index - 1]) {
					boxes[index - 1].focus();
				}
			});

			box.addEventListener('paste', function (event) {
				var clipboard = event.clipboardData || window.clipboardData;
				if (!clipboard) {
					return;
				}

				event.preventDefault();
				fillBoxes(clipboard.getData('text'));
			});
		});

		if (form) {
			form.addEventListener('submit', syncHidden);
		}
	}

	function setupResendTimer(form) {
		var seconds = parseInt(form.getAttribute('data-resend-seconds'), 10);
		var countdown = form.querySelector('.crpcrm-resend-countdown');
		var button = form.querySelector('.crpcrm-resend-button');

		if (!countdown || !button || isNaN(seconds) || seconds <= 0) {
			return;
		}

		function render() {
			if (seconds <= 0) {
				countdown.hidden = true;
				button.hidden = false;
				return;
			}

			countdown.textContent = 'تا ارسال مجدد کد تأیید ' + seconds + ' ثانیه';
			seconds -= 1;
			window.setTimeout(render, 1000);
		}

		render();
	}

	function setupPhoneEntry(control) {
		var suffix = control.querySelector('.crpcrm-phone-suffix');
		var hidden = control.querySelector('#crpcrm_phone');
		var digits = Array.prototype.slice.call(control.querySelectorAll('.crpcrm-phone-digit'));
		var form = control.closest('form');

		if (!suffix || !hidden || !form) {
			return;
		}

		function renderDigits() {
			digits.forEach(function (digit, index) {
				digit.textContent = suffix.value.charAt(index) || '';
				digit.classList.toggle('is-filled', index < suffix.value.length);
				digit.classList.toggle('is-active', index === suffix.value.length && suffix.value.length < digits.length);
			});
		}

		function syncPhone() {
			suffix.value = toEnglishDigits(suffix.value).replace(/\D+/g, '').slice(0, 9);
			hidden.value = '09' + suffix.value;
			renderDigits();
		}

		suffix.addEventListener('input', syncPhone);
		suffix.addEventListener('focus', function () {
			control.classList.add('is-focused');
			renderDigits();
		});
		suffix.addEventListener('blur', function () {
			control.classList.remove('is-focused');
			digits.forEach(function (digit) {
				digit.classList.remove('is-active');
			});
		});
		control.addEventListener('click', function () {
			suffix.focus();
		});
		form.addEventListener('submit', syncPhone);
		syncPhone();
	}


	function setupInlineRequestForms() {
		var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-crpcrm-open-form]'));
		var forms = Array.prototype.slice.call(document.querySelectorAll('[data-crpcrm-request-form]'));

		if (!triggers.length || !forms.length) {
			return;
		}

		function getForm(page) {
			return forms.filter(function (form) {
				return form.getAttribute('data-crpcrm-request-form') === page;
			})[0] || null;
		}

		function openForm(page, shouldFocus) {
			var target = getForm(page);

			if (!target) {
				return false;
			}

			forms.forEach(function (form) {
				var isTarget = form === target;
				form.hidden = !isTarget;
				form.classList.toggle('is-open', isTarget);
			});

			triggers.forEach(function (trigger) {
				var isActive = trigger.getAttribute('data-crpcrm-open-form') === page;
				trigger.classList.toggle('is-active', isActive);
				trigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
			});

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', '#crpcrm-request-form-' + page);
			}

			if (shouldFocus) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				window.setTimeout(function () {
					var firstField = target.querySelector('input:not([type="hidden"]), select, textarea, button, a');
					if (firstField) {
						firstField.focus();
					}
				}, 250);
			}

			return true;
		}

		triggers.forEach(function (trigger) {
			var page = trigger.getAttribute('data-crpcrm-open-form');
			var target = getForm(page);

			if (!target) {
				return;
			}

			trigger.setAttribute('aria-controls', target.id);
			trigger.setAttribute('aria-expanded', 'false');
			trigger.addEventListener('click', function (event) {
				if (openForm(page, true)) {
					event.preventDefault();
				}
			});
		});

		if (window.location.hash && 0 === window.location.hash.indexOf('#crpcrm-request-form-')) {
			openForm(window.location.hash.replace('#crpcrm-request-form-', ''), false);
		}
	}

	function getPublicConfig() {
		return window.crpcrmPublic || {};
	}

	function syncProductHiddenValue(container, hidden) {
		var ids = Array.prototype.map.call(container.querySelectorAll('.crpcrm-product-chip'), function (chip) {
			return chip.getAttribute('data-product-id');
		});
		hidden.value = ids.join(',');
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

	function createUploadRow(name, required) {
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

	function initFileUploads(scope) {
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

			add.addEventListener('click', function () {
				var required = !!list.querySelector('[data-required="1"]');
				list.appendChild(createUploadRow(name, required));
			});

			list.addEventListener('click', function (event) {
				if (event.target.classList.contains('crpcrm-file-upload-remove') && list.children.length > 1) {
					event.preventDefault();
					event.target.closest('.crpcrm-file-upload-row').remove();
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var config = getPublicConfig();
		document.querySelectorAll('.crpcrm-phone-entry').forEach(setupPhoneEntry);
		document.querySelectorAll('.crpcrm-otp-code-boxes').forEach(setupOtpBoxes);
		document.querySelectorAll('.crpcrm-resend-otp-form').forEach(setupResendTimer);
		setupInlineRequestForms();
		document.querySelectorAll('.crpcrm-request-form').forEach(function (form) {
			initProductSearch(form, config);
			initFileUploads(form);
		});
	});
}());
