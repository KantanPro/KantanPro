(function () {
	'use strict';

	var config = typeof ktpServiceContractFields !== 'undefined' ? ktpServiceContractFields : {};
	var noneValue = config.none_value || 'none';

	function toggleServiceContractFields(cycleSelect) {
		var cycle = cycleSelect || document.getElementById('contract_billing_cycle');
		var recurringFields = document.getElementById('ktpwp-service-recurring-fields');

		if (!cycle || !recurringFields) {
			return;
		}

		var show = (cycle.value || noneValue) !== noneValue;
		recurringFields.classList.toggle('ktpwp-service-recurring-fields--hidden', !show);
		recurringFields.style.removeProperty('display');
		recurringFields.setAttribute('aria-hidden', show ? 'false' : 'true');
	}

	function initServiceContractFields() {
		toggleServiceContractFields();
	}

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target || target.id !== 'contract_billing_cycle') {
			return;
		}
		toggleServiceContractFields(target);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initServiceContractFields);
	} else {
		initServiceContractFields();
	}

	window.ktpwpInitServiceContractFields = initServiceContractFields;
	window.ktpwpToggleServiceContractFields = toggleServiceContractFields;
})();
