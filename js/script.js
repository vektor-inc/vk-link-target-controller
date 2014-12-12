jQuery(document).ready(function(){

	//get a list of ids with ajax
	jQuery.post(
		vkLtc.ajaxurl,
		{
			action : 'ids',
		},
		function( ids ) {
			if(!jQuery.isEmptyObject(ids)){
				jQuery.each(ids, function(id, link) {
					 var candidate = jQuery('#post-'+id+' a[href="'+link+'"]');
					 if(candidate.length){
					 	jQuery(candidate).attr('target','_blank');
					 };
				});
			}
		}
	);

});