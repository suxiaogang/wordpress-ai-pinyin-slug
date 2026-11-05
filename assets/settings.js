(function ($) {
	'use strict';

	$(function () {
		var button = document.getElementById('aips-test-connection');
		if (!button || !window.AIPS_SETTINGS) {
			return;
		}

		var cfg = window.AIPS_SETTINGS;
		var result = document.getElementById('aips-test-result');
		var spinner = document.getElementById('aips-test-spinner');

		button.addEventListener('click', function () {
			button.disabled = true;
			if (spinner) {
				spinner.classList.add('is-active');
			}
			if (result) {
				result.style.display = 'none';
				result.className = 'notice inline';
				result.textContent = '';
			}

			$.ajax({
				url: cfg.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: cfg.action,
					nonce: cfg.nonce,
					api_base: $('input[name="aips_settings[api_base]"]').val() || '',
					api_key: $('input[name="aips_settings[api_key]"]').val() || '',
					model: $('input[name="aips_settings[model]"]').val() || ''
				}
			}).done(function (response) {
				if (!result) {
					return;
				}

				if (response && response.success && response.data && response.data.message) {
					result.className = 'notice inline notice-success';
					result.textContent = response.data.message;
				} else {
					result.className = 'notice inline notice-error';
					result.textContent = response && response.data && response.data.message
						? response.data.message
						: '测试失败，请检查配置。';
				}

				result.style.display = 'block';
			}).fail(function (xhr) {
				if (!result) {
					return;
				}

				result.className = 'notice inline notice-error';
				result.textContent = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: '请求失败，请检查网络或接口配置。';
				result.style.display = 'block';
			}).always(function () {
				button.disabled = false;
				if (spinner) {
					spinner.classList.remove('is-active');
				}
			});
		});
	});
}(jQuery));
