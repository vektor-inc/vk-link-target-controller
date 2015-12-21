jQuery(document).ready(function(){

	//get a list of posts that need target _blank with ajax
	jQuery.post(
		vkLtc.ajaxurl,
		{
			action : 'ids'
		},
		function( posts ) {
			if(!jQuery.isEmptyObject(posts)){
				jQuery.each(posts, function(id, link) {
					var candidate = jQuery('#post-'+id+' a[href="'+link+'"]');
					if(candidate.length){
						jQuery(candidate).attr('target','_blank');
					}
				});
			}
		}
	);

});
