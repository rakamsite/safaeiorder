(function () {
	'use strict';

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

	var modal = null;
	var modalImage = null;
	var modalFrame = null;
	var modalTitle = null;
	var modalDownload = null;
	var body = document.body || document.documentElement;

	function ensureModal() {
		if (modal) {
			return modal;
		}

		modal = document.createElement('div');
		modal.className = 'crpcrm-file-modal';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML = ''
			+ '<div class="crpcrm-file-modal-backdrop" data-crpcrm-file-modal-close="1"></div>'
			+ '<div class="crpcrm-file-modal-content" role="dialog" aria-modal="true" aria-label="پیش‌نمایش فایل">'
			+   '<button type="button" class="crpcrm-file-modal-close" aria-label="بستن">×</button>'
			+   '<div class="crpcrm-file-modal-body">'
			+     '<img class="crpcrm-file-modal-image" alt="">'
			+     '<iframe class="crpcrm-file-modal-frame" title="پیش‌نمایش PDF" loading="lazy"></iframe>'
			+   '</div>'
			+   '<div class="crpcrm-file-modal-footer">'
			+     '<strong class="crpcrm-file-modal-title"></strong>'
			+     '<a class="button button-primary crpcrm-file-modal-download" href="#" download>' + 'دانلود فایل' + '</a>'
			+   '</div>'
			+ '</div>';

		body.appendChild(modal);
		modalImage = modal.querySelector('.crpcrm-file-modal-image');
		modalFrame = modal.querySelector('.crpcrm-file-modal-frame');
		modalTitle = modal.querySelector('.crpcrm-file-modal-title');
		modalDownload = modal.querySelector('.crpcrm-file-modal-download');

		modal.addEventListener('click', function (event) {
			if (event.target.classList.contains('crpcrm-file-modal-backdrop') || event.target.classList.contains('crpcrm-file-modal-close')) {
				event.preventDefault();
				closeModal();
			}
		});

		return modal;
	}

	function openModal(trigger) {
		var fullUrl = trigger.getAttribute('data-full-url') || '';
		var downloadUrl = trigger.getAttribute('data-download-url') || fullUrl;
		var filename = trigger.getAttribute('data-filename') || '';
		var mimeType = String(trigger.getAttribute('data-mime-type') || '').toLowerCase();
		var alt = filename || '';
		var node = ensureModal();

		if (!fullUrl && !downloadUrl) {
			return;
		}

		if (mimeType.indexOf('pdf') !== -1) {
			modal.classList.add('is-pdf');
			modalFrame.src = fullUrl || downloadUrl;
			modalImage.src = '';
			modalImage.alt = '';
		} else {
			modal.classList.remove('is-pdf');
			modalImage.src = fullUrl || downloadUrl;
			modalImage.alt = alt;
			modalFrame.src = '';
		}
		modalTitle.textContent = filename || '';
		modalDownload.href = downloadUrl || fullUrl;
		modalDownload.textContent = 'دانلود فایل';
		if (filename) {
			modalDownload.setAttribute('download', filename);
		} else {
			modalDownload.removeAttribute('download');
		}

		node.classList.add('is-open');
		node.setAttribute('aria-hidden', 'false');
		body.classList.add('crpcrm-file-modal-open');
		node.querySelector('.crpcrm-file-modal-close').focus();
	}

	function closeModal() {
		if (!modal) {
			return;
		}

		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		if (modalImage) {
			modalImage.src = '';
			modalImage.alt = '';
		}
		if (modalFrame) {
			modalFrame.src = '';
		}
		if (modalTitle) {
			modalTitle.textContent = '';
		}
		if (modalDownload) {
			modalDownload.href = '#';
			modalDownload.removeAttribute('download');
		}
		body.classList.remove('crpcrm-file-modal-open');
		modal.classList.remove('is-pdf');
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest ? event.target.closest('[data-crpcrm-file-preview="1"]') : null;
		if (!trigger) {
			return;
		}

		event.preventDefault();
		openModal(trigger);
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			closeModal();
		}
	});
})();
