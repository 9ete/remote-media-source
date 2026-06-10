<?php
/**
 * PSR-4 style autoloader for Remote Media Source.
 *
 * @package RemoteMediaSource
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( string $class_name ): void {
		if ( strpos( $class_name, 'RemoteMediaSource\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( 'RemoteMediaSource\\' ) );
		$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
