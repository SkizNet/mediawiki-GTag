<?php

namespace MediaWiki\Extension\GTag\Tests;

use HashConfig;
use MediaWiki\Extension\GTag\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Skin;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\GTag\Hooks
 */
class HooksConsentTest extends MediaWikiUnitTestCase {

	/**
	 * @param array $configOverrides
	 * @param bool $exempt
	 * @param bool $cookieConsentLoaded
	 * @return array{0:PermissionManager,1:ExtensionRegistry,2:OutputPage,3:Skin}
	 */
	private function pageFixtures(
		array $configOverrides,
		bool $exempt = false,
		bool $cookieConsentLoaded = false
	): array {
		$config = new HashConfig( $configOverrides + [
			'GTagAnalyticsId' => 'G-TEST1234',
			'GTagAnonymizeIP' => false,
			'GTagHonorDNT' => false,
			'GTagEnableTCF' => false,
			'GTagTrackSensitivePages' => true,
		] );

		$user = $this->createMock( User::class );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getHeader' )->willReturn( false );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )->willReturn( $exempt );

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )->willReturnCallback(
			static function ( $name ) use ( $cookieConsentLoaded ) {
				return $cookieConsentLoaded && $name === 'CookieConsent';
			}
		);

		$csp = $this->createMock( ContentSecurityPolicy::class );
		$csp->method( 'getNonce' )->willReturn( false );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'getUser' )->willReturn( $user );
		$out->method( 'getConfig' )->willReturn( $config );
		$out->method( 'getRequest' )->willReturn( $request );
		$out->method( 'getCSP' )->willReturn( $csp );

		$skin = $this->createMock( Skin::class );

		return [ $permissionManager, $extensionRegistry, $out, $skin ];
	}

	/**
	 * @param PermissionManager $permissionManager
	 * @param ExtensionRegistry $extensionRegistry
	 * @return Hooks&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function newRoutingMock(
		PermissionManager $permissionManager,
		ExtensionRegistry $extensionRegistry
	): Hooks {
		return $this->getMockBuilder( Hooks::class )
			->setConstructorArgs( [ $permissionManager, $extensionRegistry ] )
			->onlyMethods( [
				'addConsentGuardedGtag',
				'addUnguardedGtag',
				'addConsentGuardedGtm',
				'addUnguardedGtm',
			] )
			->getMock();
	}

	public static function provideConsentAndTcfCombinations(): array {
		return [
			'CookieConsent, TCF true' => [ true, true ],
			'CookieConsent, TCF false' => [ true, false ],
			'no CookieConsent, TCF false' => [ false, false ],
			'no CookieConsent, TCF true' => [ false, true ],
		];
	}

	/**
	 * @dataProvider provideConsentAndTcfCombinations
	 * @param bool $cookieConsentLoaded
	 * @param bool $enableTCF
	 */
	public function testRoutingConsentAndTcfCombinations(
		bool $cookieConsentLoaded,
		bool $enableTCF
	): void {
		[ $permissionManager, $extensionRegistry, $out, $skin ] = $this->pageFixtures(
			[ 'GTagEnableTCF' => $enableTCF ],
			false,
			$cookieConsentLoaded
		);
		$hooks = $this->newRoutingMock( $permissionManager, $extensionRegistry );

		$hooks->expects( $this->never() )->method( 'addConsentGuardedGtm' );
		$hooks->expects( $this->never() )->method( 'addUnguardedGtm' );

		if ( $cookieConsentLoaded ) {
			$hooks->expects( $this->once() )->method( 'addConsentGuardedGtag' )->with(
				$this->anything(),
				$this->logicalNot( $this->stringContains( 'gtag_enable_tcf_support' ) ),
				$this->anything()
			);
			$hooks->expects( $this->never() )->method( 'addUnguardedGtag' );
		} elseif ( $enableTCF ) {
			$hooks->expects( $this->never() )->method( 'addConsentGuardedGtag' );
			$hooks->expects( $this->once() )->method( 'addUnguardedGtag' )->with(
				$this->anything(),
				$this->stringContains( 'gtag_enable_tcf_support' ),
				$this->anything()
			);
		} else {
			$hooks->expects( $this->never() )->method( 'addConsentGuardedGtag' );
			$hooks->expects( $this->once() )->method( 'addUnguardedGtag' )->with(
				$this->anything(),
				$this->logicalNot( $this->stringContains( 'gtag_enable_tcf_support' ) ),
				$this->anything()
			);
		}

		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testRoutingCookieConsentGtmUsesGuardedEmitter() {
		[ $permissionManager, $extensionRegistry, $out, $skin ] = $this->pageFixtures(
			[ 'GTagAnalyticsId' => 'GTM-TEST1234' ],
			false,
			true
		);
		$hooks = $this->newRoutingMock( $permissionManager, $extensionRegistry );

		$hooks->expects( $this->once() )->method( 'addConsentGuardedGtm' );
		$hooks->expects( $this->never() )->method( 'addUnguardedGtm' );
		$hooks->expects( $this->never() )->method( 'addConsentGuardedGtag' );
		$hooks->expects( $this->never() )->method( 'addUnguardedGtag' );

		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testRoutingNoAnalyticsIdDoesNotEmitTags() {
		[ $permissionManager, $extensionRegistry, $out, $skin ] = $this->pageFixtures(
			[ 'GTagAnalyticsId' => '' ],
			false,
			true
		);
		$hooks = $this->newRoutingMock( $permissionManager, $extensionRegistry );

		$hooks->expects( $this->never() )->method( 'addConsentGuardedGtag' );
		$hooks->expects( $this->never() )->method( 'addUnguardedGtag' );
		$hooks->expects( $this->never() )->method( 'addConsentGuardedGtm' );
		$hooks->expects( $this->never() )->method( 'addUnguardedGtm' );

		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testConsentGuardedGtagEmitsPlainTags() {
		[ $permissionManager, $extensionRegistry, $out ] = $this->pageFixtures( [] );
		$hooks = TestingAccessWrapper::newFromObject(
			new Hooks( $permissionManager, $extensionRegistry )
		);

		$out->expects( $this->exactly( 2 ) )->method( 'addScript' )->withConsecutive(
			[
				$this->logicalAnd(
					$this->stringContains( 'type="text/plain"' ),
					$this->stringContains( 'data-mw-cookieconsent="statistics"' ),
					$this->stringContains( 'gtag/js?id=G-TEST1234' )
				),
			],
			[
				$this->logicalAnd(
					$this->stringContains( 'type="text/plain"' ),
					$this->stringContains( 'data-mw-cookieconsent="statistics"' ),
					$this->stringContains( 'dataLayer' ),
					$this->logicalNot( $this->stringContains( 'gtag_enable_tcf_support' ) )
				),
			]
		);
		$out->expects( $this->never() )->method( 'addInlineScript' );

		$hooks->addConsentGuardedGtag( 'G-TEST1234', "window.dataLayer = window.dataLayer || [];\n", $out );
	}

	public function testUnguardedGtagEmitsLiveTagsAndTcfLine() {
		[ $permissionManager, $extensionRegistry, $out ] = $this->pageFixtures( [] );
		$hooks = TestingAccessWrapper::newFromObject(
			new Hooks( $permissionManager, $extensionRegistry )
		);

		$out->expects( $this->once() )->method( 'addScript' )->with(
			$this->logicalAnd(
				$this->stringContains( 'gtag/js?id=G-TEST1234' ),
				$this->logicalNot( $this->stringContains( 'text/plain' ) )
			)
		);
		$out->expects( $this->once() )->method( 'addInlineScript' )->with(
			$this->stringContains( 'gtag_enable_tcf_support' )
		);

		$hooks->addUnguardedGtag(
			'G-TEST1234',
			"window[\"gtag_enable_tcf_support\"] = true;\n",
			$out
		);
	}

	public function testConsentGuardedGtmOmitsIframe() {
		[ $permissionManager, $extensionRegistry, $out ] = $this->pageFixtures( [] );
		$hooks = TestingAccessWrapper::newFromObject(
			new Hooks( $permissionManager, $extensionRegistry )
		);

		$out->expects( $this->once() )->method( 'addScript' )->with(
			$this->logicalAnd(
				$this->stringContains( 'type="text/plain"' ),
				$this->stringContains( 'data-mw-cookieconsent="statistics"' ),
				$this->stringContains( 'GTM-TEST1234' )
			)
		);
		$out->expects( $this->never() )->method( 'addInlineScript' );
		$out->expects( $this->never() )->method( 'addHTML' );

		$hooks->addConsentGuardedGtm( "gtm.js?id=GTM-TEST1234\n", $out );
	}

	public function testUnguardedGtmEmitsNoscriptIframe() {
		[ $permissionManager, $extensionRegistry, $out ] = $this->pageFixtures( [] );
		$hooks = TestingAccessWrapper::newFromObject(
			new Hooks( $permissionManager, $extensionRegistry )
		);

		$out->expects( $this->once() )->method( 'addInlineScript' )->with(
			$this->stringContains( 'GTM-TEST1234' )
		);
		$out->expects( $this->once() )->method( 'addHTML' )->with(
			$this->logicalAnd(
				$this->stringContains( '<noscript>' ),
				$this->stringContains( 'googletagmanager.com/ns.html?id=GTM-TEST1234' )
			)
		);
		$out->expects( $this->never() )->method( 'addScript' );

		$hooks->addUnguardedGtm( 'GTM-TEST1234', "gtm.js?id=GTM-TEST1234\n", $out );
	}
}
