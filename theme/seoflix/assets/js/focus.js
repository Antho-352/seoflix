(function () {
	'use strict';
	const menu = document.querySelector('.sx-dashboard-focus .sx-focus-menu');
	if (!menu) return;

	const select = menu.querySelector('select[name="seoflix_focus_path"]');
	const submit = menu.querySelector('.sx-focus-menu__submit');
	if (select && submit) {
		select.addEventListener('change', function () {
			submit.disabled = !select.value;
		});
	}

	document.addEventListener('click', function (event) {
		if (menu.open && !menu.contains(event.target)) menu.removeAttribute('open');
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && menu.open) {
			menu.removeAttribute('open');
			const trigger = menu.querySelector('summary');
			if (trigger) trigger.focus();
		}
	});
})();
