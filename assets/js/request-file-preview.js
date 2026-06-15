(function () {
	'use strict';

	var modal = null;
	var modalImage = null;
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
			+   '</div>'
			+   '<div class="crpcrm-file-modal-footer">'
			+     '<strong class="crpcrm-file-modal-title"></strong>'
			+     '<a class="button button-primary crpcrm-file-modal-download" href="#" download>' + 'دانلود فایل' + '</a>'
			+   '</div>'
			+ '</div>';

		body.appendChild(modal);
		modalImage = modal.querySelector('.crpcrm-file-modal-image');
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
		var alt = filename || '';
		var node = ensureModal();

		if (!fullUrl && !downloadUrl) {
			return;
		}

		modalImage.src = fullUrl || downloadUrl;
		modalImage.alt = alt;
		modalTitle.textContent = filename || '';
		modalDownload.href = downloadUrl || fullUrl;
		if (filename) {
			modalDownload.setAttribute('download', filename);
		} else {
			modalDownload.removeAttribute('download');
		}

		node.classList.add('is-open');
		node.setAttribute('aria-hidden', 'false');
		body.classList.add('crpcrm-file-modal-open');
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
		if (modalTitle) {
			modalTitle.textContent = '';
		}
		if (modalDownload) {
			modalDownload.href = '#';
			modalDownload.removeAttribute('download');
		}
		body.classList.remove('crpcrm-file-modal-open');
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
