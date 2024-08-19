document.addEventListener("DOMContentLoaded", function(e) {
	var $ = jQuery;
	// .post でサーバーへ通信する
	const pathToServer = vkLtc.ajaxurl;
	const sendData = { action: 'ids' };
	
	$.post(pathToServer, sendData, function(ps) {
		// ps : 対象の投稿データっぽい
		if (!$.isEmptyObject(ps)) {
			$.each(ps, function(id, ls) {
				// ls
				// [0]リプレースURL
				// [1]変換元URL
				try { // 例外エラーが発生しるかもしれない処理
					var originalUrl = decodeURIComponent(ls[1]);
					var c = $('.post-' + id + ' a').filter(function() {
						return decodeURIComponent($(this).attr('href')) === originalUrl;
					});

					if (c.length) {
						// href属性をリプレースURLに変更
						$(c).attr('href', ls[0]);

						// targetが設定されていない場合は、強制的に target="_self" を追加
						if (!$(c).attr('target')) {
							$(c).attr('target', '_self');
						}

						// targetが_selfでない場合のみ、rel属性を追加
						if ($(c).attr('target') !== '_self') {
							if (!$(c).attr('rel')) {
								$(c).attr('rel', 'noreferrer noopener');
							}
						}
					}
				} finally {}
			});
		}
	});
}, false);
