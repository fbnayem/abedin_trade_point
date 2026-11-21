/* ATP Quote: quote list interactions. No dependencies. */
(function () {
	'use strict';
	var cfg = window.atpQuote || {};
	var t = cfg.i18n || {};

	function api(method, body, query) {
		var url = cfg.rest + (query ? (cfg.rest.indexOf('?') > -1 ? '&' : '?') + query : '');
		return fetch(url, {
			method: method,
			credentials: 'same-origin',
			headers: body ? { 'Content-Type': 'application/json' } : {},
			body: body ? JSON.stringify(body) : undefined
		}).then(function (r) {
			return r.json().then(function (data) {
				if (!r.ok) { throw new Error(data && data.message ? data.message : t.error); }
				return data;
			});
		});
	}

	function setCount(n) {
		changed = true;
		document.querySelectorAll('[data-atp-count]').forEach(function (el) { el.textContent = n; });
	}

	var toastEl;
	function toast(msg) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'atp-toast';
			toastEl.setAttribute('role', 'status');
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.hidden = false;
		clearTimeout(toastEl._t);
		toastEl._t = setTimeout(function () { toastEl.hidden = true; }, 3200);
	}

	// Refresh the count on load (pages may be cached).
	var changed = false;
	if (document.querySelector('[data-atp-count]')) {
		api('GET').then(function (d) { if (!changed) { setCount(d.count); } }).catch(function () {});
	}

	// Loop buttons.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-atp-add]');
		if (!btn) { return; }
		e.preventDefault();
		btn.disabled = true;
		api('POST', { product_id: +btn.getAttribute('data-atp-add'), qty: +(btn.getAttribute('data-atp-default-qty') || 1) })
			.then(function (d) { setCount(d.count); toast(t.added); })
			.catch(function (err) { toast(err.message); })
			.finally(function () { btn.disabled = false; });
	});

	// Single product form.
	document.querySelectorAll('[data-atp-single]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.atp-msg');
			var qty = form.querySelector('[data-atp-qty]');
			var options = {};
			var missing = null;
			form.querySelectorAll('[data-atp-option]').forEach(function (sel) {
				if (!sel.value && !missing) { missing = sel; }
				options[sel.getAttribute('data-atp-option')] = sel.value;
			});
			if (missing) { msg.textContent = missing.previousElementSibling.textContent + ': ' + (t.choose || 'please choose an option.'); missing.focus(); return; }
			if (!qty.value || +qty.value <= 0) { msg.textContent = t.qty; qty.focus(); return; }
			var btn = form.querySelector('button[type=submit]');
			btn.disabled = true;
			api('POST', {
				product_id: +form.getAttribute('data-atp-single'),
				qty: +qty.value,
				options: options,
				notes: (form.querySelector('[data-atp-notes]') || {}).value || ''
			}).then(function (d) {
				setCount(d.count);
				msg.innerHTML = '';
				msg.appendChild(document.createTextNode(t.added + ' '));
				var a = document.createElement('a');
				a.href = cfg.quoteUrl;
				a.textContent = 'Review your quote list';
				msg.appendChild(a);
			}).catch(function (err) { msg.textContent = err.message; })
			  .finally(function () { btn.disabled = false; });
		});
	});

	// Quote page: edit and remove lines.
	var list = document.querySelector('[data-atp-list]');
	if (list) {
		list.addEventListener('click', function (e) {
			var rm = e.target.closest('[data-atp-remove]');
			if (!rm) { return; }
			var row = rm.closest('tr');
			api('DELETE', null, 'key=' + encodeURIComponent(row.getAttribute('data-key')))
				.then(function (d) {
					row.remove();
					setCount(d.count);
					toast(t.removed);
					if (!d.count) { location.reload(); }
				}).catch(function (err) { toast(err.message); });
		});
		list.addEventListener('change', function (e) {
			var row = e.target.closest('tr');
			if (!row) { return; }
			var body = { key: row.getAttribute('data-key') };
			if (e.target.matches('[data-atp-qty]')) { body.qty = +e.target.value; }
			if (e.target.matches('[data-atp-notes]')) { body.notes = e.target.value; }
			api('PATCH', body).catch(function (err) { toast(err.message); e.target.focus(); });
		});
	}

	// File list preview with remove.
	var fileInput = document.querySelector('[data-atp-files]');
	var fileList = document.querySelector('[data-atp-file-list]');
	if (fileInput && fileList && window.DataTransfer) {
		var allowed = /\.(pdf|xlsx|docx|jpe?g|png)$/i;
		var render = function () {
			fileList.innerHTML = '';
			Array.prototype.forEach.call(fileInput.files, function (f, i) {
				var li = document.createElement('li');
				var bad = !allowed.test(f.name) || f.size > 10 * 1024 * 1024;
				li.textContent = f.name + ' (' + Math.ceil(f.size / 1024) + ' KB)' + (bad ? ' — this file type or size is not accepted' : '');
				if (bad) { li.className = 'is-bad'; }
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'atp-link-btn';
				b.textContent = 'Remove';
				b.addEventListener('click', function () {
					var dt = new DataTransfer();
					Array.prototype.forEach.call(fileInput.files, function (g, j) { if (j !== i) { dt.items.add(g); } });
					fileInput.files = dt.files;
					render();
				});
				li.appendChild(b);
				fileList.appendChild(li);
			});
		};
		fileInput.addEventListener('change', render);
	}

	// Submit once; record the landing page for reporting.
	var form = document.querySelector('.atp-form');
	if (form) {
		var landing = form.querySelector('[data-atp-landing]');
		try { landing.value = sessionStorage.getItem('atp_landing') || document.referrer || ''; } catch (err) {}
		form.addEventListener('submit', function () {
			var b = form.querySelector('[data-atp-submit]');
			setTimeout(function () { b.disabled = true; b.textContent = 'Sending…'; }, 0);
		});
		var mode = document.getElementById('atp-quote-form').getAttribute('data-mode');
		if (mode === 'type' || mode === 'upload') {
			var target = document.getElementById('atp-route-' + mode);
			if (target) { target.scrollIntoView(); }
		}
		var errs = document.getElementById('atp-errors');
		if (errs) { errs.focus(); }
	}
	try { if (!sessionStorage.getItem('atp_landing')) { sessionStorage.setItem('atp_landing', location.href); } } catch (err) {}

	// Conversion: fires once, only after the server saved the enquiry.
	var conv = document.querySelector('[data-atp-conversion]');
	if (conv) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'quote_request_saved', enquiry_reference: conv.getAttribute('data-atp-conversion') });
	}
})();
