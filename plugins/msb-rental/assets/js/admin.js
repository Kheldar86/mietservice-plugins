/* Mietservice Barnim – Vermietung: Backend */
jQuery(function ($) {
	'use strict';

	/* Formular-Builder ------------------------------------------------ */
	var $builder = $('#msb-form-builder');
	if ($builder.length) {
		var $body = $builder.find('.msb-fields tbody');
		var tmpl = $('#tmpl-msb-field-row').html();

		function renumber() {
			$body.find('tr.msb-field-row').each(function (i) {
				$(this).find('[name^="fields["]').each(function () {
					this.name = this.name.replace(/^fields\[[^\]]*\]/, 'fields[' + i + ']');
				});
			});
		}
		function toggleExtra($row) {
			var t = $row.find('.msb-type').val();
			$row.find('.msb-opts').toggle(t === 'select' || t === 'radio');
			$row.find('.msb-ph').toggle(['text', 'email', 'tel', 'number', 'textarea', 'select'].indexOf(t) !== -1);
			$row.toggleClass('is-layout', t === 'heading' || t === 'paragraph');
		}

		$body.sortable({ handle: '.msb-handle', axis: 'y', update: renumber });
		$body.find('tr.msb-field-row').each(function () { toggleExtra($(this)); });

		$('#msb-add-field').on('click', function () {
			var $row = $(tmpl.replace(/__i__/g, $body.children().length));
			$body.append($row);
			toggleExtra($row);
			renumber();
			$row.find('input[type="text"]').first().trigger('focus');
		});
		$body.on('click', '.msb-remove-field', function () {
			if (confirm('Feld entfernen?')) { $(this).closest('tr').remove(); renumber(); }
		});
		$body.on('change', '.msb-type', function () { toggleExtra($(this).closest('tr')); });
		$builder.on('submit', renumber);
		// Handy: Pfeile statt Ziehen.
		$body.on('click', '.msb-move', function () {
			var $row = $(this).closest('tr');
			if ($(this).data('dir') < 0) { $row.prev().before($row); } else { $row.next().after($row); }
			renumber();
		});
	}

	/* Manuelle Buchung: weitere Positionen ---------------------------- */
	var $lines = $('.msb-lines tbody');
	if ($lines.length) {
		// Spaltenüberschrift je Zelle für die Kartenansicht auf dem Handy.
		var heads = $lines.closest('table').find('thead th').map(function () { return $.trim($(this).text()); }).get();
		$lines.find('tr.msb-line td').each(function () {
			if (heads[$(this).index()]) $(this).attr('data-label', heads[$(this).index()]);
		});
		function renumberLines() {
			$lines.find('tr.msb-line').each(function (i) {
				$(this).find('[name^="lines["]').each(function () {
					this.name = this.name.replace(/^lines\[\d+\]/, 'lines[' + i + ']');
				});
			});
		}
		$('#msb-add-line').on('click', function () {
			var $last = $lines.find('tr.msb-line').last();
			var $row = $last.clone();
			$row.find('select').val('');
			$row.find('input[type="number"]').val(1);
			// Datum der vorherigen Zeile übernehmen.
			$lines.append($row);
			renumberLines();
		});
		$lines.on('click', '.msb-remove-line', function () {
			if ($lines.find('tr.msb-line').length > 1) { $(this).closest('tr').remove(); renumberLines(); }
		});
		$lines.on('change', 'input[name$="[start]"]', function () {
			var $end = $(this).closest('tr').find('input[name$="[end]"]');
			$end.attr('min', this.value);
			if (!$end.val() || $end.val() < this.value) $end.val(this.value);
		});
	}

	/* Preisregeln ------------------------------------------------------ */
	$('.msb-rules').each(function () {
		var $wrap = $(this);
		var name = $wrap.data('name');
		var $body = $wrap.find('tbody');
		var tmpl = $wrap.find('.msb-rules__tmpl').html();
		var dayTypes = ['weekdays', 'period'];

		function sync($row) {
			var type = $row.find('.msb-rule__type').val();
			$row.attr('data-type', type);
			// Nur die Bedingung des gewählten Typs anzeigen und absenden.
			$row.find('.msb-c').each(function () {
				var on = $(this).hasClass('msb-c--' + type);
				$(this).toggle(on).find('input').prop('disabled', !on);
			});
			var isDay = dayTypes.indexOf(type) !== -1;
			var $mode = $row.find('.msb-rule__mode');
			$mode.find('option').each(function () {
				var ok = $(this).data(isDay ? 'day' : 'booking') === 1;
				$(this).prop('disabled', !ok).toggle(ok);
			});
			if ($mode.find('option:selected').prop('disabled')) {
				$mode.val($mode.find('option:not(:disabled)').first().val());
			}
			if (type === 'weekend') {
				var $max = $row.find('.msb-c--weekend .msb-in-max');
				if (!$max.val()) $max.val(4);
			}
		}
		function renumber() {
			var re = new RegExp('^' + name.replace(/[\[\]]/g, '\\$&') + '\\[[^\\]]*\\]');
			$body.find('tr.msb-rule').each(function (i) {
				$(this).find('[name]').each(function () {
					this.name = this.name.replace(re, name + '[' + i + ']');
				});
			});
		}
		$body.find('tr.msb-rule').each(function () { sync($(this)); });
		$wrap.on('click', '.msb-rules__add', function () {
			var $row = $(tmpl.replace(/__i__/g, $body.children().length));
			$body.append($row);
			sync($row);
			renumber();
		});
		$body.on('click', '.msb-rule__remove', function () {
			$(this).closest('tr').remove();
			renumber();
		});
		$body.on('change', '.msb-rule__type', function () { sync($(this).closest('tr')); });
		$wrap.closest('form').on('submit', renumber);
	});

	/* Galerie im Artikel -------------------------------------------- */
	$('.msb-gallery-edit').each(function () {
		var $box = $(this);
		var $list = $box.find('.msb-gallery-edit__list');
		var $input = $box.find('input[type="hidden"]');
		var frame;
		function save() {
			$input.val($list.children().map(function () { return $(this).data('id'); }).get().join(','));
		}
		$list.sortable({ update: save });
		$box.on('click', '.msb-gallery-edit__remove', function () { $(this).closest('li').remove(); save(); });
		$box.on('click', '.msb-gallery-edit__add', function (e) {
			e.preventDefault();
			if (!window.wp || !wp.media) return;
			if (!frame) {
				frame = wp.media({ title: 'Bilder für die Galerie wählen', button: { text: 'Zur Galerie hinzufügen' }, library: { type: 'image' }, multiple: 'add' });
				frame.on('select', function () {
					frame.state().get('selection').each(function (att) {
						var a = att.toJSON();
						if ($list.find('li[data-id="' + a.id + '"]').length) return;
						var src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
						$list.append('<li data-id="' + a.id + '"><img src="' + src + '" alt=""><button type="button" class="msb-gallery-edit__remove" aria-label="Bild entfernen">×</button></li>');
					});
					save();
				});
			}
			frame.open();
		});
	});

	/* Neue Buchung: Kunde wählen füllt Felder ------------------------- */
	$('#msb-customer-select').on('change', function () {
		var data = $(this).find('option:selected').data('values');
		if (!data) return;
		$.each(data, function (key, val) {
			var $f = $('[name="msb_field[' + key + ']"]');
			if ($f.length) $f.val(val);
		});
	});

	/* Handy: Tabellen als Karten (Baustein msb-cards, ab 1.6.4) -------- */
	// Artikel und Zahlungen in der Buchung nutzen den Baustein ohne eigenes Markup.
	$('.msb-detail__main > .msb-card-box:first-child > table.widefat').addClass('msb-cards').attr('data-aside', 7);
	$('.msb-pay-table').addClass('msb-cards').attr({ 'data-aside': 3, 'data-actions': 6 });
	$('#bookings > table.widefat').addClass('msb-cards').attr('data-aside', 5);
	$('.wp-list-table.kunden tbody td').not('.check-column, .column-primary, .column-account').each(function () {
		$(this).addClass('msb-c-label').attr('data-label', $(this).attr('data-colname'));
	});
	// Inventar: Tag (aus dem Tabellenkopf) an jede Zelle, für die Kachelansicht.
	$('.msb-inv').each(function () {
		var $th = $(this).find('thead th');
		$(this).find('tbody tr').each(function () {
			$(this).children('td').each(function (i) {
				var $h = $th.eq(i);
				if (i === 1) $(this).addClass('msb-inv__stock');
				if (!$(this).hasClass('msb-inv__cell')) return;
				$(this).toggleClass('is-today', $h.hasClass('is-today')).toggleClass('is-closed', $h.hasClass('is-closed'));
				$(this).children('a').attr('data-day', $.trim($h.html().replace(/<br\s*\/?>/i, '\n')));
			});
		});
	});
	// Reiter: aktiven Reiter in den sichtbaren Bereich schieben.
	$('.msb-admin .nav-tab-wrapper').each(function () {
		var a = $(this).find('.nav-tab-active')[0];
		if (a && this.scrollWidth > this.clientWidth) this.scrollLeft = a.offsetLeft - this.offsetLeft - 16;
	});
	$('table.msb-cards').each(function () {
		var $t = $(this);
		var heads = $t.find('thead th').map(function () { return $.trim($(this).text()); }).get();
		var aside = parseInt($t.attr('data-aside'), 10) || 0;
		var actions = parseInt($t.attr('data-actions'), 10) || 0;
		$t.find('tbody tr').each(function () {
			$(this).children('td').each(function (i) {
				var $c = $(this);
				if (i === 0) {
					$c.addClass('msb-c-head');
				} else if (i + 1 === aside) {
					$c.addClass('msb-c-aside');
				} else if (i + 1 === actions) {
					$c.addClass('msb-c-actions');
				} else if (heads[i]) {
					$c.attr('data-label', heads[i]);
				}
				if (!$.trim($c.text()) && !$c.find('input, select, button').length) $c.addClass('msb-c-empty');
			});
		});
	});
	$('.wp-list-table.buchungen tbody td').not('.check-column, .column-primary, .column-status').each(function () {
		$(this).addClass('msb-c-label').attr('data-label', $(this).attr('data-colname'));
	});
});

/* Dokumentation: Suche & aktiver Abschnitt */
(function () {
	var input = document.getElementById('msb-doc-search');
	if (!input) return;
	var docs = document.querySelectorAll('.msb-doc');
	input.addEventListener('input', function () {
		var q = input.value.trim().toLowerCase();
		docs.forEach(function (d) {
			d.classList.toggle('is-hidden', q.length > 1 && d.textContent.toLowerCase().indexOf(q) === -1);
		});
	});
	var links = document.querySelectorAll('.msb-docs__toc a');
	if ('IntersectionObserver' in window) {
		var obs = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (!e.isIntersecting) return;
				links.forEach(function (a) { a.classList.toggle('is-current', a.getAttribute('href') === '#' + e.target.id); });
			});
		}, { rootMargin: '-40% 0px -55% 0px' });
		docs.forEach(function (d) { obs.observe(d); });
	}
})();
