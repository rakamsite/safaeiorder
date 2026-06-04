(function () {
	'use strict';

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
