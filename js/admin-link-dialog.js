/**
 * WordPress link dialog integration for VK Link Target Controller.
 * Opens the standard link modal to search and select internal links.
 */
( function( $ ) {
	'use strict';

	var LINK_FIELD_ID = 'vk-ltc-link-field';
	var TARGET_CHECK_ID = 'vk-ltc-target-check';

	/**
	 * Open wpLink for our metabox context.
	 */
	function openLinkDialog() {
		if ( typeof window.wpLink === 'undefined' ) {
			return;
		}
		window.vkLtcLinkMode = true;
		var currentUrl = $( '#' + LINK_FIELD_ID ).val() || '';
		window.wpLink.open( LINK_FIELD_ID, currentUrl, '' );
	}

	/**
	 * Intercept wp-link-submit when opened from our metabox.
	 */
	function init() {
		$( document ).on( 'click', '.vk-ltc-link-search-btn', function( e ) {
			e.preventDefault();
			openLinkDialog();
			return false;
		} );

		// Capture phase to run before wpLink's handler.
		document.addEventListener( 'click', function( e ) {
			if ( ! window.vkLtcLinkMode || ! e.target || e.target.id !== 'wp-link-submit' ) {
				return;
			}
			if ( typeof window.wpLink === 'undefined' ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();

			var attrs = window.wpLink.getAttrs();
			if ( attrs.href ) {
				$( '#' + LINK_FIELD_ID ).val( attrs.href );
				$( '#' + TARGET_CHECK_ID ).prop( 'checked', attrs.target === '_blank' );
			}

			window.wpLink.textarea = $( 'body' ).get( 0 );
			window.wpLink.close( 'noReset' );
			window.vkLtcLinkMode = false;
			return false;
		}, true );

		// Reset flag when dialog is closed by cancel/backdrop.
		$( document ).on( 'wplink-close', function() {
			window.vkLtcLinkMode = false;
		} );
	}

	$( init );
} )( jQuery );
