document.addEventListener("DOMContentLoaded", function() {
	var $ = jQuery;
	const pathToServer = vkLtc.ajaxurl;
	const sendData = { action: 'ids' };

	const decodeUri = ( url ) => {

		// 文字列内に "%XX" の形式が存在するかどうかを正規表現でチェック
		if (/%[0-9a-fA-F]{2}/.test(url)) {
			// エンコードされていると判断された場合はデコードして返す
			try {
				return decodeURIComponent(url);
			} catch (error) {
				return url;
			}
		}
		// エンコードされていないと判断された場合はそのまま返す
		return url;
		
	}
	
	$.post(pathToServer, sendData, function(ps) {
		if (typeof ps === 'string') {
			try {
				ps = JSON.parse(ps);
			} catch (e) {
				return;
			}
		}
		if (!ps || typeof ps !== 'object' || $.isEmptyObject(ps)) return;
		$.each(ps, function(id, ls) {
			// ls: { re: リダイレクトURL, pl: パーマリンク, tg: ターゲット(0|1) }
			if (!ls || typeof ls !== 'object') return;
			try {
				var redirectUrl = ls.re || '';
				var permalinkUrl = ls.pl || '';
				if (!redirectUrl && !permalinkUrl) return;
				var decodedRedirect = decodeUri(redirectUrl);
				var decodedPermalink = decodeUri(permalinkUrl);
				var targetBlank = Number(ls.tg) === 1;
				// re または pl のいずれかにマッチするリンクを検索（テーマによって出力が異なる）
				var c = $('.post-' + id + ' a').filter(function() {
					var href = decodeUri($(this).attr('href'));
					return href === decodedRedirect || href === decodedPermalink;
				});

				if (c.length) {
					if (redirectUrl) {
						$(c).attr('href', redirectUrl);
					}
					$(c).attr('target', targetBlank ? '_blank' : '_self');
					if (targetBlank) {
						$(c).each(function() {
							var rel = ($(this).attr('rel') || '')
								.split(/\s+/)
								.filter(Boolean);
							if (rel.indexOf('noreferrer') === -1) rel.push('noreferrer');
							if (rel.indexOf('noopener') === -1) rel.push('noopener');
							$(this).attr('rel', rel.join(' '));
						});
					} else {
						$(c).each(function() {
							var rel = ($(this).attr('rel') || '')
								.split(/\s+/)
								.filter(Boolean)
								.filter(function(v) { return v !== 'noreferrer' && v !== 'noopener'; })
								.join(' ');
							if (rel) {
								$(this).attr('rel', rel);
							} else {
								$(this).removeAttr('rel');
							}
						});
					}

					// リダイレクト設定済み投稿の編集リンクを追加（ログイン済みかつ編集権限がある場合）
					// el フィールドはPHP側でget_edit_post_link()により生成され、
					// 権限がないユーザーや非ログインユーザーには空文字が返る。
					var editUrl = ls.el || '';
					if (editUrl) {
						c.each(function() {
							// img要素を含むリンク（アイキャッチ画像リンク等）はスキップ
							// テキスト判定ではなくimg判定にする理由：
							// VK Blocks Pro等では画像リンク内にカテゴリラベルspanが含まれており、
							// テキスト空チェックではスキップできないため。
							if ($(this).find('img').length > 0) {
								return;
							}
							// 既に編集リンクが追加済みの場合はスキップ
							if ($(this).next('.vk-ltc-edit-link').length > 0) {
								return;
							}
							var editLabel = (vkLtc.editLabel || 'Edit');
							var $editLink = $('<a>')
								.addClass('vk-ltc-edit-link')
								.attr('href', editUrl)
								.css({
									'font-size': '0.75em',
									'margin-left': '0.5em',
									'white-space': 'nowrap',
								})
								.text('[' + editLabel + ']');
							$(this).after($editLink);
							// 最初のテキストリンクにのみ挿入し、同じURLを持つ「続きを読む」等
							// 他のリンクへの重複挿入を防ぐためにループを抜ける。
							return false;
						});
					}
				}
			} catch (e) {}
		});
	});
}, false);
