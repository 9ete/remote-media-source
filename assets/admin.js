/* global rmsAdmin, jQuery */
( function ( $ ) {
	'use strict';

	// Role selector: toggle source / consumer sections.
	$( '#rms_role' ).on( 'change', function () {
		var role = $( this ).val();
		$( '#rms-source-settings' ).toggle( role === 'source' );
		$( '#rms-consumer-settings' ).toggle( role === 'consumer' );
	} );

	// Generate key (first time) or Regenerate key.
	$( document ).on( 'click', '#rms-generate-key, #rms-regenerate-key', function ( e ) {
		e.preventDefault();
		if ( $( this ).is( '#rms-regenerate-key' ) ) {
			if ( ! window.confirm( rmsAdmin.i18n.regenerateConfirm ) ) {
				return;
			}
		}
		$.post(
			rmsAdmin.ajaxUrl,
			{
				action: 'rms_generate_key',
				nonce: rmsAdmin.nonces.generateKey,
			},
			function ( response ) {
				if ( ! response.success ) {
					window.alert( response.data.message );
					return;
				}
				$( '#rms-key-modal-value' ).val( response.data.key );
				$( '#rms-key-modal' ).show();
			}
		).fail( function () {
			window.alert( rmsAdmin.i18n.requestFailed );
		} );
	} );

	// Copy key from modal.
	$( '#rms-key-modal-copy' ).on( 'click', function () {
		var $btn = $( this );
		var key  = $( '#rms-key-modal-value' ).val();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( key ).then( function () {
				$btn.text( rmsAdmin.i18n.copySuccess );
				setTimeout( function () {
					$btn.text( rmsAdmin.i18n.copyKey );
				}, 2000 );
			} );
		} else {
			window.prompt( rmsAdmin.i18n.copyFail, key );
		}
	} );

	// Dismiss modal — reload so the prefix display updates.
	$( '#rms-key-modal-dismiss' ).on( 'click', function () {
		$( '#rms-key-modal' ).hide();
		window.location.reload();
	} );

	// Reveal / hide the consumer connection key field.
	$( '#rms-reveal-key' ).on( 'click', function () {
		var $field  = $( '#rms_remote_key' );
		var isPass  = $field.attr( 'type' ) === 'password';
		$field.attr( 'type', isPass ? 'text' : 'password' );
		$( this ).text( isPass ? rmsAdmin.i18n.hide : rmsAdmin.i18n.reveal );
	} );

	// Test connection.
	$( '#rms-test-connection' ).on( 'click', function () {
		var $btn    = $( this );
		var $result = $( '#rms-test-result' );

		$btn.prop( 'disabled', true ).text( rmsAdmin.i18n.testing );
		$result.text( '' ).css( 'color', '' );

		$.post(
			rmsAdmin.ajaxUrl,
			{
				action: 'rms_test_connection',
				nonce: rmsAdmin.nonces.testConnection,
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( rmsAdmin.i18n.testConnection );
				if ( response.success ) {
					$result.css( 'color', '#00a32a' ).text( '✓ ' + response.data.message );
				} else {
					$result.css( 'color', '#d63638' ).text( '✗ ' + response.data.message );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( rmsAdmin.i18n.testConnection );
			$result.css( 'color', '#d63638' ).text( '✗ ' + rmsAdmin.i18n.requestFailed );
		} );
	} );

} )( jQuery );
