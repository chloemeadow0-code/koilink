/**
 * Koilink 前端交互：点赞 + 发布 + 换头像
 */
(function () {
	var D = window.KoilinkData || {};

	function needLogin() {
		if (!D.logged) {
			if (D.loginurl) location.href = D.loginurl;
			return true;
		}
		return false;
	}

	/* 点赞 */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.like-btn');
		if (!btn) return;
		if (needLogin()) return;
		fetch(D.ajax + '?action=koilink_like', {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams({ nonce: D.like_nonce, post_id: btn.getAttribute('data-post') })
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.success) {
					btn.classList.toggle('liked', !!j.data.state);
					var c = btn.querySelector('.like-count');
					if (c) c.textContent = j.data.count;
				}
			})
			.catch(function () {});
	});

	/* 换头像 */
	var avatarInput = document.getElementById('me-avatar-input');
	if (avatarInput) {
		avatarInput.addEventListener('change', function () {
			if (!avatarInput.files || !avatarInput.files[0]) return;
			if (needLogin()) return;
			var fd = new FormData();
			fd.append('nonce', D.avatar_nonce);
			fd.append('avatar', avatarInput.files[0]);
			fetch(D.ajax + '?action=koilink_avatar', {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			})
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (j && j.success) location.reload();
					else alert((j && j.data && j.data.msg) || '上传失败');
				})
				.catch(function () { alert('网络错误'); });
		});
	}

	/* 发布 */
	var form = document.getElementById('koilink-publish');
	if (!form) return;

	var input = document.getElementById('pub-files');
	var preview = document.getElementById('pub-preview');
	var files = [];

	input.addEventListener('change', function () {
		files = Array.prototype.slice.call(input.files || []).slice(0, 9);
		preview.innerHTML = '';
		files.forEach(function (f) {
			var img = document.createElement('img');
			img.src = URL.createObjectURL(f);
			preview.appendChild(img);
		});
	});

	form.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var tip = document.getElementById('pub-tip');
		var btn = form.querySelector('.pub-submit');
		tip.textContent = '发布中…';
		btn.disabled = true;

		var fd = new FormData();
		fd.append('nonce', D.publish_nonce);
		fd.append('caption', document.getElementById('pub-caption').value);
		files.forEach(function (f) { fd.append('files[]', f); });

		fetch(D.ajax + '?action=koilink_publish', {
			method: 'POST',
			credentials: 'same-origin',
			body: fd
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.success) {
					location.href = j.data.link;
				} else {
					tip.textContent = (j && j.data && j.data.msg) || '发布失败，请重试';
					btn.disabled = false;
				}
			})
			.catch(function () {
				tip.textContent = '网络错误，请重试';
				btn.disabled = false;
			});
	});
})();
