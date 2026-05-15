(function () {
	'use strict';

	function initSearch() {
		var input = document.getElementById('robot-food-search');
		if (!input) return;
		var sections = document.querySelectorAll('.robot-food-section');
		input.addEventListener('input', function () {
			var q = this.value.trim().toLowerCase();
			sections.forEach(function (section) {
				if (!q) {
					section.classList.remove('robot-food-hidden');
					return;
				}
				var sectionKeywords = (section.getAttribute('data-keywords') || '').toLowerCase();
				var rows = section.querySelectorAll('tr[data-keywords]');
				var sectionMatch = sectionKeywords.indexOf(q) !== -1;
				var rowMatch = false;
				rows.forEach(function (row) {
					var rowKeywords = (row.getAttribute('data-keywords') || '').toLowerCase();
					var label = row.textContent.toLowerCase();
					if (rowKeywords.indexOf(q) !== -1 || label.indexOf(q) !== -1) {
						rowMatch = true;
					}
				});
				if (sectionMatch || rowMatch) {
					section.classList.remove('robot-food-hidden');
				} else {
					section.classList.add('robot-food-hidden');
				}
			});
		});
	}

	function initMediaPickers() {
		var frame;
		function openPicker(inputId, previewId, removeBtn) {
			if (frame) {
				frame.off('select');
			}
			frame = wp.media({
				title: robotFoodL10n.selectImage,
				button: { text: robotFoodL10n.useImage },
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var input = document.getElementById(inputId);
				var preview = document.getElementById(previewId);
				if (input) input.value = attachment.id;
				if (preview) {
					var img = preview.querySelector('img');
					if (!img) {
						img = document.createElement('img');
						preview.appendChild(img);
					}
					img.src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
					img.alt = '';
				}
				if (removeBtn) removeBtn.classList.remove('hidden');
			});
			frame.open();
		}
		document.querySelectorAll('.robot-food-image-picker').forEach(function (picker) {
			var hiddenInput = picker.querySelector('input[type="hidden"]');
			if (!hiddenInput) return;
			var inputId = hiddenInput.id;
			var preview = picker.querySelector('.robot-food-image-preview');
			var previewId = preview ? preview.id : null;
			var selectBtn = picker.querySelector('.button:not(.robot-food-image-remove)');
			var removeBtn = picker.querySelector('.robot-food-image-remove');
			if (selectBtn) {
				selectBtn.addEventListener('click', function () {
					openPicker(inputId, previewId, removeBtn);
				});
			}
			if (removeBtn) {
				removeBtn.addEventListener('click', function () {
					hiddenInput.value = '';
					if (preview) preview.innerHTML = '';
					removeBtn.classList.add('hidden');
				});
			}
		});
		var metaSelect = document.getElementById('robot_food_og_image_select');
		var metaRemove = document.getElementById('robot_food_og_image_remove');
		if (metaSelect) {
			metaSelect.addEventListener('click', function () {
				openPicker('robot_food_og_image', 'robot_food_og_image_preview', metaRemove);
			});
		}
		if (metaRemove) {
			metaRemove.addEventListener('click', function () {
				var input = document.getElementById('robot_food_og_image');
				var preview = document.getElementById('robot_food_og_image_preview');
				if (input) input.value = '';
				if (preview) preview.innerHTML = '';
				metaRemove.classList.add('hidden');
			});
		}
	}

	function initRedirectRows() {
		var container = document.querySelector('.robot-food-redirects');
		if (!container) return;
		var fromLabel = container.getAttribute('data-from-label') || 'From URL';
		var toLabel = container.getAttribute('data-to-label') || 'To URL';
		function rowIsEmpty(row) {
			var inputs = row.querySelectorAll('input');
			var empty = true;
			inputs.forEach(function (input) {
				if (input.value.trim()) empty = false;
			});
			return empty;
		}
		function reindex() {
			container.querySelectorAll('.robot-food-redirect-row').forEach(function (row, i) {
				row.querySelectorAll('input').forEach(function (input) {
					var key = input.name.indexOf('[from]') !== -1 ? 'from' : 'to';
					input.name = 'robot_food[htaccess_redirects][' + i + '][' + key + ']';
				});
			});
		}
		function removeEmptyTrailingRows() {
			var rows = container.querySelectorAll('.robot-food-redirect-row');
			for (var i = rows.length - 1; i > 0; i--) {
				if (rowIsEmpty(rows[i])) {
					rows[i].remove();
				} else {
					break;
				}
			}
			reindex();
		}
		function addRowIfNeeded() {
			var rows = container.querySelectorAll('.robot-food-redirect-row');
			var lastRow = rows[rows.length - 1];
			if (!lastRow) return;
			if (!rowIsEmpty(lastRow)) {
				var index = rows.length;
				var newRow = document.createElement('div');
				newRow.className = 'robot-food-redirect-row';
				var fromInput = document.createElement('input');
				fromInput.type = 'url';
				fromInput.name = 'robot_food[htaccess_redirects][' + index + '][from]';
				fromInput.placeholder = fromLabel;
				fromInput.className = 'regular-text';
				var toInput = document.createElement('input');
				toInput.type = 'url';
				toInput.name = 'robot_food[htaccess_redirects][' + index + '][to]';
				toInput.placeholder = toLabel;
				toInput.className = 'regular-text';
				newRow.appendChild( fromInput );
				newRow.appendChild( toInput );
				container.appendChild(newRow);
				newRow.querySelectorAll('input').forEach(function (input) {
					input.addEventListener('input', handleInput);
				});
			}
		}
		function handleInput() {
			removeEmptyTrailingRows();
			addRowIfNeeded();
		}
		container.querySelectorAll('.robot-food-redirect-row input').forEach(function (input) {
			input.addEventListener('input', handleInput);
		});
	}

	function initDismissNotice() {
		if (typeof robotFoodNotice === 'undefined') return;
		var notice = document.getElementById('robot-food-notice-discouraged');
		if (!notice) return;
		notice.addEventListener('click', function (e) {
			if (e.target.classList.contains('notice-dismiss')) {
				fetch(robotFoodNotice.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=robot_food_dismiss_notice&nonce=' + robotFoodNotice.nonce
				});
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initSearch();
		initRedirectRows();
		initDismissNotice();
		if (typeof wp !== 'undefined' && wp.media) {
			initMediaPickers();
		}
	});
}());