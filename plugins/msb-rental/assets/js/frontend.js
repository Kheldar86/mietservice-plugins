/* Mietservice Barnim – Vermietung: Frontend */
(function () {
	'use strict';

	var C = window.MSB || {};
	var T = C.i18n || {};

	function ajax(action, data) {
		var fd = data instanceof FormData ? data : new FormData();
		if (!(data instanceof FormData)) {
			Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		}
		fd.append('action', 'msb_' + action);
		return fetch(C.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); });
	}

	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
	function de(s) { var p = s.split('-'); return p[2] + '.' + p[1] + '.' + p[0]; }
	function monthKey(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
	function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return ymd(d); }
	function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }

	function updateCartCount(n) {
		document.querySelectorAll('.msb-cart-count').forEach(function (e) { e.textContent = n; });
	}

	/* ------------------------------------------------------------------
	 * Kalender / Buchungsbox
	 * ------------------------------------------------------------------ */
	function Booking(root) {
		this.root = root;
		this.item = root.getAttribute('data-item');
		this.mode = root.getAttribute('data-mode') || 'book';
		this.months = Math.max(1, parseInt(root.getAttribute('data-months'), 10) || 1);
		this.stock = parseInt(root.getAttribute('data-stock'), 10) || 1;
		this.start = root.getAttribute('data-start') || null;
		this.end = root.getAttribute('data-end') || null;
		this.days = {};
		this.loaded = {};
		this.calEl = root.querySelector('.msb-cal');
		this.qtyEl = root.querySelector('.msb-qty');
		this.quoteEl = root.querySelector('.msb-quote');
		this.msgEl = root.querySelector('.msb-msg');
		this.addBtn = root.querySelector('.msb-add');
		this.quoteReq = 0;

		var base = this.start ? parse(this.start) : parse(C.today || ymd(new Date()));
		this.view = new Date(base.getFullYear(), base.getMonth(), 1);

		// Auf schmalen Bildschirmen nur einen Monat zeigen.
		if (window.matchMedia && window.matchMedia('(max-width: 600px)').matches) this.months = 1;

		this.bind();
		var self = this;
		var preload = [];
		if (this.start && this.end) {
			// Alle Monate des vorgewählten Zeitraums laden.
			var m = new Date(this.view), endD = parse(this.end);
			while (m <= endD) { preload.push(monthKey(m)); m.setMonth(m.getMonth() + 1); }
		}
		this.render();
		this.load(preload).then(function () {
			if (self.start && self.end && !self.rangeOk(self.start, self.end)) { self.start = self.end = null; }
			self.render();
			self.update();
		});
	}

	Booking.prototype.qty = function () {
		var q = this.qtyEl ? parseInt(this.qtyEl.value, 10) : 1;
		return isNaN(q) || q < 1 ? 1 : q;
	};

	Booking.prototype.visibleMonths = function () {
		var out = [];
		for (var i = 0; i < this.months; i++) {
			out.push(new Date(this.view.getFullYear(), this.view.getMonth() + i, 1));
		}
		return out;
	};

	Booking.prototype.load = function (extra) {
		var self = this;
		var keys = this.visibleMonths().map(monthKey).concat(extra || []);
		var todo = keys.filter(function (k, i) { return keys.indexOf(k) === i && !self.loaded[k]; });
		return Promise.all(todo.map(function (k) {
			self.loaded[k] = true;
			return ajax('availability', { item: self.item, month: k }).then(function (res) {
				if (res && res.success) {
					Object.assign(self.days, res.data.days);
					self.stock = res.data.stock;
				} else {
					self.loaded[k] = false;
				}
			}).catch(function () { self.loaded[k] = false; });
		}));
	};

	Booking.prototype.info = function (d) { return this.days[d]; };

	Booking.prototype.canEdge = function (d) {
		var i = this.info(d);
		return !!i && !i.x && !i.c && i.a >= this.qty();
	};

	Booking.prototype.rangeOk = function (a, b) {
		if (b < a) return false;
		var d = a;
		while (d <= b) {
			var i = this.info(d);
			if (!i || i.x || i.a < this.qty()) return false;
			d = addDays(d, 1);
		}
		return this.canEdge(a) && this.canEdge(b);
	};

	Booking.prototype.bind = function () {
		var self = this;
		this.calEl.addEventListener('click', function (e) {
			var nav = e.target.closest('[data-nav]');
			if (nav) {
				var dir = parseInt(nav.getAttribute('data-nav'), 10);
				self.view = new Date(self.view.getFullYear(), self.view.getMonth() + dir, 1);
				self.render();
				self.load().then(function () { self.render(); });
				return;
			}
			var day = e.target.closest('[data-date]');
			if (day && !day.disabled && self.mode === 'book') self.pick(day.getAttribute('data-date'));
		});

		if (this.mode !== 'book') return;

		if (this.qtyEl) {
			this.qtyEl.addEventListener('change', function () { self.qtyChanged(); });
		}
		this.root.querySelectorAll('.msb-step').forEach(function (b) {
			b.addEventListener('click', function () {
				var v = self.qty() + parseInt(b.getAttribute('data-step'), 10);
				self.qtyEl.value = Math.max(1, Math.min(self.stock, v));
				self.qtyChanged();
			});
		});
		if (this.addBtn) {
			this.addBtn.addEventListener('click', function () { self.add(); });
		}
	};

	Booking.prototype.qtyChanged = function () {
		if (this.qtyEl) {
			var q = Math.max(1, Math.min(this.stock, this.qty()));
			this.qtyEl.value = q;
		}
		if (this.start && this.end && !this.rangeOk(this.start, this.end)) {
			this.end = null;
			if (!this.canEdge(this.start)) this.start = null;
		}
		this.render();
		this.update();
	};

	Booking.prototype.pick = function (d) {
		var nights = C.countMode === 'nights';
		if (!this.start || this.end || d < this.start) {
			if (this.canEdge(d)) { this.start = d; this.end = null; }
		} else if (d === this.start) {
			if (!nights) this.end = d; // Eintagesmiete
		} else if (this.rangeOk(this.start, d)) {
			this.end = d;
		} else if (this.canEdge(d)) {
			this.start = d; this.end = null;
		}
		this.render();
		this.update();
	};

	Booking.prototype.render = function () {
		var self = this;
		var today = C.today || ymd(new Date());
		var wrap = el('div', 'msb-cal__inner');
		var head = el('div', 'msb-cal__head');
		var prevDisabled = monthKey(this.view) <= today.slice(0, 7);
		head.innerHTML =
			'<button type="button" class="msb-cal__nav" data-nav="-1" aria-label="' + (T.prev || '') + '"' + (prevDisabled ? ' disabled' : '') + '>‹</button>' +
			'<button type="button" class="msb-cal__nav" data-nav="1" aria-label="' + (T.next || '') + '">›</button>';
		wrap.appendChild(head);

		var monthsWrap = el('div', 'msb-cal__months');
		this.visibleMonths().forEach(function (m) {
			var box = el('div', 'msb-cal__month');
			box.appendChild(el('div', 'msb-cal__title', C.months[m.getMonth()] + ' ' + m.getFullYear()));
			var grid = el('div', 'msb-cal__grid');
			C.weekdays.forEach(function (w) { grid.appendChild(el('span', 'msb-cal__wd', w)); });
			var lead = (m.getDay() + 6) % 7;
			for (var i = 0; i < lead; i++) grid.appendChild(el('span', 'msb-cal__blank'));
			var last = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
			for (var dnum = 1; dnum <= last; dnum++) {
				var d = ymd(new Date(m.getFullYear(), m.getMonth(), dnum));
				var info = self.info(d);
				var b = el('button', 'msb-day', '<span>' + dnum + '</span>');
				b.type = 'button';
				b.setAttribute('data-date', d);
				var cls = [], title = de(d);
				if (!info) {
					cls.push('is-loading'); b.disabled = true;
				} else if (info.x) {
					cls.push('is-off'); b.disabled = true;
				} else if (info.a < self.qty()) {
					cls.push('is-booked'); b.disabled = true; title += ' – ' + (T.booked || '');
				} else {
					cls.push(info.a < self.stock ? 'is-few' : 'is-free');
					title += ' – ' + info.a + ' ' + (T.available || '');
					if (info.c) { cls.push('is-closed'); title += ' (' + (T.closed || '') + ')'; }
				}
				if (d === today) cls.push('is-today');
				if (self.start && d === self.start) cls.push('is-start');
				if (self.end && d === self.end) cls.push('is-end');
				if (self.start && self.end && d > self.start && d < self.end) cls.push('is-range');
				if (self.mode !== 'book') b.tabIndex = -1;
				b.className += ' ' + cls.join(' ');
				b.title = title;
				b.setAttribute('aria-label', title);
				if (self.start === d || self.end === d) b.setAttribute('aria-pressed', 'true');
				grid.appendChild(b);
			}
			box.appendChild(grid);
			monthsWrap.appendChild(box);
		});
		wrap.appendChild(monthsWrap);
		this.calEl.innerHTML = '';
		this.calEl.appendChild(wrap);
		this.calEl.style.setProperty('--msb-months', this.months);
	};

	Booking.prototype.setMsg = function (html, type) {
		if (!this.msgEl) return;
		this.msgEl.className = 'msb-msg' + (type ? ' msb-msg--' + type : '');
		this.msgEl.innerHTML = html || '';
	};

	Booking.prototype.update = function () {
		if (this.mode !== 'book') return;
		var s = this.root.querySelector('.msb-start'), e = this.root.querySelector('.msb-end');
		if (s) s.textContent = this.start ? de(this.start) : '–';
		if (e) e.textContent = this.end ? de(this.end) : '–';
		this.addBtn.disabled = true;
		this.quoteEl.innerHTML = '';

		if (!this.start) { this.setMsg(T.selectStart, 'hint'); return; }
		if (!this.end) { this.setMsg(T.selectEnd, 'hint'); return; }
		this.setMsg('');

		var self = this, req = ++this.quoteReq;
		this.quoteEl.classList.add('is-loading');
		ajax('quote', { item: this.item, start: this.start, end: this.end, qty: this.qty() }).then(function (res) {
			if (req !== self.quoteReq) return;
			self.quoteEl.classList.remove('is-loading');
			if (res && res.success) {
				self.quoteEl.innerHTML = res.data.html;
				self.addBtn.disabled = false;
			} else {
				self.setMsg((res && res.data && res.data.message) || T.error, 'error');
			}
		}).catch(function () { self.quoteEl.classList.remove('is-loading'); self.setMsg(T.error, 'error'); });
	};

	Booking.prototype.add = function () {
		var self = this;
		if (!this.start || !this.end) return;
		this.addBtn.disabled = true;
		this.addBtn.classList.add('is-loading');
		ajax('cart_add', { item: this.item, start: this.start, end: this.end, qty: this.qty() }).then(function (res) {
			self.addBtn.classList.remove('is-loading');
			if (res && res.success) {
				updateCartCount(res.data.count);
				if (res.data.redirect && res.data.checkout) { window.location.href = res.data.checkout; return; }
				var link = res.data.checkout ? ' <a class="msb-btn msb-btn--primary msb-btn--small" href="' + res.data.checkout + '">' + (T.toCheckout || '') + ' →</a>' : '';
				self.setMsg('<span>' + res.data.message + '</span>' + link, 'success');
				// Kalender neu laden, damit die eigene Auswahl berücksichtigt wird.
				self.loaded = {}; self.days = {};
				self.start = self.end = null;
				self.quoteEl.innerHTML = '';
				self.render();
				self.load().then(function () { self.render(); });
				var s = self.root.querySelector('.msb-start'), e = self.root.querySelector('.msb-end');
				if (s) s.textContent = '–';
				if (e) e.textContent = '–';
			} else {
				self.addBtn.disabled = false;
				self.setMsg((res && res.data && res.data.message) || T.error, 'error');
			}
		}).catch(function () { self.addBtn.disabled = false; self.addBtn.classList.remove('is-loading'); self.setMsg(T.error, 'error'); });
	};

	/* ------------------------------------------------------------------
	 * Warenkorb & Checkout
	 * ------------------------------------------------------------------ */
	function initCart() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.msb-cart-remove');
			if (!btn) return;
			btn.disabled = true;
			ajax('cart_remove', { index: btn.getAttribute('data-index') }).then(function () { window.location.reload(); });
		});
	}

	function initCheckout(form) {
		var msg = form.querySelector('.msb-msg');
		var btn = form.querySelector('button[type="submit"]');

		// Konto anlegen: Passwortfeld ein-/ausblenden.
		var toggle = form.querySelector('.msb-toggle-account');
		var pass = form.querySelector('.msb-account-pass');
		if (toggle && pass) {
			toggle.addEventListener('change', function () {
				pass.hidden = !toggle.checked;
				if (toggle.checked) pass.querySelector('input').focus();
			});
		}
		// Button-Text je nach Zahlungsart.
		var labelOffline = form.getAttribute('data-button') || btn.textContent;
		var labelOnline = form.getAttribute('data-button-online') || btn.textContent;
		var needsOnline = !!form.querySelector('.msb-checkout__payment p');
		function syncButton() {
			var m = form.querySelector('input[name="msb_payment_method"]:checked');
			var online = m && m.getAttribute('data-online') === '1';
			btn.textContent = online && needsOnline ? labelOnline : labelOffline;
		}
		form.querySelectorAll('input[name="msb_payment_method"]').forEach(function (r) { r.addEventListener('change', syncButton); });
		syncButton();
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			form.querySelectorAll('.msb-field.has-error').forEach(function (f) {
				f.classList.remove('has-error');
				var er = f.querySelector('.msb-field__error'); if (er) er.textContent = '';
			});
			msg.className = 'msb-msg'; msg.textContent = '';
			var label = btn.textContent;
			btn.disabled = true; btn.textContent = T.sending || '…';

			ajax('checkout', new FormData(form)).then(function (res) {
				if (res && res.success) {
					updateCartCount(0);
					if (res.data.redirect) { window.location.href = res.data.redirect; return; }
					var wrap = form.closest('.msb-checkout');
					wrap.innerHTML = res.data.html || '';
					wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
					return;
				}
				btn.disabled = false; btn.textContent = label;
				var data = (res && res.data) || {};
				msg.className = 'msb-msg msb-msg--error';
				msg.textContent = data.message || T.error;
				var first = null;
				Object.keys(data.errors || {}).forEach(function (key) {
					var f = form.querySelector('.msb-field[data-key="' + key + '"]');
					if (!f) return;
					f.classList.add('has-error');
					var er = f.querySelector('.msb-field__error'); if (er) er.textContent = data.errors[key];
					if (!first) first = f;
				});
				(first || msg).scrollIntoView({ behavior: 'smooth', block: 'center' });
			}).catch(function () {
				btn.disabled = false; btn.textContent = label;
				msg.className = 'msb-msg msb-msg--error'; msg.textContent = T.error;
			});
		});
	}

	/* ------------------------------------------------------------------
	 * Suche: Rückgabe nicht vor Abholung
	 * ------------------------------------------------------------------ */
	function initSearch(form) {
		var s = form.querySelector('[name="msb_start"]'), e = form.querySelector('[name="msb_end"]');
		if (!s || !e) return;
		s.addEventListener('change', function () {
			if (s.value) { e.min = s.value; if (e.value && e.value < s.value) e.value = s.value; }
		});
	}

	/* ------------------------------------------------------------------
	 * Bildergalerie
	 * ------------------------------------------------------------------ */
	function initGallery(g) {
		var slides = g.querySelectorAll('.msb-gallery__slide');
		if (slides.length < 2) return;
		var idx = 0;
		function show(i) {
			idx = (i + slides.length) % slides.length;
			slides.forEach(function (s, n) { s.classList.toggle('is-active', n === idx); });
			g.querySelectorAll('.msb-gallery__thumb, .msb-gallery__dot').forEach(function (t) {
				t.classList.toggle('is-active', parseInt(t.getAttribute('data-index'), 10) === idx);
			});
		}
		g.addEventListener('click', function (e) {
			var t = e.target.closest('.msb-gallery__arrow, .msb-gallery__thumb, .msb-gallery__dot');
			if (!t) return;
			e.preventDefault();
			if (t.classList.contains('msb-gallery__arrow--prev')) show(idx - 1);
			else if (t.classList.contains('msb-gallery__arrow--next')) show(idx + 1);
			else show(parseInt(t.getAttribute('data-index'), 10));
		});
		// Wischen auf Touch-Geräten.
		var x0 = null;
		g.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		g.addEventListener('touchend', function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 40) show(idx + (dx < 0 ? 1 : -1));
			x0 = null;
		});
	}

	function initLightbox() {
		document.addEventListener('click', function (e) {
			var z = e.target.closest('.msb-gallery__zoom');
			if (!z) return;
			var gal = z.closest('.msb-gallery');
			var urls = Array.prototype.map.call(gal.querySelectorAll('.msb-gallery__zoom'), function (b) { return b.getAttribute('data-full'); });
			var i = urls.indexOf(z.getAttribute('data-full'));
			var box = el('div', 'msb msb-lightbox');
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');
			box.innerHTML = '<img alt=""><button type="button" class="msb-lightbox__close" aria-label="Schließen">×</button>' +
				(urls.length > 1 ? '<button type="button" class="msb-lightbox__nav msb-lightbox__prev" aria-label="Zurück">‹</button><button type="button" class="msb-lightbox__nav msb-lightbox__next" aria-label="Weiter">›</button>' : '');
			var img = box.querySelector('img');
			function go(n) { i = (n + urls.length) % urls.length; img.src = urls[i]; }
			function close() { document.removeEventListener('keydown', key); box.remove(); z.focus(); }
			function key(ev) {
				if (ev.key === 'Escape') close();
				if (ev.key === 'ArrowLeft') go(i - 1);
				if (ev.key === 'ArrowRight') go(i + 1);
			}
			box.addEventListener('click', function (ev) {
				if (ev.target.closest('.msb-lightbox__prev')) go(i - 1);
				else if (ev.target.closest('.msb-lightbox__next')) go(i + 1);
				else if (ev.target === box || ev.target.closest('.msb-lightbox__close')) close();
			});
			document.addEventListener('keydown', key);
			go(i);
			document.body.appendChild(box);
			box.querySelector('.msb-lightbox__close').focus();
		});
	}

	function init() {
		document.querySelectorAll('.msb-gallery').forEach(initGallery);
		initLightbox();
		document.querySelectorAll('.msb-booking[data-item]').forEach(function (r) {
			if (!r.__msb) { r.__msb = new Booking(r); }
		});
		document.querySelectorAll('.msb-checkout-form').forEach(initCheckout);
		document.querySelectorAll('.msb-search__form').forEach(initSearch);
		initCart();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
