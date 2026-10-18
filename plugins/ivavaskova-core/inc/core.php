<?php
/**
 * Přihlášení, info o serveru, automatické aktualizace, hardening a updater pluginu z GitHubu.
 */

defined( 'ABSPATH' ) || exit;


/* =========================================================================
 * 1) Omezení pokusů o přihlášení (nahrazuje advanced-nocaptcha-recaptcha)
 *    5 chybných pokusů za 15 min z jedné IP → blokace na 30 min.
 * ====================================================================== */

const IV_LOGIN_MAX_FAILS = 5;
const IV_LOGIN_WINDOW    = 15 * MINUTE_IN_SECONDS;
const IV_LOGIN_LOCKOUT   = 30 * MINUTE_IN_SECONDS;

function iv_client_ip(): string {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

add_filter( 'authenticate', function ( $user, $username ) {
	if ( get_transient( 'iv_lock_' . md5( iv_client_ip() ) ) ) {
		return new WP_Error( 'iv_locked', 'Příliš mnoho neúspěšných pokusů. Zkuste to znovu za 30 minut.' );
	}
	return $user;
}, 30, 2 );

add_action( 'wp_login_failed', function ( $username ) {
	$ip        = iv_client_ip();
	$fails_key = 'iv_fails_' . md5( $ip );
	$fails     = (int) get_transient( $fails_key ) + 1;
	set_transient( $fails_key, $fails, IV_LOGIN_WINDOW );
	if ( $fails >= IV_LOGIN_MAX_FAILS ) {
		set_transient( 'iv_lock_' . md5( $ip ), 1, IV_LOGIN_LOCKOUT );
		delete_transient( $fails_key );
	}
	iv_login_log_add( (string) $username, false );
} );

// Chybová hláška neprozradí, jestli uživatel existuje.
add_filter( 'login_errors', function ( $error ) {
	return str_contains( (string) $error, 'Příliš mnoho' ) ? $error : '<strong>Chyba:</strong> nesprávné přihlašovací údaje.';
} );

/* =========================================================================
 * 2) Log přihlášení + poslední přihlášení (nahrazuje simple-login-log)
 * ====================================================================== */

const IV_LOGIN_LOG_OPTION = 'iv_login_log';
const IV_LOGIN_LOG_MAX    = 200;

function iv_login_log_add( string $username, bool $success ): void {
	$log = get_option( IV_LOGIN_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array(
		't'  => time(),
		'u'  => mb_substr( sanitize_user( $username ), 0, 60 ),
		'ok' => $success,
		'ip' => iv_client_ip(),
		'ua' => mb_substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 160 ),
	) );
	update_option( IV_LOGIN_LOG_OPTION, array_slice( $log, 0, IV_LOGIN_LOG_MAX ), false );
}

add_action( 'wp_login', function ( $login, $user ) {
	update_user_meta( $user->ID, 'iv_last_login', time() );
	delete_transient( 'iv_fails_' . md5( iv_client_ip() ) );
	iv_login_log_add( (string) $login, true );
}, 10, 2 );

add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['iv_last_login'] = 'Poslední přihlášení';
	return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $out, $col, $user_id ) {
	if ( 'iv_last_login' !== $col ) {
		return $out;
	}
	$t = (int) get_user_meta( $user_id, 'iv_last_login', true );
	return $t ? esc_html( wp_date( 'j. n. Y H:i', $t ) ) : '—';
}, 10, 3 );

add_action( 'admin_menu', function () {
	add_management_page( 'Log přihlášení', 'Log přihlášení', 'manage_options', 'iv-login-log', function () {
		$log = get_option( IV_LOGIN_LOG_OPTION, array() );
		echo '<div class="wrap"><h1>Log přihlášení</h1><p>Posledních ' . (int) IV_LOGIN_LOG_MAX . ' pokusů o přihlášení.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Čas</th><th>Uživatel</th><th>Výsledek</th><th>IP</th><th>Prohlížeč</th></tr></thead><tbody>';
		if ( ! $log ) {
			echo '<tr><td colspan="5">Zatím prázdné.</td></tr>';
		}
		foreach ( (array) $log as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><small>%s</small></td></tr>',
				esc_html( wp_date( 'j. n. Y H:i:s', (int) $row['t'] ) ),
				esc_html( $row['u'] ),
				$row['ok'] ? '✅ úspěch' : '❌ chyba',
				esc_html( $row['ip'] ),
				esc_html( $row['ua'] )
			);
		}
		echo '</tbody></table></div>';
	} );
} );

/* =========================================================================
 * 3) Info o serveru v patičce adminu (nahrazuje server-ip-memory-usage)
 * ====================================================================== */

add_filter( 'admin_footer_text', function ( $text ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $text;
	}
	global $wpdb;
	return sprintf(
		'PHP %s · DB %s · IP %s · paměť %s / %s',
		esc_html( PHP_VERSION ),
		esc_html( $wpdb->db_server_info() ),
		esc_html( $_SERVER['SERVER_ADDR'] ?? '?' ),
		esc_html( size_format( memory_get_peak_usage( true ) ) ),
		esc_html( ini_get( 'memory_limit' ) )
	);
} );

/* =========================================================================
 * 4) Automatické aktualizace (nahrazuje stops-core-theme-and-plugin-updates — vše automaticky)
 * ====================================================================== */

add_filter( 'allow_major_auto_core_updates', '__return_true' );
add_filter( 'allow_minor_auto_core_updates', '__return_true' );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	// Iva Vašková Core se aktualizuje ručně z GitHub Releases (Nástěnka → Aktualizace).
	return ( isset( $item->plugin ) && IV_CORE_BASENAME === $item->plugin ) ? false : true;
}, 10, 2 );
add_filter( 'auto_update_theme', function ( $update, $item ) {
	// Vlastní šablonu „ivavaskova“ nikdy neaktualizovat z wordpress.org.
	return ( isset( $item->theme ) && 'ivavaskova' === $item->theme ) ? false : true;
}, 10, 2 );
add_filter( 'auto_update_translation', '__return_true' );

// Lokální vývoj: žádné aktualizace.
if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'automatic_updater_disabled', '__return_true' );
}

/* =========================================================================
 * 5) Hardening
 * ====================================================================== */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' ); // ani system.listMethods
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// Nepřihlášeným neukazovat seznam uživatelů přes REST API.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// ?author=N neprozradí login.
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* =========================================================================
 * 6) Aktualizace pluginu z GitHub Releases (Update URI → filtr update_plugins_github.com)
 *    Release musí obsahovat soubor ivavaskova-core.zip (sestavuje GitHub Action).
 * ====================================================================== */

const IV_CORE_REPO = 'elvisek2020/wp-theme_ivavaskova';

function iv_core_release(): array {
	$release = get_site_transient( 'iv_core_release' );
	if ( is_array( $release ) ) {
		return $release;
	}
	$release  = array();
	$response = wp_remote_get( 'https://api.github.com/repos/' . IV_CORE_REPO . '/releases/latest', array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'ivavaskova-core-updater' ),
	) );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( 'ivavaskova-core.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'vV' ),
					'url'     => (string) $data['html_url'],
					'package' => (string) $asset['browser_download_url'],
					'notes'   => (string) ( $data['body'] ?? '' ),
					'date'    => (string) ( $data['published_at'] ?? '' ),
				);
				break;
			}
		}
	}
	set_site_transient( 'iv_core_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

if ( 'local' !== wp_get_environment_type() ) {
	add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
		if ( IV_CORE_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = iv_core_release();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/' . IV_CORE_REPO,
			'slug'         => 'ivavaskova-core',
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => IV_CORE_TESTED,
			'requires'     => '6.5',
			'requires_php' => '7.4',
		);
	}, 10, 3 );

	// Okno „Zobrazit podrobnosti“ (jinak by WP hledal plugin na wordpress.org).
	add_filter( 'plugins_api', function ( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || 'ivavaskova-core' !== ( $args->slug ?? '' ) ) {
			return $res;
		}
		$release = iv_core_release();
		$notes   = ! empty( $release['notes'] ) ? $release['notes'] : 'Viz CHANGELOG na GitHubu.';
		return (object) array(
			'name'          => 'Iva Vašková Core',
			'slug'          => 'ivavaskova-core',
			'version'       => $release['version'] ?? IV_CORE_VERSION,
			'author'        => 'elvisek',
			'homepage'      => 'https://github.com/' . IV_CORE_REPO,
			'tested'        => IV_CORE_TESTED,
			'requires'      => '6.5',
			'requires_php'  => '7.4',
			'last_updated'  => $release['date'] ?? '',
			'download_link' => $release['package'] ?? '',
			'sections'      => array(
				'description' => 'Funkce webu ivavaskova.cz nezávislé na šabloně: ochrana a log přihlášení, hardening, automatické aktualizace, sitemap, ochrana obsahu, údržba databáze.',
				'changelog'   => '<pre style="white-space:pre-wrap">' . esc_html( $notes ) . '</pre><p><a href="https://github.com/' . IV_CORE_REPO . '/blob/main/CHANGELOG.md" target="_blank">Celý CHANGELOG</a></p>',
			),
		);
	}, 10, 3 );

	add_action( 'load-update-core.php', function () {
		if ( isset( $_GET['force-check'] ) ) {
			delete_site_transient( 'iv_core_release' );
		}
	} );
}
