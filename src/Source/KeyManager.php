<?php
/**
 * Connection key manager for the Source role.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Source;

defined( 'ABSPATH' ) || exit;

/**
 * Generates, stores (hashed), verifies, and rotates the connection key.
 *
 * The raw key is returned once on generate/rotate and never stored.
 * The hash (wp_hash) and prefix (first 8 chars) are stored in wp_options.
 *
 * Note: stored hashes are keyed to WordPress site salts. Rotating salts
 * (e.g. via `wp-cli secret regenerate`) invalidates all stored keys —
 * regeneration is required.
 */
final class KeyManager {

	private const HASH_OPTION   = 'rms_connection_key_hash';
	private const PREFIX_OPTION = 'rms_connection_key_prefix';

	/**
	 * Generate a new connection key, store hash + prefix, return raw key.
	 *
	 * @return string 64-char hex key (shown once — not stored).
	 */
	public static function generate(): string {
		$key = bin2hex( random_bytes( 32 ) );
		update_option( self::HASH_OPTION, wp_hash( $key ) );
		update_option( self::PREFIX_OPTION, substr( $key, 0, 8 ) );
		return $key;
	}

	/**
	 * Regenerate the key, invalidating the previous one.
	 *
	 * @return string New 64-char hex key.
	 */
	public static function rotate(): string {
		return self::generate();
	}

	/**
	 * Verify a supplied key against the stored hash.
	 *
	 * @param string $key Raw key to verify.
	 * @return bool True if valid.
	 */
	public static function verify( string $key ): bool {
		$stored = (string) get_option( self::HASH_OPTION, '' );
		$dummy  = str_repeat( '0', strlen( wp_hash( $key ) ) );
		return hash_equals( $stored ?: $dummy, wp_hash( $key ) );
	}

	/**
	 * Whether a key has been generated for this site.
	 *
	 * @return bool
	 */
	public static function has_key(): bool {
		return (bool) get_option( self::HASH_OPTION, '' );
	}

	/**
	 * Return the stored 8-char prefix (safe to display in UI).
	 *
	 * @return string
	 */
	public static function get_prefix(): string {
		return (string) get_option( self::PREFIX_OPTION, '' );
	}
}
