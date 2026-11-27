<?php
/**
 * Projektový modul šablony: styly bloků (Bublina, Dráha slunce, Noc, Kniha) a vzory bloků
 * (Jak lekce probíhá, Bublina s textem, Kniha). Vzhled je v main.css, editor ho načítá také.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		$styles = array(
			'core/group'      => array(
				'ivb-bubble' => 'Bublina',
				'ivb-arc'    => 'Dráha slunce (fáze)',
				'ivb-night'  => 'Noc s hvězdami',
			),
			'core/media-text' => array(
				'ivb-book' => 'Kniha (noc)',
			),
		);
		foreach ( $styles as $block => $list ) {
			foreach ( $list as $name => $label ) {
				register_block_style(
					$block,
					array(
						'name'  => $name,
						'label' => $label,
					)
				);
			}
		}

		register_block_pattern_category( 'ivb', array( 'label' => 'Iva Vašková' ) );

		// Stejná stránka rezervace jako ostatní tlačítka Rezervovat (šablona Kontakt a rezervace);
		// adresa se při vložení vzoru uloží do obsahu, platí tedy ta z doby vložení.
		$booking = esc_url( ivb_booking_url() ?: home_url( '/kontakt-a-rezervace/' ) );
		$phases  = array( 'Vleže, se zavřenýma očima', 'Bez úsilí a tlaku', 'Za doprovodu hudby', 'S jemným vědomým dotekem', 'S Tvojí vlastní energií a moudrostí těla' );
		$items   = '';
		foreach ( $phases as $phase ) {
			$items .= '<!-- wp:list-item --><li>' . esc_html( $phase ) . '</li><!-- /wp:list-item -->';
		}

		register_block_pattern(
			'ivb/jak-lekce-probiha',
			array(
				'title'       => 'Jak lekce probíhá (dráha slunce)',
				'description' => 'Nadpis, podtitulek, pět fází na dráze slunce a tlačítko Objednat se (odkaz na stránku rezervace platnou při vložení vzoru). Na mobilu se fáze řadí pod sebe; jiný počet fází než pět = vždy pod sebou.',
				'categories'  => array( 'ivb' ),
				'content'     => '<!-- wp:group {"className":"is-style-ivb-arc"} --><div class="wp-block-group is-style-ivb-arc">'
					. '<!-- wp:heading --><h2 class="wp-block-heading">Jak lekce probíhá</h2><!-- /wp:heading -->'
					. '<!-- wp:paragraph --><p>Bezpečně a ve Tvém vlastním tempu</p><!-- /wp:paragraph -->'
					. '<!-- wp:list {"ordered":true} --><ol class="wp-block-list">' . $items . '</ol><!-- /wp:list -->'
					. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $booking . '">Objednat se</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
					. '</div><!-- /wp:group -->',
			)
		);

		register_block_pattern(
			'ivb/bublina',
			array(
				'title'       => 'Bublina s textem',
				'description' => 'Světle khaki bublina se zlatým obrysem a ocáskem.',
				'categories'  => array( 'ivb' ),
				'content'     => '<!-- wp:group {"className":"is-style-ivb-bubble"} --><div class="wp-block-group is-style-ivb-bubble">'
					. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Nadpis bubliny</h3><!-- /wp:heading -->'
					. '<!-- wp:paragraph --><p>Text v bublině.</p><!-- /wp:paragraph -->'
					. '</div><!-- /wp:group -->',
			)
		);

		register_block_pattern(
			'ivb/kniha',
			array(
				'title'       => 'Kniha v noční bublině',
				'description' => 'Obálka knihy a text v tmavé bublině – patří do skupiny ve stylu Noc s hvězdami.',
				'categories'  => array( 'ivb' ),
				'content'     => '<!-- wp:group {"className":"is-style-ivb-night","align":"wide"} --><div class="wp-block-group alignwide is-style-ivb-night">'
					. '<!-- wp:media-text {"className":"is-style-ivb-book","mediaWidth":30} --><div class="wp-block-media-text is-stacked-on-mobile is-style-ivb-book" style="grid-template-columns:30% auto"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content">'
					. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Název knihy</h3><!-- /wp:heading -->'
					. '<!-- wp:paragraph --><p>Krátký popis knihy.</p><!-- /wp:paragraph -->'
					. '</div></div><!-- /wp:media-text -->'
					. '</div><!-- /wp:group -->',
			)
		);
	}
);
