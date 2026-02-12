document.addEventListener("DOMContentLoaded", function(e) {
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
		if (!ps || typeof ps !== 'object') return;
		if (!$.isEmptyObject(ps)) {
			$.each(ps, function(id, ls) {
				// ls: { re: リダイレクトURL, pl: パーマリンク, tg: ターゲット(0|1) }
				if (!ls || typeof ls !== 'object') return;
				try {
					var redirectUrl = ls.re || '';
					var permalinkUrl = ls.pl || '';
					var targetBlank = ls.tg === 1;
					// re または pl のいずれかにマッチするリンクを検索（テーマによって出力が異なる）
					var c = $('.post-' + id + ' a').filter(function() {
						var href = decodeUri($(this).attr('href'));
						return href === decodeUri(redirectUrl) || href === decodeUri(permalinkUrl);
					});

					if (c.length) {
						if (redirectUrl) {
							$(c).attr('href', redirectUrl);
						}
						$(c).attr('target', targetBlank ? '_blank' : '_self');
						if (targetBlank) {
							if (!$(c).attr('rel')) {
								$(c).attr('rel', 'noreferrer noopener');
							}
						} else {
							$(c).removeAttr('rel');
						}
					}
				} catch (e) {}
			});
		}
	});
}, false);
