document.addEventListener("DOMContentLoaded",function(e){
	var $=jQuery;
	// .post でサーバーへ通信する
	const pathToServer = vkLtc.ajaxurl;
	const sendData = {action : 'ids',};
	$.post(
		pathToServer,
		sendData,
		function(ps) {
			// ps : 対象の投稿データっぽい
			if(!$.isEmptyObject(ps)){
				$.each(ps, function(id, ls) {
					// ls
					// [0]リプレースURL
					// [1]変換元URL
					try{ // 例外エラーが発生するかもしれない処理
						// 変換元URLで一致する要素を取得
						var c = $('.post-'+id+' a[href="'+ls[1]+'"]');
						// 対象が存在したら
						if(c.length){
							// href属性を置換後のURLに変更し、_blank属性とセキュリティ属性を付与
							$(c).attr('href', ls[0]).attr('target','_blank').attr('rel','noreferrer noopener');
						}
					} finally {}
				});
			}
		}
	);
},false);
