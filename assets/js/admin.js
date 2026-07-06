(function () {
	'use strict';

	var state = {
		page: 1,
		totalPages: 1,
		filters: {}
	};

	function text(value) {
		return value === null || typeof value === 'undefined' ? '' : String(value);
	}

	function severityLabel(value) {
		return (oalAdmin.i18n.severity && oalAdmin.i18n.severity[value]) || '';
	}

	function eventLabel(eventType) {
		var labels = oalAdmin.i18n.events || {};

		if (labels[eventType]) {
			return labels[eventType];
		}

		return text(eventType).replace(/[._-]/g, ' ').replace(/\b\w/g, function (letter) {
			return letter.toUpperCase();
		});
	}

	function categoryIcon(eventType) {
		if (eventType.indexOf('user.') === 0) {
			return 'U';
		}
		if (eventType.indexOf('content.') === 0) {
			return 'C';
		}
		if (eventType.indexOf('media.') === 0) {
			return 'M';
		}
		if (eventType.indexOf('comment.') === 0) {
			return 'R';
		}
		if (eventType.indexOf('woocommerce.') === 0) {
			return 'W';
		}
		return 'S';
	}

	function prettifyKey(key) {
		return text(key).replace(/^_+/, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, function (letter) {
			return letter.toUpperCase();
		});
	}

	function formatDetails(item) {
		var message = text(item.message);

		if (item.event_type === 'system.option_update') {
			var match = message.match(/Option "([^"]+)"/);
			var option = match && match[1] ? match[1] : (item.meta && item.meta.option ? item.meta.option : '');

			return option ? 'Setting changed: ' + prettifyKey(option) : 'Setting changed';
		}

		return message;
	}

	function collectFilters() {
		var form = document.querySelector('[data-oal-filters]');
		var filters = {};

		if (!form) {
			return filters;
		}

		Array.prototype.forEach.call(form.elements, function (field) {
			if (!field.name || !field.value) {
				return;
			}

			filters[field.name] = field.value;
		});

		return filters;
	}

	function get(action, params) {
		var url = new URL(oalAdmin.ajaxUrl);
		url.searchParams.set('action', action);
		url.searchParams.set('nonce', oalAdmin.nonce);

		Object.keys(params || {}).forEach(function (key) {
			url.searchParams.set(key, params[key]);
		});

		return fetch(url.toString(), {
			credentials: 'same-origin',
			headers: {'Accept': 'application/json'}
		}).then(function (response) {
			return response.json();
		});
	}

	function post(action, params) {
		var data = new FormData();
		data.append('action', action);
		data.append('nonce', oalAdmin.nonce);

		Object.keys(params || {}).forEach(function (key) {
			data.append(key, params[key]);
		});

		return fetch(oalAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		}).then(function (response) {
			return response.json();
		});
	}

	function appendTextCell(row, value) {
		var cell = document.createElement('td');
		cell.textContent = text(value);
		row.appendChild(cell);
		return cell;
	}

	function renderRows(items) {
		var tbody = document.querySelector('[data-oal-table="logs"] tbody');

		if (!tbody) {
			return;
		}

		tbody.innerHTML = '';

		if (!items.length) {
			var empty = document.createElement('tr');
			var cell = document.createElement('td');
			cell.colSpan = 6;
			cell.textContent = oalAdmin.i18n.empty;
			empty.appendChild(cell);
			tbody.appendChild(empty);
			return;
		}

		items.forEach(function (item) {
			var row = document.createElement('tr');
			appendTextCell(row, item.display_time || item.created_at);

			var activity = appendTextCell(row, '');
			var icon = document.createElement('span');
			var title = document.createElement('strong');
			var badge = document.createElement('span');
			icon.className = 'oal-event-icon';
			icon.setAttribute('aria-hidden', 'true');
			icon.textContent = categoryIcon(item.event_type);
			title.textContent = eventLabel(item.event_type);
			badge.className = 'oal-severity oal-severity-' + item.severity;
			badge.textContent = severityLabel(item.severity);
			activity.className = 'oal-activity-cell';
			activity.appendChild(icon);
			activity.appendChild(title);
			activity.appendChild(badge);

			appendTextCell(row, item.username || (item.user_id ? '#' + item.user_id : 'System'));
			appendTextCell(row, item.ip_address);

			var details = appendTextCell(row, formatDetails(item));
			if (item.object_type && parseInt(item.object_id, 10) > 0) {
				var object = document.createElement('span');
				object.className = 'oal-object';
				object.textContent = item.object_type + ' #' + item.object_id;
				details.appendChild(document.createElement('br'));
				details.appendChild(object);
			}

			var action = appendTextCell(row, '');
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'button button-small button-link-delete';
			button.textContent = oalAdmin.i18n.delete;
			button.setAttribute('data-oal-delete-id', item.id);
			action.appendChild(button);

			tbody.appendChild(row);
		});
	}

	function updatePager() {
		var label = document.querySelector('[data-oal-page-label]');
		var prev = document.querySelector('[data-oal-page="prev"]');
		var next = document.querySelector('[data-oal-page="next"]');

		if (label) {
			label.textContent = state.page + ' / ' + state.totalPages;
		}

		if (prev) {
			prev.disabled = state.page <= 1;
		}

		if (next) {
			next.disabled = state.page >= state.totalPages;
		}
	}

	function loadLogs() {
		var table = document.querySelector('[data-oal-table="logs"]');

		if (!table) {
			return;
		}

		get('oal_logs', Object.assign({}, state.filters, {
			page: state.page,
			per_page: 20
		})).then(function (payload) {
			if (!payload.success) {
				throw new Error('Failed');
			}

			state.totalPages = payload.data.total_pages || 1;
			renderRows(payload.data.items || []);
			updatePager();
		}).catch(function () {
			var tbody = table.querySelector('tbody');
			tbody.innerHTML = '<tr><td colspan="6">' + oalAdmin.i18n.error + '</td></tr>';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('[data-oal-filters]');
		var reset = document.querySelector('[data-oal-reset]');
		var clear = document.querySelector('[data-oal-clear]');

		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				state.filters = collectFilters();
				state.page = 1;
				loadLogs();
			});
		}

		if (reset && form) {
			reset.addEventListener('click', function () {
				form.reset();
				state.filters = {};
				state.page = 1;
				loadLogs();
			});
		}

		if (clear) {
			clear.addEventListener('click', function () {
				if (!window.confirm(oalAdmin.i18n.confirmClear)) {
					return;
				}

				post('oal_clear_logs', {}).then(loadLogs);
			});
		}

		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-oal-delete-id]');

			if (!button || !window.confirm(oalAdmin.i18n.confirmDelete)) {
				return;
			}

			post('oal_delete_log', {id: button.getAttribute('data-oal-delete-id')}).then(loadLogs);
		});

		Array.prototype.forEach.call(document.querySelectorAll('[data-oal-page]'), function (button) {
			button.addEventListener('click', function () {
				if (button.getAttribute('data-oal-page') === 'prev') {
					state.page = Math.max(1, state.page - 1);
				} else {
					state.page = Math.min(state.totalPages, state.page + 1);
				}

				loadLogs();
			});
		});

		loadLogs();
	});
}());
