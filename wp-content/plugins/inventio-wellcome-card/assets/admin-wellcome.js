/**
 * Επιλογή καμβά από τη βιβλιοθήκη μέσων + εναλλαγή προκαθορισμένων templates.
 */
(function ($) {
	'use strict';

	function getTeamBodyMaxChars() {
		var n =
			window.inventioWellcomeAdmin && window.inventioWellcomeAdmin.teamBodyMaxChars != null
				? parseInt(window.inventioWellcomeAdmin.teamBodyMaxChars, 10)
				: 80;
		return isNaN(n) || n < 1 ? 80 : n;
	}

	function unicodeLen(str) {
		if (typeof Array.from === 'function') {
			return Array.from(String(str || '')).length;
		}
		return String(str || '').length;
	}

	function teamBodyIndexFromTextarea(el) {
		var m = /^team_body_(\d+)$/.exec(el.id || '');
		return m ? parseInt(m[1], 10) : 0;
	}

	function updateTeamBodyStatus(j) {
		var max = getTeamBodyMaxChars();
		var $ta = $('#team_body_' + j);
		var $wrap = $('#inventio-team-body-wrap-' + j);
		var $count = $('#inventio-team-body-count-' + j);
		var $msg = $('#inventio-team-body-limit-msg-' + j);
		if (!$ta.length || !$count.length) {
			return;
		}
		var lim = limitTeamBodyStr($ta.val());
		if (lim !== $ta.val()) {
			$ta.val(lim);
		}
		var len = unicodeLen($ta.val());
		$count.text(len + ' / ' + max);
		var at = len >= max;
		$ta.toggleClass('inventio-team-body-at-limit', at);
		if ($wrap.length) {
			$wrap.toggleClass('inventio-team-body-at-limit', at);
		}
		var admin = window.inventioWellcomeAdmin || {};
		var tAt = admin.teamBodyAtLimit || '';
		if ($msg.length) {
			if (at && tAt) {
				$msg.text(tAt).prop('hidden', false);
			} else {
				$msg.text('').prop('hidden', true);
			}
		}
	}

	function refreshAllTeamBodyStatus() {
		var j;
		for (j = 1; j <= 5; j++) {
			updateTeamBodyStatus(j);
		}
	}

	function limitTeamBodyStr(str) {
		var max = getTeamBodyMaxChars();
		if (!str) {
			return '';
		}
		var s = String(str);
		if (typeof Array.from === 'function') {
			var arr = Array.from(s);
			if (arr.length <= max) {
				return s;
			}
			return arr.slice(0, max).join('');
		}
		return s.length <= max ? s : s.slice(0, max);
	}

	function bindTeamBodyFields() {
		var j;
		for (j = 1; j <= 5; j++) {
			$('#team_body_' + j).on('input.inventioTeamBody blur.inventioTeamBody', function () {
				var $t = $(this);
				var lim = limitTeamBodyStr($t.val());
				if (lim !== $t.val()) {
					$t.val(lim);
				}
				var idx = teamBodyIndexFromTextarea(this);
				if (idx) {
					updateTeamBodyStatus(idx);
				}
			});
		}
	}

	function getPresetsRoot() {
		return window.inventioWellcomeAdmin && window.inventioWellcomeAdmin.presets
			? window.inventioWellcomeAdmin.presets
			: null;
	}

	function currentPresetKey() {
		var $sel = $('#inventio-active-preset');
		return $sel.length ? $sel.val() : '';
	}

	function applyPresetKey(key) {
		var root = getPresetsRoot();
		if (!root || !root.list || !root.list[key]) {
			return;
		}
		var p = root.list[key];
		var $input = $('#inventio-template-id');
		var $img = $('#inventio-template-preview');
		var $empty = $('#inventio-template-preview-empty');

		$input.val(String(p.templateId || 0));
		$('.inventio-preset-key-field').val(key);
		$('#split_ratio').val(String(p.splitRatio != null ? p.splitRatio : 0.42));
		$('#headline').val(p.headline || '');

		var isTeam = p.layout === 'team_portrait';
		$('.inventio-row-headline').toggle(!isTeam);
		$('.inventio-row-split-ratio').toggle(!isTeam);
		$('.inventio-only-split').toggle(!isTeam);
		$('.inventio-only-team').toggle(!!isTeam);

		if (isTeam && p.teamPhotoBg !== undefined) {
			$('#team_photo_bg').val(p.teamPhotoBg || '#bfe8e8');
			$('#team_card_name').val(p.teamCardName || '');
			$('#team_card_title').val(p.teamCardTitle || '');
			var j;
			for (j = 1; j <= 5; j++) {
				$('#team_body_' + j).val(limitTeamBodyStr(p['teamBody' + j] || ''));
				$('#team_body_' + j + '_tone').val(p['teamBody' + j + 'Tone'] || (j <= 2 ? 'dark' : 'light'));
			}
		}

		if (p.previewUrl) {
			$img.attr('src', p.previewUrl).css('display', 'block');
			$empty.hide();
		} else {
			$img.attr('src', '').css('display', 'none');
			$empty.show();
		}

		refreshAllTeamBodyStatus();
	}

	function syncPresetListEntry(key, templateId, previewUrl) {
		var root = getPresetsRoot();
		if (!root || !root.list || !root.list[key]) {
			return;
		}
		root.list[key].templateId = parseInt(templateId, 10) || 0;
		root.list[key].previewUrl = previewUrl || '';
	}

	function bindPresetSelect() {
		var $sel = $('#inventio-active-preset');
		if (!$sel.length) {
			return;
		}
		$sel.on('change.inventioWellcome', function () {
			applyPresetKey($(this).val());
		});
	}

	function bindPick() {
		var $input = $('#inventio-template-id');
		var $pick = $('#inventio-pick-template');
		var $clear = $('#inventio-clear-template');

		if (!$input.length || !$pick.length || !$clear.length) {
			return;
		}

		$clear.on('click.inventioWellcome', function (e) {
			e.preventDefault();
			$input.val('0');
			var key = currentPresetKey();
			syncPresetListEntry(key, 0, '');
			applyPresetKey(key);
		});

		$pick.on('click.inventioWellcome', function (e) {
			e.preventDefault();

			if (typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined') {
				if (window.console && console.warn) {
					console.warn('Inventio Wellcome: wp.media not loaded yet.');
				}
				return;
			}

			var title =
				window.inventioWellcomeAdmin && window.inventioWellcomeAdmin.pickTitle
					? window.inventioWellcomeAdmin.pickTitle
					: '';

			var frame = window.wp.media({
				title: title,
				multiple: false,
				library: { type: 'image' },
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var id = att.id;
				var url = att.url || (att.sizes && att.sizes.medium ? att.sizes.medium.url : '') || '';
				$input.val(String(id));
				var key = currentPresetKey();
				syncPresetListEntry(key, id, url);
				applyPresetKey(key);
			});

			frame.open();
		});
	}

	function clearEmployeeLibraryUi() {
		$('#inventio-employee-photo-id').val('0');
		$('#inventio-employee-library-preview').attr('src', '');
		$('#inventio-employee-library-preview-wrap').hide();
	}

	function bindEmployeePhoto() {
		var $id = $('#inventio-employee-photo-id');
		var $pick = $('#inventio-pick-employee-photo');
		var $clear = $('#inventio-clear-employee-photo');
		var $file = $('#employee_photo');
		var $wrap = $('#inventio-employee-library-preview-wrap');
		var $img = $('#inventio-employee-library-preview');

		if (!$id.length || !$pick.length || !$clear.length) {
			return;
		}

		$clear.on('click.inventioWellcomeEmployee', function (e) {
			e.preventDefault();
			clearEmployeeLibraryUi();
		});

		$pick.on('click.inventioWellcomeEmployee', function (e) {
			e.preventDefault();

			if (typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined') {
				if (window.console && console.warn) {
					console.warn('Inventio Wellcome: wp.media not loaded yet.');
				}
				return;
			}

			var title =
				window.inventioWellcomeAdmin && window.inventioWellcomeAdmin.pickEmployeeTitle
					? window.inventioWellcomeAdmin.pickEmployeeTitle
					: '';

			var frame = window.wp.media({
				title: title,
				multiple: false,
				library: { type: 'image' },
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var id = att.id;
				var url = att.url || (att.sizes && att.sizes.medium ? att.sizes.medium.url : '') || '';
				if (!id) {
					clearEmployeeLibraryUi();
					return;
				}
				$id.val(String(id));
				if (url) {
					$img.attr('src', url).css('display', 'inline-block');
				} else {
					$img.attr('src', '').css('display', 'none');
				}
				$wrap.show();
				if ($file.length) {
					$file.val('');
				}
			});

			frame.open();
		});

		$file.on('change.inventioWellcomeEmployee', function () {
			if (this.files && this.files.length) {
				clearEmployeeLibraryUi();
			}
		});
	}

	$(function () {
		bindPresetSelect();
		bindPick();
		bindEmployeePhoto();
		bindTeamBodyFields();
		var $sel = $('#inventio-active-preset');
		if ($sel.length) {
			applyPresetKey($sel.val());
		} else {
			refreshAllTeamBodyStatus();
		}
	});
})(jQuery);
