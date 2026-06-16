(function () {
	'use strict';

	var dashboard = window.crpcrmReportsData || {};
	var chartInstances = {};
	var resizeTimer = null;

	function getChartsRoot() {
		return document.querySelector('.crpcrm-reports-admin');
	}

	function isArray(value) {
		return Array.isArray(value);
	}

	function destroyChart(name) {
		if (chartInstances[name] && typeof chartInstances[name].destroy === 'function') {
			chartInstances[name].destroy();
		}
		delete chartInstances[name];
	}

	function clearLegend(card) {
		var legend = card.querySelector('.crpcrm-chart-legend');
		if (legend) {
			legend.parentNode.removeChild(legend);
		}
	}

	function buildLegend(card, chartData) {
		clearLegend(card);

		var datasets = isArray(chartData.datasets) ? chartData.datasets : [];
		if (datasets.length <= 1) {
			return;
		}

		var legend = document.createElement('div');
		legend.className = 'crpcrm-chart-legend';

		datasets.forEach(function (dataset) {
			var item = document.createElement('span');
			item.className = 'crpcrm-chart-legend-item';
			var swatch = document.createElement('i');
			swatch.className = 'crpcrm-chart-legend-swatch';
			swatch.style.background = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[0] : (dataset.backgroundColor || dataset.borderColor || '#2d5bff');
			item.appendChild(swatch);
			item.appendChild(document.createTextNode(dataset.label || ''));
			legend.appendChild(item);
		});

		card.appendChild(legend);
	}

	function hasChartData(chartData) {
		if (!chartData || !isArray(chartData.datasets)) {
			return false;
		}

		return chartData.datasets.some(function (dataset) {
			return isArray(dataset.data) && dataset.data.some(function (value) {
				return Number(value) > 0;
			});
		});
	}

	function createOptions(type) {
		var base = {
			responsive: true,
			maintainAspectRatio: false,
			rtl: true,
			locale: 'fa-IR'
		};

		if ('doughnut' === type) {
			base.cutout = '62%';
		}

		return base;
	}

	function renderChart(name, canvas, chartData) {
		if (!canvas || !window.Chart || !chartData || !hasChartData(chartData)) {
			return;
		}

		destroyChart(name);
		var ctx = canvas.getContext('2d');
		chartInstances[name] = new window.Chart(
			ctx,
			{
				type: chartData.type || 'bar',
				data: {
					labels: chartData.labels || [],
					datasets: chartData.datasets || []
				},
				options: createOptions(chartData.type || 'bar')
			}
		);

		var card = canvas.closest('.crpcrm-chart-card');
		if (card) {
			buildLegend(card, chartData);
		}
	}

	function renderAll() {
		var chartMap = (dashboard && dashboard.charts) || {};
		if (!chartMap) {
			return;
		}

		var canvasList = document.querySelectorAll('[data-chart]');
		canvasList.forEach(function (canvas) {
			var name = canvas.getAttribute('data-chart');
			renderChart(name, canvas, chartMap[name]);
		});
	}

	function debounceResize() {
		if (resizeTimer) {
			window.clearTimeout(resizeTimer);
		}
		resizeTimer = window.setTimeout(function () {
			renderAll();
		}, 160);
	}

	function init() {
		if (!getChartsRoot()) {
			return;
		}
		renderAll();
		window.addEventListener('resize', debounceResize);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
