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

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.crpcrm-otp-code-boxes').forEach(setupOtpBoxes);
		document.querySelectorAll('.crpcrm-resend-otp-form').forEach(setupResendTimer);
	});
}());
