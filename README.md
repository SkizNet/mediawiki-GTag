# GTag Extension for MediaWiki

The GTag extension lets you insert the new Google Analytics
tracking tag on your MediaWiki site (gtag.js).

## Requirements

- MediaWiki 1.34 or later

## Installation

[Download the file from mwusers.org](https://mwusers.org/files/file/4-gtag/)
(a free account is required) and extract the file to your
extensions directory. We recommend that you "follow" the
download so that you are notified of new updates via email
when they are released.

To install the extension, add the following to your
LocalSettings.php file:
```php
wfLoadExtension( 'GTag' );
$wgGTagAnalyticsId = 'UA-XXXXXXXX-X'; // replace with your GA id
```

## Configuration

In addition to the required `$wgGTagAnalyticsId`, this extension
features many optional configuration variables that you may add
to your LocalSettings.php file.

| Variable | Default | Description |
| -------- | ------- | ----------- |
| `$wgGTagAnalyticsId` | _none_ | Google Analytics Id, for example `'UA-123456789-1'`. Required. |
| `$wgGTagHonorDNT` | `true` | If true, honor "Do Not Track" requests from browsers. If false, ignore such requests. |
| `$wgGTagTrackSensitivePages` | `true` | If true, insert tracking code into sensitive pages such as Special:UserLogin and Special:Preferences. If false, no tracking code is added to these pages. |

In addition to these configuration variables, you may assign the
right `gtag-exempt` to user groups to prevent them from being
tracked. This can be useful to give to staff groups so that your
internal users and staff are not tracked, giving you a better
idea of who is actually using your site. For example:
```php
$wgGroupPermissions['sysop']['gtag-exempt'] = true;
```

## Support

Free community support is available on the mwusers.org forums.
[Paid support plans](https://mwusers.org/store/category/2-mediawiki-support-subscriptions/)
are available as well.
