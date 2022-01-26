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
				try{ // 例外エラーが発生しるかもしれない処理
					ls.forEach(function(l){
						// c : 対象セレクタ
						var c = $('#post-'+id+' a[href="'+l+'"]');
						// 対象が存在したら
						if(c.length){
							// 対象セレクタの要素に _blank 属性を付与
							$(c).attr('target','_blank');
						}
					});
				}finally{}
			});
		}
	}
);},false);
