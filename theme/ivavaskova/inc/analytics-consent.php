<?php
/**
 * Google Analytics 4 se souhlasem (Consent Mode v2) + vlastní cookie lišta.
 * Nahrazuje MonsterInsights a CookieYes (cookie-law-info).
 *
 * - Bez vyplněného ID (Přizpůsobit → Iva Vašková) se nenačte nic.
 * - Výchozí stav „denied“; gtag.js se stáhne až po souhlasu.
 * - Dokud běží MonsterInsights nebo CookieYes, šablona GA ani lištu nevypisuje (žádné dvojí měření).
 * - Odkaz „Nastavení cookies“ v patičce lištu znovu otevře.
 */

defined( 'ABSPATH' ) || exit;

function iv_ga4_id(): string {
	return (string) get_theme_mod( 'iv_ga4_id' );
}

function iv_consent_enabled(): bool {
	if ( '' === iv_ga4_id() || is_customize_preview() ) {
		return false;
	}
	return ! function_exists( 'MonsterInsights_Lite' ) && ! function_exists( 'MonsterInsights' ) && ! defined( 'CLI_VERSION' );
}

add_action( 'wp_head', function () {
	if ( ! iv_consent_enabled() ) {
		return;
	}
	$id = iv_ga4_id();
	?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});
gtag('js',new Date());gtag('config',<?php echo wp_json_encode( $id ); ?>,{anonymize_ip:true});
(function(){var c=null;try{c=localStorage.getItem('iv-consent');}catch(e){}
window.ivLoadGA=function(){if(window.ivGALoaded)return;window.ivGALoaded=1;var s=document.createElement('script');s.async=1;s.src='https://www.googletagmanager.com/gtag/js?id='+<?php echo wp_json_encode( rawurlencode( $id ) ); ?>;document.head.appendChild(s);};
if(c==='yes'){gtag('consent','update',{analytics_storage:'granted'});window.ivLoadGA();}})();
</script>
	<?php
}, 3 );

add_action( 'wp_footer', function () {
	if ( ! iv_consent_enabled() ) {
		return;
	}
	?>
<div class="iv-consent" id="iv-consent" role="dialog" aria-live="polite" aria-label="Souhlas s cookies" hidden>
	<p>Web používá analytické cookies (Google Analytics) k&nbsp;měření návštěvnosti. Souhlasíte s&nbsp;nimi?</p>
	<div class="iv-consent-actions">
		<button type="button" class="iv-btn iv-btn-ghost" data-iv-consent="no">Jen nezbytné</button>
		<button type="button" class="iv-btn" data-iv-consent="yes">Souhlasím</button>
	</div>
</div>
<script>
(function(){var bar=document.getElementById('iv-consent');if(!bar)return;var c=null;try{c=localStorage.getItem('iv-consent');}catch(e){}
function show(){bar.hidden=false;}
if(c!=='yes'&&c!=='no')show();
bar.addEventListener('click',function(e){var b=e.target.closest('[data-iv-consent]');if(!b)return;var v=b.getAttribute('data-iv-consent');try{localStorage.setItem('iv-consent',v);}catch(err){}
if(v==='yes'){gtag('consent','update',{analytics_storage:'granted'});window.ivLoadGA();}else{gtag('consent','update',{analytics_storage:'denied'});}
bar.hidden=true;});
document.addEventListener('click',function(e){if(e.target.closest('[data-iv-consent-open]')){e.preventDefault();show();}});})();
</script>
	<?php
}, 50 );

/** Odkaz do patičky, který lištu znovu otevře (jen když je lišta aktivní). */
function iv_consent_link(): string {
	return iv_consent_enabled() ? '<a href="#" data-iv-consent-open>Nastavení cookies</a>' : '';
}
