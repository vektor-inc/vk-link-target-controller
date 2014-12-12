jQuery(document).ready(function(){

	//get a list of ids with ajax
	jQuery.post(
		vkLtc.ajaxurl,
		{
			action : 'ids',
		},
		function( ids ) {
			console.log(ids);
			/*
			if( ids.length > 0 ){
				jQuery( ids ).each(function(){
					var id = jQuery( this + 0 );
					console.log(typeof id);
					//jQuery('#post-' + id).css( "border", "3px solid red" );
				});
			}
			*/
		}
	);

});