<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Module;

class GTagHooks {
	/**
	 * Add tracking js to page
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $sk ) {
		$user = $out->getUser();
		$config = $out->getConfig();
		$request = $out->getRequest();
		$permMan = MediaWikiServices::getInstance()->getPermissionManager();

		$gaId = $config->get( 'GTagAnalyticsId' );
		$anonymizeIP = $config->get( 'GTagAnonymizeIP' );
		$honorDNT = $config->get( 'GTagHonorDNT' );
		$enableTCF = $config->get( 'GTagEnableTCF' );
		$trackSensitive = $config->get( 'GTagTrackSensitivePages' );

		if ( $gaId === '' || !preg_match( '/^(UA-[0-9]+-[0-9]+|G-[0-9A-Z]+)$/', $gaId ) ) {
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
		if ( $permMan->userHasRight( $user, 'gtag-exempt' ) ) {
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
