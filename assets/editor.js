(function ($) {
	'use strict';

	var cfg = window.AIPS_EDITOR || {};
	var messages = cfg.messages || {};

	function message(key, fallback) {
		return messages[key] ? messages[key] : fallback;
	}

	function isBlockEditor() {
		if (!window.wp || !wp.data || !wp.data.select || !wp.data.select('core/editor')) {
			return false;
		}

		return !!(
			document.body.classList.contains('block-editor-page') ||
			wp.data.select('core/edit-post')
		);
	}

	function getEditorContext() {
		if (isBlockEditor()) {
			var editor = wp.data.select('core/editor');
			return {
				id: editor.getCurrentPostId() || 0,
				title: editor.getEditedPostAttribute('title') || '',
				content: editor.getEditedPostContent() || ''
			};
		}

		var idField = document.getElementById('post_ID');
		var titleField = document.getElementById('title');
		var content = '';

		if (window.wp && wp.editor && typeof wp.editor.getContent === 'function') {
			try {
				content = wp.editor.getContent('content') || '';
			} catch (e) {
				content = '';
			}
		}

		if (!content) {
			var contentField = document.getElementById('content');
			content = contentField ? contentField.value : '';
		}

		return {
			id: idField ? parseInt(idField.value, 10) || 0 : 0,
			title: titleField ? titleField.value : '',
			content: content
		};
	}

	function applyToBlockEditor(summary, slug) {
		var patch = {};

		if (summary) {
			patch.excerpt = summary;
		}
		if (slug) {
			patch.slug = slug;
		}
		if (!Object.keys(patch).length) {
			return;
		}

		wp.data.dispatch('core/editor').editPost(patch);
	}

	function applyToClassicEditor(summary, slug) {
		var missing = { summary: false, slug: false };

		if (summary) {
			var excerpt = document.getElementById('excerpt');
			if (excerpt) {
				excerpt.value = summary;
			} else {
				missing.summary = true;
			}
		}

		if (slug) {
			var slugField = document.getElementById('post_name');
			if (slugField) {
				slugField.value = slug;
				$(slugField).trigger('change');

				var shortName = document.getElementById('editable-post-name');
				var fullName = document.getElementById('editable-post-name-full');
				var newSlug = document.getElementById('new-post-slug');
				if (shortName) {
					shortName.textContent = slug;
				}
				if (fullName) {
					fullName.textContent = slug;
				}
				if (newSlug) {
					newSlug.value = slug;
				}
			} else {
				missing.slug = true;
			}
		}

		return missing;
	}

	function setStatus(text, type) {
		var status = document.getElementById('aips-status');
		if (!status) {
			return;
		}

		status.className = 'aips-status aips-status--' + type;
		status.textContent = text;
		status.hidden = false;
	}

	function showResult(summary, slug) {
		var box = document.getElementById('aips-result');
		var summaryField = document.getElementById('aips-summary');
		var slugField = document.getElementById('aips-slug');

		if (summaryField) {
			summaryField.value = summary || '';
		}
		if (slugField) {
			slugField.textContent = slug || '（未生成）';
		}
		if (box) {
			box.hidden = false;
		}
	}

	function showBlockNotice(text, type) {
		if (!isBlockEditor() || !wp.data.dispatch('core/notices')) {
			return;
		}

		var noticeType = 'success';
		if (type === 'error') {
			noticeType = 'error';
		} else if (type === 'warn') {
			noticeType = 'warning';
		}

		try {
			wp.data.dispatch('core/notices').createNotice(noticeType, text, {
				isDismissible: true
			});
		} catch (e) {
			// Notices are optional feedback; the meta box status is authoritative.
		}
	}

	function setBusy(busy) {
		var button = document.getElementById('aips-generate');
		var spinner = document.getElementById('aips-spinner');

		if (button) {
			button.disabled = busy;
			button.textContent = busy
				? message('working', '正在生成，请稍候…')
				: message('button', '生成摘要与拼音 Slug');
		}
		if (spinner) {
			spinner.classList.toggle('is-active', busy);
		}
	}

	function buildNote(summary, slug, missing) {
		if (!isBlockEditor() && missing) {
			if (missing.summary && missing.slug) {
				return message('appliedNoFields', '摘要和拼音 Slug 已生成；当前页面未找到对应输入框，请从上方复制。');
			}
			if (missing.summary) {
				return message('appliedNoExcerpt', '摘要已生成；当前页面没有摘要输入框，请从上方复制摘要。');
			}
			if (missing.slug) {
				return message('appliedNoSlug', '拼音 Slug 已生成；未找到 slug 输入框，请从上方复制拼音 Slug。');
			}
		}
		if (summary && slug) {
			return message('applied', '已填入摘要与拼音 Slug，保存文章后生效。');
		}
		if (summary) {
			return message('appliedSummaryOnly', '已填入摘要。');
		}
		if (slug) {
			return message('appliedSlugOnly', '已填入拼音 Slug。');
		}

		return message('unknown', '生成失败，请重试。');
	}

	function generate() {
		var context = getEditorContext();

		if (!context.id) {
			setStatus(message('noPostId', '未获取到文章 ID，请刷新页面后重试。'), 'error');
			return;
		}
		if (!context.title && !context.content) {
			setStatus(message('empty', '请先填写文章标题或正文。'), 'error');
			return;
		}

		setBusy(true);
		setStatus(message('working', '正在生成，请稍候…'), 'info');

		$.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: cfg.action,
				nonce: cfg.nonce,
				post_id: context.id,
				title: context.title,
				content: context.content
			}
		}).done(function (response) {
			if (!response || !response.success) {
				var errorMessage = response && response.data && response.data.message
					? response.data.message
					: message('unknown', '生成失败，请重试。');

				setStatus(errorMessage, 'error');
				showBlockNotice(errorMessage, 'error');
				return;
			}

			var data = response.data || {};
			var summary = data.summary || '';
			var slug = data.slug || '';
			var warnings = data.warnings || [];
			var missing = null;
			var notes = [];

			showResult(summary, slug);

			if (isBlockEditor()) {
				applyToBlockEditor(summary, slug);
			} else {
				missing = applyToClassicEditor(summary, slug);
			}

			notes.push(buildNote(summary, slug, missing));
			notes = notes.concat(warnings);

			var type = warnings.length ? 'warn' : 'ok';
			var noticeText = notes.join(' ');

			setStatus(noticeText, type);
			showBlockNotice(noticeText, type);
		}).fail(function (xhr) {
			var errorMessage = message('requestFailed', '请求失败，请检查网络或接口配置。');

			if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				errorMessage = xhr.responseJSON.data.message;
			}

			setStatus(errorMessage, 'error');
			showBlockNotice(errorMessage, 'error');
		}).always(function () {
			setBusy(false);
		});
	}

	$(function () {
		if (!cfg.ajaxUrl) {
			return;
		}

		$(document).on('click', '#aips-generate', function (event) {
			event.preventDefault();
			generate();
		});
	});
}(jQuery));
