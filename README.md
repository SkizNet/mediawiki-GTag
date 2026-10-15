# GTag Extension for MediaWiki

The GTag extension lets you insert the new Google Analytics
tracking tag on your MediaWiki site (gtag.js).

## Requirements

- MediaWiki 1.43 or later

## Installation

[Download the extension][1] and extract it to your wiki's `extensions/` directory.

To install the extension, add the following to your LocalSettings.php file:
```php
wfLoadExtension( 'GTag' );
$wgGTagAnalyticsId = 'GT-XXXXXXXX'; // replace with your GA id or GTM id
```

## Configuration

In addition to the required `$wgGTagAnalyticsId`, this extension
features many optional configuration variables that you may add
to your LocalSettings.php file.

| Variable                     | Default | Description                                                                                                                                               |
|------------------------------|---------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `$wgGTagAnalyticsId`         | _none_  | Google Analytics ID or Google Tag Manager container ID, for example `'GT-NNVDXRX5'` or `'GTM-MG9RFZQQ'`. Required.                                        |
| `$wgGTagAnonymizeIP`         | `false` | If true, [anonymize IP addresses sent to Google Analytics][5]. Ignored when operating in Google Tag Manager mode.                                         |
| `$wgGTagEnableTCF`           | `false` | If true, enable support for the IAB Transparency & Consent Framework. Ignored when CookieConsent is loaded, and when operating in Google Tag Manager mode. |
| `$wgGTagHonorDNT`            | `false` | If true, honor "Do Not Track" requests from browsers. If false, ignore such requests.                                                                     |
| `$wgGTagTrackSensitivePages` | `true`  | If true, insert tracking code into sensitive pages such as Special:UserLogin and Special:Preferences. If false, no tracking code is added to these pages. |

In addition to these configuration variables, you may assign the
right `gtag-exempt` to user groups to prevent them from being
tracked. This can be useful to give to staff groups so that your
internal users and staff are not tracked, giving you a better
idea of who is actually using your site. For example:
```php
$wgGroupPermissions['sysop']['gtag-exempt'] = true;
```

When [CookieConsent][6] is loaded, GTag emits Google scripts as `type="text/plain"` with `data-mw-cookieconsent="statistics"`. CookieConsent enables them after statistics consent. CookieConsent needs JavaScript, so GTM does not output the noscript iframe in this mode. Example:
```php
wfLoadExtension( 'CookieConsent' );
wfLoadExtension( 'GTag' );
$wgGTagAnalyticsId = 'G-XXXXXXXX';
```

**Warning:** Do not enable `$wgCookieConsentEnableGeolocation` if anonymous HTML is cached (MediaWiki file cache, Varnish, or Cloudflare HTML cache). The first visitor's country is then reused for everyone. With GTag, if that HTML has no CookieConsent JavaScript, Google stays `text/plain` and never starts.

## Support

- For general community support questions, please make use of the [talk page on mediawiki.org][2].
- For bug reports, [open an issue on GitHub][3].
- [Paid support plans][4] are available for private support and a guaranteed SLA.

[1]: https://github.com/SkizNet/mediawiki-GTag/archive/refs/heads/master.zip
[2]: https://www.mediawiki.org/wiki/Extension_talk:GTag
[3]: https://github.com/SkizNet/mediawiki-GTag/issues
[4]: https://store.skizzerz.net/store/mediawiki-support
[5]: https://support.google.com/analytics/answer/2763052
[6]: https://www.mediawiki.org/wiki/Extension:CookieConsent
