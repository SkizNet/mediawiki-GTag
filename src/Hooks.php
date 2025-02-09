<?php

namespace MediaWiki\Extension\GTag;

use Html;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\ResourceLoader\Module;
use OutputPage;
use Skin;

class Hooks implements BeforePageDisplayHook {
	/** @var PermissionManager */
	private PermissionManager $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * Add tracking js to page
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$user = $out->getUser();
		$config = $out->getConfig();
		$request = $out->getRequest();

		$gaId = $config->get( 'GTagAnalyticsId' );
		$anonymizeIP = $config->get( 'GTagAnonymizeIP' );
		$honorDNT = $config->get( 'GTagHonorDNT' );
		$enableTCF = $config->get( 'GTagEnableTCF' );
		$trackSensitive = $config->get( 'GTagTrackSensitivePages' );

		$validId = preg_match( '/^(?<tagType>[A-Z]+)-[0-9A-Z-]+$/', $gaId, $matches );
		if ( $gaId === '' || !$validId ) {
			// extension not configured yet or invalid configuration, no-op
			return;
		}

		// Determine if this is a sensitive page and we should not track it.
		if ( !$trackSensitive ) {
			$allowed = $out->getAllowedModules( Module::TYPE_SCRIPTS );
			if ( $allowed < Module::ORIGIN_USER_SITEWIDE ) {
				// the current page is not allowing user-editable modules and we
				// are configured to not track sensitive pages
				return;
			}
		}

		// Determine if we honor DNT headers
		if ( $honorDNT ) {
			// ensure caches vary by the DNT header so that the tracking code is only sent
			// via upstream caches to people who have not opted out of tracking
			$out->addVaryHeader( 'DNT' );
			$dnt = $request->getHeader( 'DNT' );
			if ( $dnt === '1' ) {
				// User has sent the DNT header indicating that they do not wish to be tracked
				return;
			}
		}

		// Determine if the user is exempt from tracking
		if ( $this->permissionManager->userHasRight( $user, 'gtag-exempt' ) ) {
			return;
		}

		// Additional GTag config
		$gtConfig = [];

		if ( $anonymizeIP ) {
			$gtConfig['anonymize_ip'] = true;
		}

		// get a json string representing GTag config,
		// which is passed into <script> as a js object
		if ( $gtConfig ) {
			$gtConfigJson = json_encode( $gtConfig );
		} else {
			$gtConfigJson = '{}';
		}

		if ( $enableTCF ) {
			$tcfLine = 'window["gtag_enable_tcf_support"] = true;';
		} else {
			$tcfLine = '';
		}

		// If we get here, the user should be tracked
		switch ( $matches['tagType'] ) {
			case 'GTM':
				$this->setupGTM( $gaId, $out );
				break;
			default:
				$this->setupGtag( $gaId, $tcfLine, $gtConfigJson, $out );
				break;
		}
	}

	/**
	 * Set up a Google Tag Manager container.
	 *
	 * @param string $gaId
	 * @param OutputPage $out
	 * @return void
	 */
	private function setupGTM( string $gaId, OutputPage $out ): void {
		$out->addInlineScript( <<<EOS
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','$gaId');
EOS
		);

		$out->addHTML( <<<EOS
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=$gaId"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
EOS
		);
	}

	/**
	 * Set up a gtag.js container.
	 *
	 * @param string $gaId
	 * @param string $tcfLine
	 * @param string $gtConfigJson
	 * @param OutputPage $out
	 * @return void
	 */
	private function setupGtag( string $gaId, string $tcfLine, string $gtConfigJson, OutputPage $out ): void {
		$out->addScript( Html::element( 'script', [
			'src' => "https://www.googletagmanager.com/gtag/js?id=$gaId",
			'async' => true,
			'nonce' => $out->getCSP()->getNonce()
		] ) );

		$out->addInlineScript( <<<EOS
window.dataLayer = window.dataLayer || [];
$tcfLine
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '$gaId', $gtConfigJson);
EOS
		);
	}
}
