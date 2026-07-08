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
		return (tracevaultAdmin.i18n.severity && tracevaultAdmin.i18n.severity[value]) || '';
	}

	function eventLabel(eventType) {
		var labels = tracevaultAdmin.i18n.events || {};

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
		var form = document.querySelector('[data-tracevault-filters]');
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
		var url = new URL(tracevaultAdmin.ajaxUrl);
		url.searchParams.set('action', action);
		url.searchParams.set('nonce', tracevaultAdmin.nonce);

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
		data.append('nonce', tracevaultAdmin.nonce);

		Object.keys(params || {}).forEach(function (key) {
			data.append(key, params[key]);
		});

		return fetch(tracevaultAdmin.ajaxUrl, {
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
		var tbody = document.querySelector('[data-tracevault-table="logs"] tbody');

		if (!tbody) {
			return;
		}

		tbody.innerHTML = '';

		if (!items.length) {
			var empty = document.createElement('tr');
			var cell = document.createElement('td');
			cell.colSpan = 6;
			cell.textContent = tracevaultAdmin.i18n.empty;
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
			icon.className = 'tracevault-event-icon';
			icon.setAttribute('aria-hidden', 'true');
			icon.textContent = categoryIcon(item.event_type);
			title.textContent = eventLabel(item.event_type);
			badge.className = 'tracevault-severity tracevault-severity-' + item.severity;
			badge.textContent = severityLabel(item.severity);
			activity.className = 'tracevault-activity-cell';
			activity.appendChild(icon);
			activity.appendChild(title);
			activity.appendChild(badge);

			appendTextCell(row, item.username || (item.user_id ? '#' + item.user_id : 'System'));
			appendTextCell(row, item.ip_address);

			var details = appendTextCell(row, formatDetails(item));
			if (item.object_type && parseInt(item.object_id, 10) > 0) {
				var object = document.createElement('span');
				object.className = 'tracevault-object';
				object.textContent = item.object_type + ' #' + item.object_id;
				details.appendChild(document.createElement('br'));
				details.appendChild(object);
			}

			var action = appendTextCell(row, '');
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'button button-small button-link-delete';
			button.textContent = tracevaultAdmin.i18n.delete;
			button.setAttribute('data-tracevault-delete-id', item.id);
			action.appendChild(button);

			tbody.appendChild(row);
		});
	}

	function updatePager() {
		var label = document.querySelector('[data-tracevault-page-label]');
		var prev = document.querySelector('[data-tracevault-page="prev"]');
		var next = document.querySelector('[data-tracevault-page="next"]');

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
		var table = document.querySelector('[data-tracevault-table="logs"]');

		if (!table) {
			return;
		}

		get('tracevault_logs', Object.assign({}, state.filters, {
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
			tbody.innerHTML = '<tr><td colspan="6">' + tracevaultAdmin.i18n.error + '</td></tr>';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('[data-tracevault-filters]');
		var reset = document.querySelector('[data-tracevault-reset]');
		var clear = document.querySelector('[data-tracevault-clear]');

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
				if (!window.confirm(tracevaultAdmin.i18n.confirmClear)) {
					return;
				}

				post('tracevault_clear_logs', {}).then(loadLogs);
			});
		}

		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-tracevault-delete-id]');

			if (!button || !window.confirm(tracevaultAdmin.i18n.confirmDelete)) {
				return;
			}

			post('tracevault_delete_log', {id: button.getAttribute('data-tracevault-delete-id')}).then(loadLogs);
		});

		Array.prototype.forEach.call(document.querySelectorAll('[data-tracevault-page]'), function (button) {
			button.addEventListener('click', function () {
				if (button.getAttribute('data-tracevault-page') === 'prev') {
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
