/**
 * ACF Copy Field
 *
 * Injects a "Copy" action next to ACF's built-in "Move" link on every field
 * object, opens a group picker, and copies the field via AJAX.
 */
(function ($) {
	'use strict';

	if (typeof acf === 'undefined') {
		return;
	}

	/**
	 * Selector for ACF's built-in "Move" action link.
	 *
	 * The Copy link is inserted right after it, so it inherits the same look.
	 * ACF's markup has shifted between major versions, so a few known variants
	 * are listed. If your ACF version places the link elsewhere, adjust this.
	 */
	var MOVE_SELECTOR = '.move-field, .acf-field-object-action-move, a[data-event="move-field"]';

	/**
	 * Add a "Copy" link to a single field object's action row.
	 *
	 * @param {object} fieldObject ACF field object model instance.
	 */
	function addCopyLink(fieldObject) {
		if (!fieldObject || !fieldObject.$el) {
			return;
		}

		var $el = fieldObject.$el;

		// Guard against running twice on the same field.
		if ($el.data('acfcfReady')) {
			return;
		}

		// Locate THIS field's ".row-options" container (ignore nested sub-fields).
		var $rowOptions = $el.find('.row-options').filter(function () {
			return $(this).closest('.acf-field-object').get(0) === $el.get(0);
		}).first();

		if (!$rowOptions.length) {
			return;
		}

		// Already added? (covers both this guard and the data flag.)
		if ($rowOptions.children('.acfcf-copy-field').length) {
			$el.data('acfcfReady', true);
			return;
		}

		$el.data('acfcfReady', true);

		var $copy = $('<a/>', {
			href: '#',
			'class': 'acfcf-copy-field copy-field acf-js-tooltip',
			title: ACFCF.i18n.title,
			text: ACFCF.i18n.copy
		});

		// Insert right after the Move link inside .row-options. If Move can't be
		// found for some reason, fall back to appending to .row-options so Copy
		// always lands in the correct action row.
		var $move = $rowOptions.find(MOVE_SELECTOR).first();
		if ($move.length) {
			$copy.insertAfter($move);
		} else {
			$rowOptions.append($copy);
		}

		$copy.on('click', function (e) {
			e.preventDefault();
			openModal(fieldObject);
		});
	}

	/**
	 * Open the "copy to group" modal for a field.
	 *
	 * @param {object} fieldObject ACF field object model instance.
	 */
	function openModal(fieldObject) {
		var fieldKey = fieldObject.$el.attr('data-key') || fieldObject.get('key');

		if (!ACFCF.groups || !ACFCF.groups.length) {
			notice('error', ACFCF.i18n.error);
			return;
		}

		var options = ACFCF.groups.map(function (g) {
			return '<option value="' + escapeHtml(g.key) + '">' + escapeHtml(g.title) + '</option>';
		}).join('');

		var $modal = $(
			'<div class="acfcf-modal-overlay">' +
				'<div class="acfcf-modal" role="dialog" aria-modal="true">' +
					'<h3 class="acfcf-modal-title"></h3>' +
					'<p class="acfcf-field-key"></p>' +
					'<label class="acfcf-label"></label>' +
					'<select class="acfcf-group-select">' + options + '</select>' +
					'<div class="acfcf-modal-actions">' +
						'<button type="button" class="button acfcf-cancel"></button>' +
						'<button type="button" class="button button-primary acfcf-confirm"></button>' +
					'</div>' +
					'<div class="acfcf-modal-loading"><span class="spinner is-active"></span></div>' +
				'</div>' +
			'</div>'
		).appendTo('body');

		$modal.find('.acfcf-modal-title').text(ACFCF.i18n.title);
		$modal.find('.acfcf-field-key').text(fieldKey);
		$modal.find('.acfcf-cancel').text(ACFCF.i18n.cancel);
		$modal.find('.acfcf-confirm').text(ACFCF.i18n.confirm);

		function close() {
			$modal.remove();
			$(document).off('keydown.acfcf');
		}

		$modal.on('click', '.acfcf-cancel', close);
		$modal.on('click', '.acfcf-modal-overlay', function (e) {
			if (e.target === this) {
				close();
			}
		});
		$(document).on('keydown.acfcf', function (e) {
			if (e.key === 'Escape') {
				close();
			}
		});

		$modal.on('click', '.acfcf-confirm', function () {
			var groupKey = $modal.find('.acfcf-group-select').val();

			$modal.find('.acfcf-modal-actions').hide();
			$modal.find('.acfcf-modal-loading').addClass('is-visible');

			$.ajax({
				url: ACFCF.ajaxurl,
				method: 'POST',
				data: {
					action: 'acfcf_copy_field',
					nonce: ACFCF.nonce,
					field_key: fieldKey,
					group_key: groupKey
				}
			}).done(function (res) {
				close();
				if (res && res.success) {
					notice('success', format(ACFCF.i18n.success, res.data.group_title), res.data.group_url);
				} else {
					var code = res && res.data && res.data.message;
					notice('error', code === 'unsaved' ? ACFCF.i18n.unsaved : ACFCF.i18n.error);
				}
			}).fail(function () {
				close();
				notice('error', ACFCF.i18n.error);
			});
		});
	}

	/**
	 * Show a small transient toast in the corner.
	 */
	function notice(type, message, url) {
		var $n = $('<div/>', { 'class': 'acfcf-notice acfcf-notice-' + type });

		if (url) {
			$('<a/>', { href: url, text: message }).appendTo($n);
		} else {
			$n.text(message);
		}

		$n.appendTo('body');
		window.setTimeout(function () { $n.addClass('is-visible'); }, 10);
		window.setTimeout(function () { $n.removeClass('is-visible'); }, 4500);
		window.setTimeout(function () { $n.remove(); }, 5000);
	}

	function format(tpl, value) {
		return String(tpl).replace('%s', value);
	}

	function escapeHtml(str) {
		return $('<div/>').text(str == null ? '' : str).html();
	}

	// Fired when a field object is added live by the user...
	acf.addAction('new_field_object', addCopyLink);

	// ...and catch any field objects already on screen at load time.
	$(function () {
		$('.acf-field-object').each(function () {
			var fo = acf.getFieldObject ? acf.getFieldObject($(this)) : null;
			if (fo) {
				addCopyLink(fo);
			}
		});
	});

})(jQuery);
