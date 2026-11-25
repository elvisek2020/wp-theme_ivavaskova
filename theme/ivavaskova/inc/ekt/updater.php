<?php
/**
 * EKT module: updater
 * Version: 1.0.2
 * Requires: common
 * Replaces: –
 * Assets: –
 * Summary: Aktualizace šablon webu z GitHub Releases (repo z hlavičky Update URI), i neaktivních, bez nabídek z wordpress.org.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Vydání repa EKT_REPO (hlavička Update URI ve style.css) obsahuje za každou šablonu soubor
 * <složka šablony>.zip (sestavuje release.yml). Updater aktivní šablony nabídne aktualizaci
 * každé nainstalované šabloně, jejíž Update URI ukazuje na stejné repo a jejíž ZIP ve vydání je,
 * tedy i neaktivní (záloha, šablona pro Živý náhled). Šablona bez svého ZIPu ve vydání nedostane
 * nic. Soukromé repo: define( 'EKT_GITHUB_TOKEN', '…' ) ve wp-config.php (token jen pro čtení
 * obsahu repa; starší EKC_/EK_/MND_/IV_GITHUB_TOKEN platí dál). Na lokálním vývoji vypnuto,
 * pro test define( 'EKT_UPDATER_ON_LOCAL', true ).
 */

/**
 * Token pro GitHub API (nový, nebo ze starší šablony).
 *
 * @return string
 */
function ekt_updater_token(): string {
	foreach ( array( 'EKT_GITHUB_TOKEN', 'EKC_GITHUB_TOKEN', 'EK_GITHUB_TOKEN', 'MND_GITHUB_TOKEN', 'IV_GITHUB_TOKEN' ) as $name ) {
		if ( defined( $name ) && constant( $name ) ) {
			return (string) constant( $name );
		}
	}
	return '';
}

/**
 * HTTP hlavičky pro GitHub API.
 *
 * @param string $accept Accept.
 * @return array
 */
function ekt_updater_headers( string $accept = 'application/vnd.github+json' ): array {
	$headers = array(
		'Accept'     => $accept,
		'User-Agent' => EKT_SLUG . '-theme-updater',
	);
	$token = ekt_updater_token();
	if ( '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	return $headers;
}

/**
 * Repo owner/repo z hodnoty Update URI (stejně jako EKT_REPO ve functions.php), malými písmeny.
 *
 * @param string $uri Update URI.
 * @return string
 */
function ekt_updater_repo( string $uri ): string {
	return strtolower( trim( (string) preg_replace( '#^https?://github\.com/#i', '', trim( $uri ) ), '/' ) );
}

/**
 * Nainstalované šablony, jejichž Update URI ukazuje na repo EKT_REPO (aktivní i neaktivní).
 *
 * @return array<string, WP_Theme> Složka šablony (stylesheet) => šablona.
 */
function ekt_updater_themes(): array {
	$repo   = ekt_updater_repo( EKT_REPO );
	$themes = array();
	foreach ( wp_get_themes() as $stylesheet => $theme ) {
		if ( $repo === ekt_updater_repo( (string) $theme->get( 'UpdateURI' ) ) ) {
			$themes[ (string) $stylesheet ] = $theme;
		}
	}
	return $themes;
}

/**
 * Poslední vydání repa EKT_REPO: verze z tagu, odkaz na vydání a ZIPy ve vydání (název => adresa
 * ke stažení, u soukromého repa API adresa assetu). Cache site transient
 * ekt_theme_release_<md5 repa>, společná pro všechny šablony repa: úspěch 6 h, chyba 1 h.
 *
 * @return array{repo:string,version:string,url:string,assets:array<string,string>}|array{} Prázdné = nic.
 */
function ekt_theme_release(): array {
	$repo   = ekt_updater_repo( EKT_REPO );
	$key    = 'ekt_theme_release_' . md5( $repo );
	$cached = get_site_transient( $key );
	if ( array() === $cached || ( is_array( $cached ) && $repo === ( $cached['repo'] ?? '' ) && is_string( $cached['version'] ?? null ) && is_array( $cached['assets'] ?? null ) ) ) {
		return $cached;
	}
	delete_site_transient( 'ekt_theme_release' ); // cache updateru 1.0.1 (jen jedna šablona, balíček už vybraný)

	$release  = array();
	$response = wp_remote_get(
		'https://api.github.com/repos/' . EKT_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => ekt_updater_headers(),
		)
	);
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data    = is_array( $data ) ? $data : array();
		$private = '' !== ekt_updater_token();
		$assets  = array();
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			// Soukromé repo: stahuje se přes API (s tokenem, viz ekt_updater_http_args).
			$url = (string) ( $private ? ( $asset['url'] ?? '' ) : ( $asset['browser_download_url'] ?? '' ) );
			if ( str_ends_with( $name, '.zip' ) && '' !== $url ) {
				$assets[ $name ] = $url;
			}
		}
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		if ( '' !== $version && $assets ) {
			$release = array(
				'repo'    => $repo,
				'version' => $version,
				'url'     => (string) ( $data['html_url'] ?? '' ),
				'assets'  => $assets,
			);
		}
	}
	set_site_transient( $key, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

/**
 * Nabídka aktualizace všem šablonám repa, které mají ve vydání svůj ZIP (<složka šablony>.zip).
 * Nabídky z wordpress.org pro tyto šablony zahodí (šablona stejného jména tam patří někomu jinému).
 *
 * @param mixed $transient Data aktualizací šablon.
 * @return mixed
 */
function ekt_updater_offer( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$themes = ekt_updater_themes();
	foreach ( array_keys( $themes ) as $stylesheet ) {
		if ( isset( $transient->response[ $stylesheet ] ) ) {
			unset( $transient->response[ $stylesheet ] ); // nikdy nenabízet „aktualizaci“ z wordpress.org
		}
	}
	$release = $themes ? ekt_theme_release() : array();
	if ( ! $release ) {
		return $transient;
	}
	foreach ( $themes as $stylesheet => $theme ) {
		$package = (string) ( $release['assets'][ $stylesheet . '.zip' ] ?? '' );
		if ( '' === $package ) {
			if ( isset( $transient->no_update[ $stylesheet ] ) ) {
				unset( $transient->no_update[ $stylesheet ] ); // ve vydání není, nic nenabízet
			}
			continue;
		}
		$item = array(
			'theme'        => $stylesheet,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $package,
			'requires'     => (string) $theme->get( 'RequiresWP' ),
			'requires_php' => (string) $theme->get( 'RequiresPHP' ),
		);
		if ( version_compare( $release['version'], (string) $theme->get( 'Version' ), '>' ) ) {
			$transient->response[ $stylesheet ] = $item;
			if ( isset( $transient->no_update[ $stylesheet ] ) ) {
				unset( $transient->no_update[ $stylesheet ] );
			}
		} else {
			$transient->no_update[ $stylesheet ] = $item;
		}
	}
	return $transient;
}

/**
 * Pojistka při aktualizaci šablony repa: balíček musí obsahovat složku téže šablony. Jinak by
 * WordPress aktualizovanou šablonu smazal a na její místo rozbalil jinou.
 *
 * @param string|WP_Error $source        Rozbalená složka balíčku.
 * @param string          $remote_source Pracovní složka.
 * @param WP_Upgrader     $upgrader      Upgrader.
 * @param array           $hook_extra    Typ a slug aktualizované položky.
 * @return string|WP_Error
 */
function ekt_updater_source_check( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	$stylesheet = (string) ( $hook_extra['theme'] ?? '' );
	if ( is_wp_error( $source ) || '' === $stylesheet || ! isset( ekt_updater_themes()[ $stylesheet ] ) ) {
		return $source;
	}
	$folder = basename( untrailingslashit( (string) $source ) );
	if ( $folder !== $stylesheet ) {
		return new WP_Error(
			'ekt_updater_folder',
			sprintf( 'Balíček pro šablonu %1$s obsahuje složku %2$s. Aktualizace se nepoužila, šablona zůstává beze změny.', esc_html( $stylesheet ), esc_html( $folder ) )
		);
	}
	return $source;
}

/**
 * Soukromé repo: token a Accept octet-stream pro stažení balíčku z API.
 *
 * @param array  $args HTTP argumenty.
 * @param string $url  Adresa.
 * @return array
 */
function ekt_updater_http_args( $args, $url ) {
	if ( is_array( $args ) && str_starts_with( (string) $url, 'https://api.github.com/repos/' . EKT_REPO . '/releases/assets/' ) ) {
		$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), ekt_updater_headers( 'application/octet-stream' ) );
	}
	return $args;
}

/**
 * Smaže uložené vydání repa (a cache updateru 1.0.1).
 */
function ekt_updater_forget(): void {
	delete_site_transient( 'ekt_theme_release_' . md5( ekt_updater_repo( EKT_REPO ) ) );
	delete_site_transient( 'ekt_theme_release' );
}

/**
 * Tlačítko „Zkontrolovat znovu“ v Aktualizacích smaže i naši cache.
 */
function ekt_updater_force_check(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['force-check'] ) ) {
		ekt_updater_forget();
	}
}

/**
 * Po aktualizaci šablon zapomenout uložené vydání.
 *
 * @param WP_Upgrader $upgrader Upgrader.
 * @param array       $extra    Typ a seznam aktualizovaných položek.
 */
function ekt_updater_clear( $upgrader, $extra = array() ): void {
	if ( 'theme' === ( $extra['type'] ?? '' ) ) {
		ekt_updater_forget();
	}
}

if ( '' !== EKT_REPO && ( 'local' !== wp_get_environment_type() || ( defined( 'EKT_UPDATER_ON_LOCAL' ) && EKT_UPDATER_ON_LOCAL ) ) ) {
	add_filter( 'pre_set_site_transient_update_themes', 'ekt_updater_offer' );
	add_filter( 'upgrader_source_selection', 'ekt_updater_source_check', 10, 4 );
	add_action( 'load-update-core.php', 'ekt_updater_force_check' );
	add_action( 'upgrader_process_complete', 'ekt_updater_clear', 10, 2 );
	if ( '' !== ekt_updater_token() ) {
		add_filter( 'http_request_args', 'ekt_updater_http_args', 10, 2 );
	}
}
