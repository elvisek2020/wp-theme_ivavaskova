<?php
/**
 * Aktualizace šablony z GitHub Releases (soubor ivavaskova.zip v posledním vydání).
 * Výsledek dotazu se drží v site transientu: úspěch 6 h, chyba 1 h. Na lokálu vypnuto.
 */

defined( 'ABSPATH' ) || exit;

const IV_THEME_REPO = 'elvisek2020/wp-theme_ivavaskova';

function iv_theme_release(): array {
	$release = get_site_transient( 'iv_theme_release' );
	if ( is_array( $release ) ) {
		return $release;
	}
	$release  = array();
	$response = wp_remote_get( 'https://api.github.com/repos/' . IV_THEME_REPO . '/releases/latest', array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'ivavaskova-theme-updater' ),
	) );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( 'ivavaskova.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'vV' ),
					'url'     => (string) $data['html_url'],
					'package' => (string) $asset['browser_download_url'],
				);
				break;
			}
		}
	}
	set_site_transient( 'iv_theme_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

if ( 'local' !== wp_get_environment_type() ) {
	add_filter( 'pre_set_site_transient_update_themes', function ( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$slug    = get_template();
		$release = 'ivavaskova' === $slug ? iv_theme_release() : array();
		if ( $release && version_compare( $release['version'], IV_VERSION, '>' ) ) {
			$transient->response[ $slug ] = array(
				'theme'        => $slug,
				'new_version'  => $release['version'],
				'url'          => $release['url'],
				'package'      => $release['package'],
				'requires'     => '6.5',
				'requires_php' => '7.4',
			);
		} elseif ( isset( $transient->response[ $slug ] ) && 'ivavaskova' === $slug ) {
			unset( $transient->response[ $slug ] ); // nikdy nenabízet „aktualizaci“ z wordpress.org
		}
		return $transient;
	} );

	add_action( 'load-update-core.php', function () {
		if ( isset( $_GET['force-check'] ) ) {
			delete_site_transient( 'iv_theme_release' );
		}
	} );
}
