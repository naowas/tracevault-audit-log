(function () {
	'use strict';

	var state = {
		page: 1,
		totalPages: 1,
		logs: [],
		activeTrigger: null,
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

	function hasObjectValues(value) {
		return value && typeof value === 'object' && Object.keys(value).length > 0;
	}

	function formatMetaValue(value) {
		if (value === null || typeof value === 'undefined') {
			return '-';
		}

		if (typeof value === 'boolean') {
			return value ? 'True' : 'False';
		}

		if (typeof value === 'object') {
			return JSON.stringify(value, null, 2);
		}

		return text(value);
	}

	function appendDetail(container, label, value) {
		if (value === null || typeof value === 'undefined' || value === '') {
			return;
		}

		var item = document.createElement('div');
		var term = document.createElement('span');
		var description = document.createElement('strong');

		item.className = 'tracevault-detail-item';
		term.textContent = label;
		description.textContent = text(value);
		item.appendChild(term);
		item.appendChild(description);
		container.appendChild(item);
	}

	function formatDetails(item) {
		var message = text(item.message);

		if (item.event_type === 'system.setting_update' || item.event_type === 'system.option_update' || item.event_type === 'woocommerce.setting_update') {
			var match = message.match(/Option "([^"]+)"/);
			var wooMatch = message.match(/WooCommerce setting "([^"]+)"/);
			var option = match && match[1] ? match[1] : (wooMatch && wooMatch[1] ? wooMatch[1] : (item.meta && item.meta.option ? item.meta.option : ''));

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

	function getModal() {
		var modal = document.querySelector('[data-tracevault-modal]');

		if (modal) {
			return modal;
		}

		modal = document.createElement('div');
		modal.className = 'tracevault-modal';
		modal.setAttribute('data-tracevault-modal', '');
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML = '<div class="tracevault-modal-backdrop" data-tracevault-modal-close></div>' +
			'<section class="tracevault-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tracevault-modal-title">' +
			'<button type="button" class="tracevault-modal-close" data-tracevault-modal-close>&times;</button>' +
			'<div class="tracevault-modal-header">' +
			'<span class="tracevault-modal-kicker"></span>' +
			'<h2 id="tracevault-modal-title"></h2>' +
			'<p></p>' +
			'</div>' +
			'<div class="tracevault-modal-body">' +
			'<div class="tracevault-detail-grid" data-tracevault-modal-overview></div>' +
			'<div class="tracevault-meta-section">' +
			'<h3></h3>' +
			'<div class="tracevault-meta-grid" data-tracevault-modal-meta></div>' +
			'</div>' +
			'</div>' +
			'</section>';

		document.body.appendChild(modal);
		modal.querySelector('.tracevault-modal-close').setAttribute('aria-label', text(tracevaultAdmin.i18n.close));
		return modal;
	}

	function closeModal() {
		var modal = document.querySelector('[data-tracevault-modal]');

		if (!modal) {
			return;
		}

		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('tracevault-modal-open');

		if (state.activeTrigger && document.body.contains(state.activeTrigger)) {
			state.activeTrigger.focus();
		}

		state.activeTrigger = null;
	}

	function renderMetadata(container, meta) {
		container.innerHTML = '';

		if (!hasObjectValues(meta)) {
			var empty = document.createElement('p');
			empty.className = 'tracevault-meta-empty';
			empty.textContent = tracevaultAdmin.i18n.noMetadata;
			container.appendChild(empty);
			return;
		}

		Object.keys(meta).sort().forEach(function (key) {
			var item = document.createElement('div');
			var label = document.createElement('span');
			var value = document.createElement('pre');

			item.className = 'tracevault-meta-item';
			label.textContent = prettifyKey(key);
			value.textContent = formatMetaValue(meta[key]);
			item.appendChild(label);
			item.appendChild(value);
			container.appendChild(item);
		});
	}

	function openLogModal(item) {
		var modal = getModal();
		var title = modal.querySelector('#tracevault-modal-title');
		var kicker = modal.querySelector('.tracevault-modal-kicker');
		var summary = modal.querySelector('.tracevault-modal-header p');
		var overview = modal.querySelector('[data-tracevault-modal-overview]');
		var metaTitle = modal.querySelector('.tracevault-meta-section h3');
		var meta = modal.querySelector('[data-tracevault-modal-meta]');

		kicker.textContent = tracevaultAdmin.i18n.logDetails + ' #' + item.id;
		title.textContent = eventLabel(item.event_type);
		summary.textContent = formatDetails(item);
		metaTitle.textContent = tracevaultAdmin.i18n.metadata;

		overview.innerHTML = '';
		appendDetail(overview, 'Time', item.display_time || item.created_at);
		appendDetail(overview, 'Severity', severityLabel(item.severity));
		appendDetail(overview, 'User', item.username || (item.user_id ? '#' + item.user_id : 'System'));
		appendDetail(overview, 'IP address', item.ip_address);
		appendDetail(overview, 'Object', item.object_type && parseInt(item.object_id, 10) > 0 ? item.object_type + ' #' + item.object_id : '');
		appendDetail(overview, 'User agent', item.user_agent);

		renderMetadata(meta, item.meta || {});

		state.activeTrigger = document.activeElement;
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('tracevault-modal-open');
		modal.querySelector('[data-tracevault-modal-close]').focus();
	}

	function renderRows(items) {
		var tbody = document.querySelector('[data-tracevault-table="logs"] tbody');

		if (!tbody) {
			return;
		}

		tbody.innerHTML = '';
		state.logs = items;

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
			var viewButton = document.createElement('button');
			var deleteButton = document.createElement('button');
			action.className = 'tracevault-row-actions';
			viewButton.type = 'button';
			viewButton.className = 'button button-small';
			viewButton.textContent = tracevaultAdmin.i18n.view;
			viewButton.setAttribute('data-tracevault-view-id', item.id);
			action.appendChild(viewButton);

			if (tracevaultAdmin.canDelete) {
				deleteButton.type = 'button';
				deleteButton.className = 'button button-small button-link-delete';
				deleteButton.textContent = tracevaultAdmin.i18n.delete;
				deleteButton.setAttribute('data-tracevault-delete-id', item.id);
				action.appendChild(deleteButton);
			}

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
			var viewButton = event.target.closest('[data-tracevault-view-id]');
			var deleteButton = event.target.closest('[data-tracevault-delete-id]');
			var closeButton = event.target.closest('[data-tracevault-modal-close]');

			if (viewButton) {
				var id = parseInt(viewButton.getAttribute('data-tracevault-view-id'), 10);
				var item = state.logs.filter(function (log) {
					return parseInt(log.id, 10) === id;
				})[0];

				if (item) {
					openLogModal(item);
				}

				return;
			}

			if (closeButton) {
				closeModal();
				return;
			}

			if (!deleteButton || !window.confirm(tracevaultAdmin.i18n.confirmDelete)) {
				return;
			}

			post('tracevault_delete_log', {id: deleteButton.getAttribute('data-tracevault-delete-id')}).then(loadLogs);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeModal();
			}
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
