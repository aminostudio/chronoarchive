/**
 * ChronoArchive admin: build and copy shortcodes without inline JavaScript.
 * Only loaded on the plugin's own WordPress settings screen.
 */
(function () {
	'use strict';

	const root = document.querySelector('.chronoarchive-admin');
	if (!root) {
		return;
	}

	const builder = root.querySelector('#chronoarchive-builder');
	const output = root.querySelector('#chronoarchive-generated');
	const copyStatus = root.querySelector('#chronoarchive-copy-status');
	const supportedTypes = ['yearly', 'monthly', 'weekly', 'daily', 'decade', 'alpha', 'postbypost'];

	function field(name) {
		return builder ? builder.querySelector('[data-chrono-field="' + name + '"]') : null;
	}

	function safeString(value) {
		return String(value || '').trim()
			.replace(/[\u0000-\u001f\[\]"\\<>]/g, '')
			.slice(0, 100);
	}

	function generate() {
		if (!builder || !output) {
			return;
		}
		const type = supportedTypes.includes(field('type').value) ? field('type').value : 'yearly';
		const format = field('format').value === 'option' ? 'option' : 'html';
		const order = field('order').value === 'ASC' ? 'ASC' : 'DESC';
		const number = Number(field('limit').value);
		const limit = Number.isInteger(number) && number >= 0 ? Math.min(number, 500) : 0;
		const postType = safeString(field('post_type').value).replace(/[^a-z0-9_-]/gi, '') || 'post';
		const count = field('count').checked ? '1' : '0';

		// Emit all global defaults explicitly. The generated shortcode is
		// portable and reproducible even if site-level defaults change later.
		const args = [
			'type="' + type + '"',
			'format="' + format + '"',
			'order="' + order + '"',
			'limit="' + limit + '"',
			'show_post_count="' + count + '"',
			'post_type="' + postType + '"'
		];
		if (type === 'alpha') {
			args.push('alpha_order="' + order + '"');
		}
		if (type === 'postbypost') {
			args.push('post_order="' + order + '"');
		}
		const placeholderGroup = root.querySelector('#chronoarchive-builder-placeholder-group');
		if (placeholderGroup) {
			placeholderGroup.hidden = format !== 'option';
		}
		if (format === 'option') {
			const label = safeString(field('select_text').value);
			if (label) {
				args.push('select_text="' + label + '"');
			}
		}
		output.textContent = '[chronoarchive ' + args.join(' ') + ']';
	}

	function fallbackCopy(value) {
		const temporary = document.createElement('textarea');
		temporary.value = value;
		temporary.setAttribute('readonly', '');
		temporary.style.position = 'fixed';
		temporary.style.left = '-9999px';
		document.body.appendChild(temporary);
		temporary.select();
		const success = document.execCommand('copy');
		temporary.remove();
		return success;
	}

	async function copyText(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			try {
				await navigator.clipboard.writeText(text);
				return true;
			} catch (error) {
				// Permission restrictions are handled with a manual fallback.
			}
		}
		return fallbackCopy(text);
	}

	if (builder) {
		builder.addEventListener('input', generate);
		builder.addEventListener('change', generate);
		generate();
	}
	root.addEventListener('click', async function (event) {
		const button = event.target.closest('[data-chrono-copy]');
		if (!button || !root.contains(button)) {
			return;
		}
		const target = root.querySelector('#' + button.getAttribute('data-chrono-copy'));
		if (!target) {
			return;
		}
		const success = await copyText(target.textContent.trim());
		if (copyStatus) {
			copyStatus.textContent = success
				? button.getAttribute('data-chrono-copied') || 'Shortcode copied'
				: button.getAttribute('data-chrono-copy-failed') || 'Select the shortcode and copy it manually';
		}
	});
}());
