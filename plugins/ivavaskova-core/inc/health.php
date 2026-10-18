<?php
/**
 * Widget „Zdraví webu“ na Nástěnce: verze, databáze, obsah, uploads, poslední údržba.
 * Dražší čísla (velikost databáze a uploads) se drží 12 h v transientu, tlačítko Obnovit je přepočítá.
 */

defined( 'ABSPATH' ) || exit;

const IV_HEALTH_TTL = 12 * HOUR_IN_SECONDS;

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'iv_health', 'Zdraví webu', 'iv_health_widget', null, null, 'normal', 'high' );
} );

/** Údržba webu zaznamená čas posledního spuštění akce. */
function iv_health_mark_maintenance(): void {
	update_option( 'iv_maint_last', time(), false );
}

function iv_health_heavy( bool $refresh = false ): array {
	$data = $refresh ? false : get_transient( 'iv_health_heavy' );
	if ( is_array( $data ) ) {
		return $data;
	}
	global $wpdb;
	$db       = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s',
		DB_NAME,
		$wpdb->esc_like( $wpdb->prefix ) . '%'
	) );
	$autoload = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" );
	$uploads  = 0;
	$dir      = wp_get_upload_dir()['basedir'] ?? '';
	if ( $dir && is_dir( $dir ) ) {
		@set_time_limit( 60 );
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() ) {
				$uploads += $f->getSize();
			}
		}
	}
	$data = array( 'db' => $db, 'autoload' => $autoload, 'uploads' => $uploads, 'at' => time() );
	set_transient( 'iv_health_heavy', $data, IV_HEALTH_TTL );
	return $data;
}

function iv_health_size( int $bytes ): string {
	if ( $bytes >= 1073741824 ) {
		return number_format_i18n( $bytes / 1073741824, 2 ) . ' GB';
	}
	return $bytes >= 1048576 ? number_format_i18n( $bytes / 1048576, 1 ) . ' MB' : number_format_i18n( $bytes / 1024, 0 ) . ' kB';
}

function iv_health_widget(): void {
	$refresh = isset( $_GET['iv_health_refresh'] ) && check_admin_referer( 'iv_health_refresh' );
	$heavy   = iv_health_heavy( $refresh );

	// Verze a dostupné aktualizace (z cache WordPressu, nic se tu nestahuje).
	$theme       = wp_get_theme();
	$slug        = $theme->get_stylesheet();
	$upd_themes  = get_site_transient( 'update_themes' );
	$upd_plugins = get_site_transient( 'update_plugins' );
	$theme_new   = is_object( $upd_themes ) ? (string) ( $upd_themes->response[ $slug ]['new_version'] ?? '' ) : '';
	$plugin_obj  = is_object( $upd_plugins ) ? ( $upd_plugins->response[ IV_CORE_BASENAME ] ?? null ) : null;
	$plugin_new  = is_object( $plugin_obj ) ? (string) ( $plugin_obj->new_version ?? '' ) : '';
	$plugins_upd = is_object( $upd_plugins ) ? count( (array) $upd_plugins->response ) - ( $plugin_new ? 1 : 0 ) : 0;
	$wp_upd      = get_core_updates();
	$wp_new      = ( is_array( $wp_upd ) && isset( $wp_upd[0] ) && 'upgrade' === ( $wp_upd[0]->response ?? '' ) ) ? $wp_upd[0]->current : '';

	// Obsah.
	$drafts  = (int) wp_count_posts( 'post' )->draft;
	$pending = (int) wp_count_comments()->moderated;
	$maint   = (int) get_option( 'iv_maint_last' );

	$ok   = '<span style="color:#1a7f37">●</span>';
	$warn = '<span style="color:#bf8700">●</span>';
	$new  = function ( string $v ) use ( $ok, $warn ) {
		return $v ? ' ' . $warn . ' <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">k dispozici ' . esc_html( $v ) . '</a>' : ' ' . $ok;
	};

	$rows = array(
		'Verze'  => array(
			'Šablona ' . $theme->get( 'Name' ) => esc_html( (string) $theme->get( 'Version' ) ) . $new( $theme_new ),
			'Iva Vašková Core'                  => esc_html( IV_CORE_VERSION ) . $new( $plugin_new ),
			'WordPress'                         => esc_html( get_bloginfo( 'version' ) ) . $new( (string) $wp_new ),
			'Ostatní pluginy'                   => $plugins_upd ? sprintf( '%s <a href="%s">%d k aktualizaci</a>', $warn, esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ), $plugins_upd ) : 'aktuální ' . $ok,
			'PHP'                               => esc_html( PHP_VERSION ) . ( version_compare( PHP_VERSION, '8.1', '<' ) ? ' ' . $warn . ' doporučeno 8.1+' : ' ' . $ok ),
		),
		'Data'   => array(
			'Databáze' => esc_html( iv_health_size( $heavy['db'] ) ),
			'Autoload' => esc_html( iv_health_size( $heavy['autoload'] ) ) . ( $heavy['autoload'] > 1048576 ? ' ' . $warn . ' víc než 1 MB' : ' ' . $ok ),
			'Uploads'  => esc_html( iv_health_size( $heavy['uploads'] ) ),
		),
		'Obsah'  => array(
			'Koncepty'               => sprintf( '<a href="%s">%d</a>', esc_url( admin_url( 'edit.php?post_status=draft' ) ), $drafts ),
			'Komentáře ke schválení' => $pending ? sprintf( '%s <a href="%s">%d</a>', $warn, esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ), $pending ) : '0 ' . $ok,
		),
		'Údržba' => array(
			'Naposledy' => $maint ? esc_html( wp_date( 'j. n. Y H:i', $maint ) ) . ' (' . esc_html( human_time_diff( $maint ) ) . ')' : 'zatím nezaznamenáno',
		),
	);

	echo '<table class="widefat striped" style="border:0;box-shadow:none"><tbody>';
	foreach ( $rows as $group => $items ) {
		printf( '<tr><th colspan="2" style="padding-top:10px;font-weight:600">%s</th></tr>', esc_html( $group ) );
		foreach ( $items as $label => $value ) {
			printf( '<tr><td style="width:55%%">%s</td><td>%s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput -- hodnoty escapované výše
		}
	}
	echo '</tbody></table>';
	printf(
		'<p style="display:flex;justify-content:space-between;align-items:center;margin:12px 0 0"><a class="button" href="%s">Údržba webu</a><span style="color:#646970">Velikosti k %s · <a href="%s">Obnovit</a></span></p>',
		esc_url( admin_url( 'tools.php?page=' . IV_MAINT_SLUG ) ),
		esc_html( wp_date( 'j. n. H:i', $heavy['at'] ) ),
		esc_url( wp_nonce_url( admin_url( 'index.php?iv_health_refresh=1' ), 'iv_health_refresh' ) )
	);
}
