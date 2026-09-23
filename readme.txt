=== CHIP for GiveWP ===
Contributors: chipasia, wanzulnet, awisqirani
Tags: chip
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.4.1
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Better Payment & Business Solutions. Securely accept payment with CHIP for GiveWP.

== Description ==

This is an official CHIP plugin for GiveWP.

CHIP is a comprehensive Digital Finance Platform specifically designed to support and empower Micro, Small and Medium Enterprises (MSMEs). We provide a suite of solutions encompassing payment collection, expense management, risk mitigation, and treasury management.

Our aim is to help businesses streamline their financial processes, reduce
operational complexity, and drive growth.

With CHIP, you gain a financial partner committed to simplifying, digitizing, and enhancing your financial operations for ultimate success.

This plugin will enable your GiveWP site to be integrated with CHIP as documented in the [API Documentation](https://docs.chip-in.asia).

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Optionally, you may set the Brand ID and Secret Key on form basis.
* Donation page. Optionally, the billing fields can be disabled.
* Donation confirmation.
* Give donation page list.

== Changelog ==

= 1.4.1 - 2026-09-23 =
* Fixed - Purchases were rejected by CHIP with "due cannot be in the past" whenever the Timing setting was left empty, so no donation could complete. The due limit is now omitted when it is not configured, matching the WooCommerce gateway.
* Fixed - A failed CHIP API call during checkout surfaced as a fatal error and a blank page instead of a message the donor could act on. Every API response is now validated before it is read.

[See changelog for all versions](https://github.com/CHIPAsia/chip-for-givewp/releases).

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-givewp&pre-installed-plugin-slug=give&redirect=plugins.php&ni=true)

= Minimum Requirements =

* WordPress 6.3 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of CHIP for GiveWP, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "CHIP for GiveWP" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key are available through our merchant dashboard.

= Do I need to set public key for webhook? =

No.

= Where can I find documentation? =

You can visit our [API documentation](https://docs.chip-in.asia/) for your reference.

= What CHIP API services used in this plugin? =

This plugin relies on the CHIP API ([GWP_CHIP_ROOT_URL](https://gate.chip-in.asia)) as follows:

  - **/purchases/**
    - This is for accepting payment
  - **/purchases/<id\>**
    - This is for getting payment status from CHIP
  - **/purchases/<id\>/refund**
    - This is for refunding payment

= How to disable refund feature? =

You need to paste the code below in your wp-config.php to disable refund.
```
define( 'GWP_CHIP_DISABLE_REFUND_PAYMENT', true);
```

== Links ==

[CHIP Website](https://www.chip-in.asia)

[Terms of Service](https://www.chip-in.asia/terms-of-service)

[Privacy Policy](https://www.chip-in.asia/privacy-policy)

[API Documentation](https://docs.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
