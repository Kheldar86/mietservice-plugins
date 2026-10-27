/* Mietservice Barnim – KI-Artikelassistent (ab 1.2.0) */
(function ($) {
	'use strict';

	if (typeof window.msbAI === 'undefined') {
		return;
	}

	var cfg = window.msbAI;
	var $images = $('#msb-ai-images');
	var frame = null;
	var busy = false;

	/* ---------------- Fotos ---------------- */

	function imageIds() {
		return $images.children('li').map(function () {
			return String($(this).data('id'));
		}).get();
	}

	function addImage(att) {
		if (imageIds().indexOf(String(att.id)) !== -1) {
			return;
		}
		if ($images.children('li').length >= cfg.maxImages) {
			window.alert('Es sind höchstens ' + cfg.maxImages + ' Fotos möglich.');
			return;
		}
		var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
		var $li = $('<li/>', { 'data-id': att.id });
		$li.append($('<div class="msb-ai__thumb"/>').append($('<img/>', { src: url, alt: '' })));
		$li.append($('<button type="button" class="msb-ai__remove" aria-label="Foto entfernen">×</button>'));
		$li.append($('<input type="text" class="msb-ai__alt widefat" placeholder="Alt-Text"/>').val(att.alt || ''));
		$images.append($li);
		markFirst();
	}

	function markFirst() {
		$images.children('li').removeClass('is-first').first().addClass('is-first');
	}

	$('#msb-ai-pick').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: 'Fotos für den neuen Mietartikel',
				button: { text: 'Fotos übernehmen' },
				library: { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				frame.state().get('selection').each(function (m) {
					addImage(m.toJSON());
				});
			});
		}
		frame.open();
	});

	$images.on('click', '.msb-ai__remove', function () {
		$(this).closest('li').remove();
		markFirst();
	});

	if ($.fn.sortable) {
		$images.sortable({ items: '> li', cancel: 'input,button', update: markFirst });
	}

	/* ---------------- Daten ---------------- */

	function formData() {
		var data = {};
		$('#msb-ai-step-data [data-field]').each(function () {
			var $f = $(this);
			if ($f.is(':radio')) {
				if ($f.is(':checked')) {
					data[$f.data('field')] = $f.val();
				}
				return;
			}
			data[$f.data('field')] = $f.val();
		});
		return data;
	}

	function resultData() {
		var r = {};
		$('#msb-ai-step-result [data-result]').each(function () {
			r[$(this).data('result')] = $(this).val();
		});
		return r;
	}

	function alts() {
		var a = {};
		$images.children('li').each(function () {
			a[$(this).data('id')] = $(this).find('.msb-ai__alt').val();
		});
		return a;
	}

	function setStatus($el, text, type) {
		$el.removeClass('is-error is-ok is-busy').addClass(type ? 'is-' + type : '').text(text || '');
	}

	function counters() {
		$('.msb-ai__count').each(function () {
			var $c = $(this);
			var len = ($('#' + $c.data('for')).val() || '').length;
			var max = parseInt($c.data('max'), 10) || 0;
			var min = parseInt($c.data('min'), 10) || 0;
			$c.text(len + ' Zeichen').toggleClass('is-warn', (max && len > max) || (min && len > 0 && len < min));
		});
	}
	$(document).on('input', '#msb-ai-step-result [data-result]', counters);

	/* ---------------- KI-Vorschlag ---------------- */

	function generate() {
		if (busy) {
			return;
		}
		var $st = $('#msb-ai-status');
		var data = formData();
		if (!imageIds().length) {
			setStatus($st, 'Bitte zuerst Fotos auswählen.', 'error');
			return;
		}
		if (!data.category) {
			setStatus($st, 'Bitte eine Kategorie wählen.', 'error');
			return;
		}
		busy = true;
		$('#msb-ai-generate, #msb-ai-regenerate').prop('disabled', true);
		setStatus($st, 'Die KI schreibt den Text … das dauert meist 20–60 Sekunden.', 'busy');

		$.ajax({
			url: cfg.ajax,
			method: 'POST',
			timeout: 150000,
			data: { action: 'msb_ai_generate', nonce: cfg.nonce, images: imageIds(), data: data }
		}).done(function (res) {
			if (!res || !res.success) {
				setStatus($st, (res && res.data && res.data.message) || 'Unbekannter Fehler.', 'error');
				return;
			}
			fill(res.data);
			setStatus($st, 'Fertig – bitte unten prüfen.', 'ok');
		}).fail(function (xhr, status) {
			var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || (status === 'timeout' ? 'Zeitüberschreitung – bitte erneut versuchen.' : 'Verbindungsfehler (' + xhr.status + ').');
			setStatus($st, msg, 'error');
		}).always(function () {
			busy = false;
			$('#msb-ai-generate, #msb-ai-regenerate').prop('disabled', false);
		});
	}

	function fill(r) {
		var map = { product_type: 'product_type', seo_title: 'seo_title', title: 'title', excerpt: 'excerpt', intro: 'intro', features: 'features', ideal: 'ideal', hints: 'hints', condition: 'condition', specs: 'specs', meta: 'meta', keyword: 'keyword' };
		$.each(map, function (key, field) {
			$('#msb-ai-step-result [data-result="' + field + '"]').val(r[key] || '');
		});
		if (r.alts) {
			$images.children('li').each(function () {
				var id = $(this).data('id');
				if (r.alts[id]) {
					$(this).find('.msb-ai__alt').val(r.alts[id]);
				}
			});
		}
		var $list = $('#msb-ai-check-list').empty();
		(r.check || []).forEach(function (c) {
			$list.append($('<li/>').text(c));
		});
		$('#msb-ai-check').prop('hidden', !(r.check && r.check.length));
		$('#msb-ai-step-result').prop('hidden', false);
		$('#msb-ai-step-done').prop('hidden', true);
		counters();
		$('html, body').animate({ scrollTop: $('#msb-ai-step-result').offset().top - 40 }, 300);
	}

	$('#msb-ai-generate, #msb-ai-regenerate').on('click', function (e) {
		e.preventDefault();
		if (this.id === 'msb-ai-regenerate' && !window.confirm('Den Text neu erstellen? Ihre Änderungen unten gehen dabei verloren.')) {
			return;
		}
		generate();
	});

	/* ---------------- Speichern ---------------- */

	$('#msb-ai-save').on('click', function (e) {
		e.preventDefault();
		if (busy) {
			return;
		}
		var $st = $('#msb-ai-save-status');
		var data = formData();
		var missing = [];
		if (!data.price_day || parseFloat(String(data.price_day).replace(',', '.')) <= 0) {
			missing.push('Preis pro Tag');
		}
		if (data.deposit === '') {
			missing.push('Kaution');
		}
		if (!data.category) {
			missing.push('Kategorie');
		}
		if (missing.length) {
			setStatus($st, 'Bitte in Schritt 2 ausfüllen: ' + missing.join(', ') + '.', 'error');
			return;
		}
		busy = true;
		$(this).prop('disabled', true);
		setStatus($st, 'Wird gespeichert …', 'busy');

		$.post(cfg.ajax, {
			action: 'msb_ai_save',
			nonce: cfg.nonce,
			images: imageIds(),
			data: data,
			result: resultData(),
			alts: alts()
		}).done(function (res) {
			if (!res || !res.success) {
				setStatus($st, (res && res.data && res.data.message) || 'Unbekannter Fehler.', 'error');
				return;
			}
			setStatus($st, '', '');
			$('#msb-ai-done-text').text(res.data.message);
			$('#msb-ai-edit').attr('href', res.data.edit);
			$('#msb-ai-preview').attr('href', res.data.preview);
			$('#msb-ai-step-photos, #msb-ai-step-data, #msb-ai-step-result').prop('hidden', true);
			$('#msb-ai-step-done').prop('hidden', false);
			$('html, body').animate({ scrollTop: 0 }, 300);
		}).fail(function (xhr) {
			setStatus($st, (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Verbindungsfehler (' + xhr.status + ').', 'error');
		}).always(function () {
			busy = false;
			$('#msb-ai-save').prop('disabled', false);
		});
	});
})(jQuery);
