document.addEventListener("DOMContentLoaded", function(e) {
	var $ = jQuery;
	const pathToServer = vkLtc.ajaxurl;
	const sendData = { action: 'ids' };
	
	$.post(pathToServer, sendData, function(ps) {
		if (!$.isEmptyObject(ps)) {
			$.each(ps, function(id, ls) {
				// ls
				// [0]リプレースURL
				// [1]変換元URL
				// [2]ターゲット属性
				try{ // 例外エラーが発生するかもしれない処理
					var originalUrl = decodeURIComponent(ls[1]);
					var c = $('.post-' + id + ' a').filter(function() {
						return decodeURIComponent($(this).attr('href')) === originalUrl;
					});

					if (c.length) {
						// リダイレクトURLが空でない場合のみhref属性を更新
						if (ls[0]) {
							$(c).attr('href', ls[0]);
						}

						// ターゲット属性を更新
						if (ls[2] === '1') {
							$(c).attr('target', '_blank');
						} else {
							$(c).attr('target', '_self');
						}

						// targetが_blankである場合にのみrel属性を追加
						if ($(c).attr('target') === '_blank') {
							if (!$(c).attr('rel')) {
								$(c).attr('rel', 'noreferrer noopener');
							}
						} else {
							$(c).removeAttr('rel');
						}
					}
				} finally{
					
				}
			});
		}
	});
}, false);
