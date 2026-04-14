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
						// タイトルリンク候補を特定する。
						// DOM順序に依存しないよう、見出し要素内の非画像リンクを優先的に探す。
						// 見出し内になければ最初の非画像リンクにフォールバックする。
						var $titleLink = c.filter(function() {
							// img要素を含むリンク（アイキャッチ画像リンク等）はスキップ
							// VK Blocks Pro等では画像リンク内にカテゴリラベルspanが含まれており、
							// テキスト空チェックではスキップできないため、img要素の有無で判定する。
							if ($(this).find('img').length > 0) {
								return false;
							}
							// h1〜h6内のリンクをタイトルリンクと判断する
							return $(this).closest('h1,h2,h3,h4,h5,h6').length > 0;
						}).first();

						// 見出し内に見つからない場合は最初の非画像リンクを使用する
						if (!$titleLink.length) {
							$titleLink = c.filter(function() {
								return $(this).find('img').length === 0;
							}).first();
						}

						// 候補が存在し、かつ既に編集リンクが追加されていない場合のみ挿入する
						if ($titleLink.length && $titleLink.next('.vk-ltc-edit-link').length === 0) {
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
							$titleLink.after($editLink);
						}
					}
				}
			} catch (e) {}
		});
	});
}, false);
