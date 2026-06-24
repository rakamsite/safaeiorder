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

	var config = window.crpcrmNotifications || {};
	var enabled = !!window.Promise && !!config.enabled && !!config.ajax_url && !!config.nonce && !!config.notifications_page_url;
	var pollTimer = null;
	var requestInFlight = false;
	var isPollingActive = false;
	var queue = [];
	var activeToasts = [];
	var shownIds = loadShownIds();
	var toastContainer = null;
	var audioContext = null;

	function loadShownIds() {
		try {
			var raw = window.sessionStorage.getItem( 'crpcrmShownNotifications' );
			if (!raw) {
				return {};
			}
			var ids = JSON.parse(raw);
			var map = {};
			(ids || []).forEach(function (id) {
				map[String(id)] = true;
			});
			return map;
		} catch (error) {
			return {};
		}
	}

	function persistShownIds() {
		try {
			window.sessionStorage.setItem( 'crpcrmShownNotifications', JSON.stringify(Object.keys(shownIds)) );
		} catch (error) {
			// Ignore session storage failures.
		}
	}

	function addShownId(id) {
		if (!id && 0 !== id) {
			return;
		}
		shownIds[String(id)] = true;
		persistShownIds();
	}

	function hasShownId(id) {
		return !!shownIds[String(id)];
	}

	function debugLog() {
		if (!config.debug || !window.console || !console.debug) {
			return;
		}
		try {
			console.debug.apply(console, arguments);
		} catch (error) {
			console.debug(arguments[0]);
		}
	}

	function ensureContainer() {
		if (toastContainer) {
			return toastContainer;
		}

		toastContainer = document.createElement('div');
		toastContainer.className = 'crpcrm-toast-container';
		toastContainer.setAttribute('aria-live', 'polite');
		toastContainer.setAttribute('aria-label', 'اعلان‌های جدید');
		document.body.appendChild(toastContainer);
		return toastContainer;
	}

	function getNotificationsPageUrl() {
		return config.notifications_page_url || '';
	}

	function removeToast(toast) {
		if (!toast || !toast.parentNode) {
			return;
		}

		var activeIndex = activeToasts.indexOf(toast);
		if (activeIndex >= 0) {
			activeToasts.splice(activeIndex, 1);
		}

		toast.classList.add('is-hiding');
		window.setTimeout(function () {
			if (toast.parentNode) {
				toast.parentNode.removeChild(toast);
			}
			flushQueue();
		}, 180);
	}

	function navigateToUrl(url) {
		window.location.href = url || getNotificationsPageUrl();
	}

	function postAjax(action, params) {
		if (!window.fetch || !window.URLSearchParams) {
			return Promise.reject(new Error('unsupported'));
		}

		var formData = new URLSearchParams();
		formData.append('action', action);
		formData.append('nonce', config.nonce);

		Object.keys(params || {}).forEach(function (key) {
			var value = params[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) {
					formData.append(key + '[]', item);
				});
				return;
			}

			if (null === value || 'undefined' === typeof value) {
				return;
			}

			formData.append(key, value);
		});

		return fetch(config.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: formData.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function markNotificationsSeen(ids) {
		if (!enabled || !ids || !ids.length) {
			return Promise.resolve();
		}

		return postAjax('crpcrm_mark_notification_seen', {
			notification_ids: ids
		}).catch(function (error) {
			debugLog('crpcrm notification seen failed', error);
		});
	}

	function markNotificationRead(notification) {
		if (!enabled || !notification || !notification.id) {
			return Promise.resolve(notification && notification.target_url ? notification.target_url : getNotificationsPageUrl());
		}

		return postAjax('crpcrm_mark_notification_read', {
			notification_id: notification.id
		}).then(function (payload) {
			if (payload && payload.success && payload.data && payload.data.target_url) {
				return payload.data.target_url;
			}

			return notification.target_url || getNotificationsPageUrl();
		}).catch(function (error) {
			debugLog('crpcrm notification read failed', error);
			return notification.target_url || getNotificationsPageUrl();
		});
	}

	function createToast(notification) {
		var toast = document.createElement('div');
		var isNavigating = false;
		toast.className = 'crpcrm-toast';
		toast.setAttribute('role', 'button');
		toast.setAttribute('tabindex', '0');
		toast.setAttribute('data-notification-id', String(notification.id || ''));

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'crpcrm-toast-close';
		close.setAttribute('aria-label', 'بستن اعلان');
		close.innerHTML = '&times;';
		close.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			removeToast(toast);
		});

		var title = document.createElement('div');
		title.className = 'crpcrm-toast-title';
		title.textContent = notification.title || 'اعلان جدید';

		var message = document.createElement('div');
		message.className = 'crpcrm-toast-message';
		message.textContent = notification.message || '';

		toast.appendChild(close);
		toast.appendChild(title);
		if (notification.message) {
			toast.appendChild(message);
		}

		toast.addEventListener('click', function (event) {
			if (event.target && event.target.closest && event.target.closest('.crpcrm-toast-close')) {
				return;
			}
			if (isNavigating) {
				return;
			}
			isNavigating = true;
			markNotificationRead(notification).then(function (targetUrl) {
				navigateToUrl(targetUrl || notification.target_url || getNotificationsPageUrl());
			});
		});

		toast.addEventListener('keydown', function (event) {
			if ('Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key) {
				event.preventDefault();
				toast.click();
			}
		});

		window.setTimeout(function () {
			removeToast(toast);
		}, 9000);

		return toast;
	}

	function flushQueue() {
		if (!enabled) {
			return;
		}

		ensureContainer();
		while (activeToasts.length < maxToasts() && queue.length) {
			var notification = queue.shift();
			if (!notification) {
				continue;
			}

			if (hasShownId(notification.id)) {
				continue;
			}

			addShownId(notification.id);
			var toast = createToast(notification);
			activeToasts.push(toast);
			toastContainer.appendChild(toast);
			markNotificationsSeen([notification.id]);
			window.setTimeout((function (toastNode) {
				return function () {
					toastNode.classList.add('is-visible');
				};
			}(toast)), 16);
		}
	}

	function maxToasts() {
		return Math.max(1, parseInt(config.max_toasts, 10) || 3);
	}

	function queueNotifications(notifications) {
		var added = 0;
		(notifications || []).forEach(function (notification) {
			if (!notification || !notification.id || hasShownId(notification.id)) {
				return;
			}

			queue.push(notification);
			added += 1;
		});

		if (added > 0) {
			playNotificationSound();
		}

		flushQueue();
	}

	function playNotificationSound() {
		var AudioCtor = window.AudioContext || window.webkitAudioContext;
		if (!AudioCtor) {
			return;
		}

		try {
			if (!audioContext) {
				audioContext = new AudioCtor();
			}

			var context = audioContext;
			if (context.state === 'suspended' && typeof context.resume === 'function') {
				context.resume().catch(function () {
					// Ignore autoplay policy blocks.
				});
			}

			var oscillator = context.createOscillator();
			var gain = context.createGain();
			oscillator.type = 'sine';
			oscillator.frequency.value = 880;
			gain.gain.setValueAtTime(0.0001, context.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.06, context.currentTime + 0.02);
			gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.18);
			oscillator.connect(gain);
			gain.connect(context.destination);
			oscillator.start();
			oscillator.stop(context.currentTime + 0.2);
		} catch (error) {
			debugLog('crpcrm notification sound skipped', error);
		}
	}

	function parseResponse(payload) {
		if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.notifications)) {
			return [];
		}

		return payload.data.notifications;
	}

	function pollNotifications() {
		if (!enabled || document.hidden || requestInFlight) {
			return;
		}

		requestInFlight = true;
		postAjax('crpcrm_get_new_notifications', {})
			.then(function (response) {
				queueNotifications(parseResponse(response));
			})
			.catch(function (error) {
				debugLog('crpcrm notification poll failed', error);
			})
			.finally(function () {
				requestInFlight = false;
				scheduleNextPoll();
			});
	}

	function scheduleNextPoll() {
		if (!isPollingActive || document.hidden) {
			return;
		}

		clearTimeout(pollTimer);
		pollTimer = window.setTimeout(pollNotifications, Math.max(1000, parseInt(config.poll_interval, 10) || 30000));
	}

	function startPolling() {
		if (!enabled || isPollingActive || document.hidden) {
			return;
		}

		isPollingActive = true;
		pollNotifications();
	}

	function stopPolling() {
		isPollingActive = false;
		clearTimeout(pollTimer);
		pollTimer = null;
	}

	function handleVisibilityChange() {
		if (document.hidden) {
			stopPolling();
			return;
		}

		startPolling();
	}

	function init() {
		if (!enabled || !document.body) {
			return;
		}

		ensureContainer();
		document.addEventListener('visibilitychange', handleVisibilityChange);
		window.addEventListener('pagehide', stopPolling);
		startPolling();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
