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

	const config = window.crpcrmLandings || {};
	const debug = !!config.debug;

	function log() {
		if (debug && window.console && console.debug) {
			console.debug.apply(console, arguments);
		}
	}

	function debounce(fn, delay) {
		let timer = null;
		return function () {
			const args = arguments;
			const context = this;
			clearTimeout(timer);
			timer = setTimeout(function () {
				fn.apply(context, args);
			}, delay);
		};
	}

	function setSelectedDestination(result, input, hidden, box) {
		if (!result) {
			hidden.value = '';
			box.innerHTML = '<span>' + (config.emptyLabel || '') + '</span>';
			return;
		}

		hidden.value = result.id;
		const title = document.createElement('strong');
		title.textContent = result.label || '';
		const meta = document.createElement('span');
		meta.textContent = result.url || '';

		box.innerHTML = '';
		box.appendChild(title);
		box.appendChild(meta);
	}

	async function searchTargets(term) {
		if (!window.fetch || !window.URLSearchParams) {
			return [];
		}

		const params = new URLSearchParams();
		params.set('action', 'crpcrm_search_landing_targets');
		params.set('nonce', config.nonce || '');
		params.set('term', term);

		const url = (config.ajaxUrl || '') + '?' + params.toString();
		const response = await fetch(url, { credentials: 'same-origin' });
		const payload = await response.json();
		if (!payload || !payload.success) {
			throw new Error((payload && payload.data && payload.data.message) ? payload.data.message : 'error');
		}
		return payload.data || [];
	}

	function renderResults(container, results, input, hidden, box) {
		container.innerHTML = '';
		if (!results.length) {
			const empty = document.createElement('div');
			empty.className = 'crpcrm-landing-search-empty';
			empty.textContent = config.emptyLabel || '';
			container.appendChild(empty);
			return;
		}

		const list = document.createElement('div');
		list.className = 'crpcrm-landing-search-list';
		results.forEach(function (result) {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'button button-small crpcrm-landing-search-item';
			button.dataset.destinationId = result.id;
			button.dataset.destinationLabel = result.label || '';
			button.dataset.destinationUrl = result.url || '';
			button.textContent = (result.label || '') + (result.type ? ' (' + result.type + ')' : '');
			button.addEventListener('click', function () {
				setSelectedDestination(result, input, hidden, box);
				container.innerHTML = '';
				input.value = '';
			});
			list.appendChild(button);
		});
		container.appendChild(list);
	}

	document.addEventListener('DOMContentLoaded', function () {
		const searchInput = document.querySelector('[data-destination-search]');
		const resultsBox = document.querySelector('[data-destination-results]');
		const hiddenInput = document.querySelector('[data-destination-id-input]');
		const selectedBox = document.querySelector('[data-selected-destination]');

		if (searchInput && resultsBox && hiddenInput && selectedBox) {
			const runSearch = debounce(async function () {
				const term = searchInput.value.trim();
				if (term.length < 2) {
					resultsBox.innerHTML = '';
					return;
				}

				resultsBox.innerHTML = '<div class="crpcrm-landing-search-loading">' + (config.searchingLabel || '') + '</div>';
				try {
					const results = await searchTargets(term);
					renderResults(resultsBox, results, searchInput, hiddenInput, selectedBox);
				} catch (error) {
					log('landing search failed', error);
					resultsBox.innerHTML = '<div class="crpcrm-landing-search-empty">' + (config.emptyLabel || '') + '</div>';
				}
			}, 250);

			searchInput.addEventListener('input', runSearch);
		}

		document.addEventListener('click', function (event) {
			const copyButton = event.target.closest('.crpcrm-copy-landing-link');
			if (!copyButton) {
				return;
			}

			const url = copyButton.getAttribute('data-copy-url') || '';
			if (!url) {
				return;
			}

			const restoreText = copyButton.textContent;
			const doneText = config.copiedLabel || restoreText;
			const setDone = function () {
				copyButton.textContent = doneText;
				setTimeout(function () {
					copyButton.textContent = restoreText;
				}, 1200);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(setDone).catch(function () {
					setDone();
				});
			} else {
				const temp = document.createElement('textarea');
				temp.value = url;
				document.body.appendChild(temp);
				temp.select();
				try {
					document.execCommand('copy');
				} catch (error) {
					log('copy failed', error);
				}
				document.body.removeChild(temp);
				setDone();
			}
		});
	});
})();
