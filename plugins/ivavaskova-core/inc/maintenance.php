<?php
/**
 * Údržba webu — Nástroje → Údržba webu.
 *
 *  1) Databáze: velikost tabulek, autoload, optimalizace
 *  2) Tabulky po pluginech: tabulky vypnutých nebo odebraných pluginů (smazání vybraných)
 *  3) Média a odkazy: největší soubory, nepoužité obrázky (hromadné smazání vybraných), rozbité interní odkazy
 *  4) Úklid obsahu: revize, koncepty a koš, transienty
 */

defined( 'ABSPATH' ) || exit;

const IV_MAINT_SLUG = 'iv-udrzba';

add_action( 'admin_menu', function () {
	add_management_page( 'Údržba webu', 'Údržba webu', 'manage_options', IV_MAINT_SLUG, 'iv_maint_page' );
} );

/* =========================================================================
 * Soubory přílohy
 * ====================================================================== */

/** Všechny soubory přílohy na disku (originál, případný nezmenšený originál, zmenšeniny). */
function iv_attachment_files( int $id ): array {
	$main = get_attached_file( $id );
	if ( ! $main ) {
		return array();
	}
	$dir   = dirname( $main );
	$meta  = wp_get_attachment_metadata( $id ) ?: array();
	$files = array( $main );
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = $dir . '/' . $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$files[] = $dir . '/' . $size['file'];
		}
	}
	return array_values( array_unique( array_filter( $files, 'is_file' ) ) );
}

/* =========================================================================
 * Databáze
 * ====================================================================== */

function iv_db_tables(): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT table_name AS t, engine AS e, table_rows AS r, data_length AS d, index_length AS i, data_free AS f
		 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s ORDER BY (data_length + index_length) DESC",
		$wpdb->esc_like( $wpdb->prefix ) . '%'
	) ) ?: array();
}

function iv_db_autoload(): array {
	global $wpdb;
	$where = "autoload IN ('yes','on','auto','auto-on')";
	return array(
		'total' => (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE $where" ),
		'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE $where" ),
		'top'   => $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE $where ORDER BY b DESC LIMIT 10" ),
	);
}

/* =========================================================================
 * Tabulky po pluginech
 * ====================================================================== */

/** Známé vlastní tabulky pluginů: prefix tabulky (bez wp_) => [soubor pluginu, název]. */
function iv_plugin_table_map(): array {
	return array(
		'supsystic_ss_'     => array( 'xxx-social-share-buttons-by-supsystic/index.php', 'Social Share Buttons by Supsystic' ),
		'bwg_'              => array( 'photo-gallery/photo-gallery.php', 'Photo Gallery' ),
		'eum_'              => array( 'stops-core-theme-and-plugin-updates/main.php', 'Easy Updates Manager' ),
		'monsterinsights_'  => array( 'google-analytics-for-wordpress/googleanalytics.php', 'MonsterInsights' ),
		'ulike'             => array( 'wp-ulike/wp-ulike.php', 'WP ULike' ),
		'simple_login_log'  => array( 'simple-login-log/simple-login-log.php', 'Simple Login Log' ),
		'cerber_'           => array( 'wp-cerber/wp-cerber.php', 'WP Cerber' ),
		'wfls_'             => array( 'wordfence/wordfence.php', 'Wordfence' ),
		'yoast_'            => array( 'wordpress-seo/wp-seo.php', 'Yoast SEO' ),
	);
}

/** Tabulky pluginů, které nejsou aktivní: [název tabulky, plugin, řádků, bajtů]. */
function iv_orphan_tables(): array {
	global $wpdb;
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( iv_db_tables() as $t ) {
		$name = substr( $t->t, strlen( $wpdb->prefix ) );
		foreach ( iv_plugin_table_map() as $prefix => $plugin ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				if ( ! is_plugin_active( $plugin[0] ) ) {
					$out[] = array( $t->t, $plugin[1] . ( file_exists( WP_PLUGIN_DIR . '/' . $plugin[0] ) ? ' (vypnutý)' : ' (odebraný)' ), (int) $t->r, (int) ( $t->d + $t->i ) );
				}
				break;
			}
		}
	}
	return $out;
}

/* =========================================================================
 * Média a odkazy
 * ====================================================================== */

function iv_media_largest( int $limit = 15 ): array {
	global $wpdb;
	$rows = array();
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) as $id ) {
		$file = get_attached_file( (int) $id );
		$meta = wp_get_attachment_metadata( (int) $id ) ?: array();
		$size = (int) ( $meta['filesize'] ?? ( $file && is_file( $file ) ? filesize( $file ) : 0 ) );
		if ( ! empty( $meta['original_image'] ) && $file ) {
			$o    = dirname( $file ) . '/' . $meta['original_image'];
			$size = max( $size, is_file( $o ) ? (int) filesize( $o ) : 0 );
		}
		$rows[] = array( (int) $id, $size, $file ? basename( $file ) : '?' );
	}
	usort( $rows, function ( $a, $b ) {
		return $b[1] <=> $a[1];
	} );
	return array_slice( $rows, 0, $limit );
}

/** Obrázky, které nejsou nikde použité (náhled, nastavení šablony, obsah včetně [gallery ids=…]). */
function iv_media_unused(): array {
	global $wpdb;
	$used = array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) );
	foreach ( (array) get_theme_mods() as $v ) {
		if ( is_numeric( $v ) ) {
			$used[] = (int) $v;
		}
	}
	$used[]  = (int) get_option( 'site_icon' );
	$content = implode( "\n", $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft')" ) );
	// Adresy obrázků v nastavení šablony a ve widgetech (text, HTML, obrázek).
	$content .= "\n" . wp_json_encode( get_theme_mods(), JSON_UNESCAPED_SLASHES );
	foreach ( $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'widget\_%' OR option_name LIKE 'theme\_mods\_%'" ) as $opt ) {
		$content .= "\n" . str_replace( '\\/', '/', $opt );
	}
	// Klasické galerie: [gallery ids="1,2,3"]
	if ( preg_match_all( '/\[gallery[^\]]*\bids=["\']?([\d,\s]+)/i', $content, $m ) ) {
		foreach ( $m[1] as $ids ) {
			$used = array_merge( $used, array_map( 'intval', explode( ',', $ids ) ) );
		}
	}
	$used = array_flip( array_filter( $used ) );
	$out  = array();
	foreach ( $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ) as $a ) {
		if ( isset( $used[ (int) $a->ID ] ) ) {
			continue;
		}
		$rel  = (string) get_post_meta( $a->ID, '_wp_attached_file', true );  // 2019/05/obrazek.jpg
		$stem = preg_replace( '#(-scaled)?\.[a-z0-9]+$#i', '', $rel );         // 2019/05/obrazek
		if ( $stem && ( str_contains( $content, $stem ) || str_contains( $content, 'wp-image-' . $a->ID . '"' ) || str_contains( $content, '"id":' . $a->ID . ',' ) || str_contains( $content, '"id":' . $a->ID . '}' ) ) ) {
			continue;
		}
		$out[] = array( (int) $a->ID, $rel );
	}
	return $out;
}

/** Velikost všech souborů přílohy na disku. */
function iv_media_bytes( int $id ): int {
	$bytes = 0;
	foreach ( iv_attachment_files( $id ) as $file ) {
		$bytes += (int) filesize( $file );
	}
	return $bytes;
}

/** Interní odkazy a obrázky v obsahu, které nikam nevedou. */
function iv_links_broken(): array {
	global $wpdb;
	$host  = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$terms = array();
	foreach ( get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) ) as $t ) {
		$terms[ untrailingslashit( (string) wp_parse_url( get_term_link( $t ), PHP_URL_PATH ) ) ] = true;
	}
	$out  = array();
	$seen = array();
	$rows = $wpdb->get_results( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status = 'publish'" );
	foreach ( $rows as $row ) {
		if ( ! preg_match_all( '#(?:href|src)=["\']([^"\']+)["\']#i', $row->post_content, $m ) ) {
			continue;
		}
		foreach ( array_unique( $m[1] ) as $url ) {
			$u = wp_parse_url( html_entity_decode( $url ) );
			$h = preg_replace( '#^www\.#', '', (string) ( $u['host'] ?? '' ) );
			if ( '' === $h && str_starts_with( (string) ( $u['path'] ?? '' ), '/' ) ) {
				$h = $host; // relativní odkaz /contact/
			}
			if ( $h !== $host || empty( $u['path'] ) || '/' === $u['path'] ) {
				continue;
			}
			$path = untrailingslashit( $u['path'] );
			$key  = $row->ID . $path;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( str_contains( $path, '/wp-content/' ) || preg_match( '#\.(?!html?$|php$)[a-z0-9]{2,5}$#i', $path ) ) {
				$ok = is_file( untrailingslashit( ABSPATH ) . $path );
			} elseif ( isset( $terms[ $path ] ) || preg_match( '#/(feed|page/\d+|wp-admin|wp-login\.php)#', $path ) ) {
				$ok = true;
			} else {
				$ok = url_to_postid( home_url( $path . '/' ) ) > 0 || get_page_by_path( ltrim( $path, '/' ) ) || url_to_postid( home_url( $path ) ) > 0;
			}
			if ( ! $ok ) {
				$out[] = array( (int) $row->ID, $row->post_title, $url );
			}
		}
	}
	return $out;
}

/* =========================================================================
 * Akce
 * ====================================================================== */

function iv_maint_handle( string $do ): string {
	global $wpdb;
	switch ( $do ) {
		case 'db-optimize':
			$n = 0;
			foreach ( iv_db_tables() as $t ) {
				$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $t->t ) . '`' );
				$n++;
			}
			return sprintf( 'Optimalizováno tabulek: %d.', $n );
		case 'tables-drop':
			// Smaže jen tabulky, které jsou i teď v seznamu tabulek po pluginech (kontrola znovu na serveru).
			$want    = array_map( 'sanitize_key', (array) ( $_POST['iv_tables'] ?? array() ) );
			$allowed = array_flip( array_map( function ( $r ) {
				return $r[0];
			}, iv_orphan_tables() ) );
			$done    = array();
			foreach ( array_unique( $want ) as $table ) {
				if ( isset( $allowed[ $table ] ) ) {
					$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
					$done[] = $table;
				}
			}
			return $done ? 'Smazány tabulky: ' . implode( ', ', $done ) . '.' : 'Nic nebylo smazáno.';
		case 'revisions':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
			foreach ( $ids as $id ) {
				wp_delete_post_revision( (int) $id );
			}
			return sprintf( 'Smazáno revizí: %d.', count( $ids ) );
		case 'drafts':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
			return sprintf( 'Smazáno automatických konceptů a položek z koše: %d.', count( $ids ) );
		case 'transients':
			delete_expired_transients( true );
			return 'Expirované transienty smazány.';
		case 'media-delete':
			// Maže jen to, co je i teď opravdu nepoužité (kontrola znovu na serveru).
			$want   = array_map( 'intval', (array) ( $_POST['iv_ids'] ?? array() ) );
			$unused = array_flip( array_map( function ( $r ) {
				return $r[0];
			}, iv_media_unused() ) );
			$done   = 0;
			$bytes  = 0;
			@set_time_limit( 120 );
			foreach ( array_unique( $want ) as $id ) {
				if ( ! isset( $unused[ $id ] ) || ! current_user_can( 'delete_post', $id ) ) {
					continue;
				}
				$size = iv_media_bytes( $id );
				if ( wp_delete_attachment( $id, true ) ) {
					$done++;
					$bytes += $size;
				}
			}
			return $done ? sprintf( 'Smazáno %d obrázků, uvolněno %s.', $done, iv_kb( $bytes ) ) : 'Nic nebylo smazáno.';
	}
	return '';
}

/* =========================================================================
 * Stránka
 * ====================================================================== */

function iv_kb( $bytes ): string {
	$bytes = (float) $bytes;
	return $bytes >= 1048576 ? number_format_i18n( $bytes / 1048576, 1 ) . ' MB' : number_format_i18n( $bytes / 1024, 0 ) . ' kB';
}

/** Skript „Vybrat vše / Zrušit výběr / Vybráno N“ pro formulář se zaškrtávátky. */
function iv_maint_select_script( string $form_id ): void {
	printf(
		"<script>(function(){var f=document.getElementById('%s');if(!f)return;var boxes=[].slice.call(f.querySelectorAll('input[type=checkbox]'));var c=f.querySelector('[data-iv-count]');var sync=function(){c.textContent='Vybráno '+boxes.filter(function(b){return b.checked;}).length+' z '+boxes.length;};f.addEventListener('change',sync);[].forEach.call(f.querySelectorAll('[data-iv-all]'),function(b){b.addEventListener('click',function(){boxes.forEach(function(x){x.checked=b.getAttribute('data-iv-all')==='1';});sync();});});sync();})();</script>",
		esc_js( $form_id )
	);
}

function iv_maint_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$notice = '';
	$report = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'iv_maint' ) ) {
		$report = sanitize_key( $_POST['iv_report'] ?? '' );
		$do     = sanitize_key( $_POST['iv_do'] ?? '' );
		if ( $do ) {
			$notice = iv_maint_handle( $do );
			if ( function_exists( 'iv_health_mark_maintenance' ) ) {
				iv_health_mark_maintenance();
			}
		}
	}
	$confirm = "return confirm('Opravdu? Tohle nejde vrátit bez zálohy.');";
	$btn     = function ( string $do, string $label, string $class = 'button', bool $danger = false, string $field = 'iv_do' ) use ( $confirm ) {
		printf( '<form method="post" style="display:inline-block;margin:0 8px 8px 0"%s>', $danger ? ' onsubmit="' . esc_attr( $confirm ) . '"' : '' );
		wp_nonce_field( 'iv_maint' );
		printf( '<input type="hidden" name="%s" value="%s"><button class="%s">%s</button></form>', esc_attr( $field ), esc_attr( $do ), esc_attr( $class ), esc_html( $label ) );
	};
	$select_bar = '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button type="button" class="button" data-iv-all="1">Vybrat vše</button><button type="button" class="button" data-iv-all="0">Zrušit výběr</button><span data-iv-count style="color:#646970">Vybráno 0</span><button class="button button-primary" style="background:#b32d2e;border-color:#b32d2e">Smazat vybrané</button></p>';

	echo '<div class="wrap"><h1>Údržba webu</h1>';
	if ( $notice ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $notice ) );
	}

	/* 1) Databáze */
	$tables = iv_db_tables();
	$al     = iv_db_autoload();
	$sum    = array_sum( array_map( function ( $t ) {
		return $t->d + $t->i;
	}, $tables ) );
	$free   = array_sum( array_map( function ( $t ) {
		return $t->f;
	}, $tables ) );
	echo '<h2>Databáze</h2>';
	printf( '<p>%d tabulek, celkem <strong>%s</strong>%s. Autoload (načítá se při každém požadavku): <strong>%s</strong> v %d volbách.</p>',
		count( $tables ), esc_html( iv_kb( $sum ) ), $free > 1048576 ? ', nevyužité místo ' . esc_html( iv_kb( $free ) ) : '', esc_html( iv_kb( $al['total'] ) ), (int) $al['count'] );
	echo '<details style="max-width:900px"><summary style="cursor:pointer">Tabulky a největší autoload volby</summary><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:8px">';
	echo '<table class="widefat striped"><thead><tr><th>Tabulka</th><th>Řádků</th><th>Velikost</th></tr></thead><tbody>';
	foreach ( $tables as $t ) {
		printf( '<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>', esc_html( $t->t ), esc_html( number_format_i18n( (int) $t->r ) ), esc_html( iv_kb( $t->d + $t->i ) ) );
	}
	echo '</tbody></table><table class="widefat striped"><thead><tr><th>Volba (autoload)</th><th>Velikost</th></tr></thead><tbody>';
	foreach ( $al['top'] as $o ) {
		printf( '<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html( $o->n ), esc_html( iv_kb( $o->b ) ) );
	}
	echo '</tbody></table></div></details><p>';
	$btn( 'db-optimize', 'Optimalizovat tabulky' );
	echo '</p>';

	/* 2) Tabulky po pluginech */
	$orphans = iv_orphan_tables();
	if ( $orphans ) {
		echo '<h2>Tabulky po pluginech</h2>';
		echo '<p style="max-width:900px">Tabulky pluginů, které už nejsou aktivní. Web je nepoužívá; smazání je natrvalo, <strong>před smazáním si udělejte zálohu databáze</strong>.</p>';
		echo '<form method="post" id="iv-tables" onsubmit="var n=this.querySelectorAll(\'input[name=&quot;iv_tables[]&quot;]:checked\').length;return n>0&&confirm(\'Trvale smazat \'+n+\' tabulek? Nejde vrátit bez zálohy.\');">';
		wp_nonce_field( 'iv_maint' );
		echo '<input type="hidden" name="iv_do" value="tables-drop">';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th style="width:2em"></th><th>Tabulka</th><th>Plugin</th><th>Řádků</th><th>Velikost</th></tr></thead><tbody>';
		foreach ( $orphans as $o ) {
			printf(
				'<tr><td><input type="checkbox" name="iv_tables[]" value="%1$s"></td><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_attr( $o[0] ),
				esc_html( $o[1] ),
				esc_html( number_format_i18n( $o[2] ) ),
				esc_html( iv_kb( $o[3] ) )
			);
		}
		echo '</tbody></table>' . $select_bar . '</form>'; // phpcs:ignore WordPress.Security.EscapeOutput -- statické HTML
		iv_maint_select_script( 'iv-tables' );
	}

	/* 3) Média a odkazy */
	echo '<h2>Média a odkazy</h2><p class="description">Kontrola projde celý web, může chvíli trvat. Smazat jde jen to, co v přehledu nepoužitých obrázků sami vyberete.</p><p>';
	$btn( 'largest', 'Největší soubory', 'button', false, 'iv_report' );
	$btn( 'unused', 'Nepoužité obrázky', 'button', false, 'iv_report' );
	$btn( 'links', 'Rozbité interní odkazy', 'button', false, 'iv_report' );
	echo '</p>';
	if ( 'largest' === $report ) {
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Soubor</th><th>Velikost</th></tr></thead><tbody>';
		foreach ( iv_media_largest() as $row ) {
			printf( '<tr><td><a href="%s">%s</a></td><td>%s</td></tr>', esc_url( get_edit_post_link( $row[0] ) ), esc_html( $row[2] ), esc_html( iv_kb( $row[1] ) ) );
		}
		echo '</tbody></table>';
	} elseif ( 'unused' === $report ) {
		$list  = iv_media_unused();
		$sizes = array();
		foreach ( $list as $row ) {
			$sizes[ $row[0] ] = iv_media_bytes( $row[0] );
		}
		printf( '<p>Nalezeno <strong>%d</strong> obrázků (%s), které nejsou použité v žádném článku, stránce, galerii, náhledu, widgetu ani nastavení šablony. Před smazáním je zkontrolujte – mohou být odkazované zvenku.</p>', count( $list ), esc_html( iv_kb( array_sum( $sizes ) ) ) );
		if ( $list ) {
			echo '<form method="post" id="iv-unused" onsubmit="var n=this.querySelectorAll(\'input[name=&quot;iv_ids[]&quot;]:checked\').length;return n>0&&confirm(\'Trvale smazat \'+n+\' obrázků i se všemi velikostmi? Nejde vrátit bez zálohy.\');">';
			wp_nonce_field( 'iv_maint' );
			echo '<input type="hidden" name="iv_do" value="media-delete"><input type="hidden" name="iv_report" value="unused">';
			echo $select_bar; // phpcs:ignore WordPress.Security.EscapeOutput -- statické HTML
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;max-width:1100px">';
			foreach ( $list as $row ) {
				printf(
					'<label style="display:block;font-size:11px;word-break:break-all;border:1px solid #dcdcde;border-radius:6px;padding:6px;background:#fff;cursor:pointer"><span style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><input type="checkbox" name="iv_ids[]" value="%1$d"> <span style="color:#646970">%4$s</span></span>%2$s<br>%3$s <a href="%5$s" target="_blank">upravit</a></label>',
					$row[0],
					wp_get_attachment_image( $row[0], 'thumbnail', false, array( 'style' => 'width:100%;height:90px;object-fit:cover;border-radius:4px' ) ),
					esc_html( $row[1] ),
					esc_html( iv_kb( $sizes[ $row[0] ] ) ),
					esc_url( get_edit_post_link( $row[0] ) )
				);
			}
			echo '</div></form>';
			iv_maint_select_script( 'iv-unused' );
		}
	} elseif ( 'links' === $report ) {
		$list = iv_links_broken();
		if ( ! $list ) {
			echo '<p>✅ Žádné rozbité interní odkazy ani obrázky.</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Článek</th><th>Odkaz</th></tr></thead><tbody>';
			foreach ( $list as $row ) {
				printf( '<tr><td><a href="%s">%s</a></td><td><code style="word-break:break-all">%s</code></td></tr>', esc_url( get_edit_post_link( $row[0] ) ), esc_html( $row[1] ), esc_html( $row[2] ) );
			}
			echo '</tbody></table>';
		}
	}

	/* 4) Úklid obsahu */
	$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
	$drafts    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
	echo '<h2>Úklid obsahu</h2>';
	echo '<table class="widefat striped" style="max-width:900px"><tbody>';
	printf( '<tr><td>Revize článků</td><td>%d</td></tr><tr><td>Automatické koncepty a koš</td><td>%d</td></tr>', $revisions, $drafts );
	echo '</tbody></table><p>';
	if ( $revisions ) {
		$btn( 'revisions', 'Smazat revize', 'button', true );
	}
	if ( $drafts ) {
		$btn( 'drafts', 'Vysypat koncepty a koš', 'button', true );
	}
	$btn( 'transients', 'Smazat expirované transienty' );
	echo '</p></div>';
}
