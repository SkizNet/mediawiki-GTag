<?php

use MediaWiki\MediaWikiServices;

class ShynetHooks {
	/**
	 * Add tracking js to page
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $sk ) {
		$user = $out->getUser();
		$config = $out->getConfig();
		$permMan = MediaWikiServices::getInstance()->getPermissionManager();

		$shynetId = $config->get( 'ShynetId' );


		// Determine if the user is exempt from tracking
		if ( $permMan->userHasRight( $user, 'gtag-exempt' ) ) {
			return;
		}

		// Additional GTag config
		$gtConfig = [];

		// get a json string representing GTag config,
		// which is passed into <script> as a js object
		if ( $gtConfig ) {
			$gtConfigJson = json_encode( $gtConfig );
		} else {
			$gtConfigJson = '{}';
		}


		// If we get here, the user should be tracked
		$out->addScript( Html::element( 'script', [
			'src' => "$shynetId",
			'async' => true,
			'nonce' => $out->getCSP()->getNonce()
		] ) );
		$out->addInlineScript( <<<EOS
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());
			gtag('config', '$shynetId', $gtConfigJson);
			EOS
		);
	}
}
