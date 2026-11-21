<?php
/**
 * Who manages a site's library: on a network, every site's administrators manage their own site's
 * library patterns, unless network administrators turn that off; then only network administrators do (`Content::grant()`). The switch sits on
 * core's Network Settings screen (core first: no screen of our own). On a single site, its administrators.
 *
 * @package Windrose\Knotboard
 */

declare( strict_types=1 );

namespace Windrose\Knotboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The network switch and the rights that follow from it.
 */
final class Network {

	public const OPTION = 'windrose_knotboard_network';
	public const FIELD  = 'windrose_knotboard_site_admins';

	/**
	 * Hooks the switch into core's Network Settings screen.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wpmu_options', array( __CLASS__, 'field' ) );
		add_action( 'update_wpmu_options', array( __CLASS__, 'save' ) );
	}

	/**
	 * Whether site administrators may offer their site's patterns (default yes).
	 *
	 * @return bool
	 */
	public static function site_admins(): bool {
		$value = get_site_option( self::OPTION, array() );
		return ! is_array( $value ) || ! array_key_exists( 'site_admins', $value ) || ! empty( $value['site_admins'] );
	}

	/**
	 * The field on Network Settings (core's form and nonce).
	 *
	 * @return void
	 */
	public static function field(): void {
		?>
		<h2><?php esc_html_e( 'Pattern library', 'windrose-knotboard' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Site administrators', 'windrose-knotboard' ); ?></th>
				<td>
					<label for="<?php echo esc_attr( self::FIELD ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( self::FIELD ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>" value="1" <?php checked( self::site_admins() ); ?> />
						<?php esc_html_e( 'Site administrators manage their site’s pattern library', 'windrose-knotboard' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When this is off, only network administrators do (Windrose Knotboard).', 'windrose-knotboard' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the field (core checked the nonce and the right to manage network options before this action).
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core's Network Settings form: check_admin_referer( 'siteoptions' ) ran before update_wpmu_options.
		$on = ! empty( $_POST[ self::FIELD ] );
		update_site_option( self::OPTION, array( 'site_admins' => $on ) );
	}
}
