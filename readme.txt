=== CHIP for GiveWP ===
Contributors: chipasia, wanzulnet
Tags: chip
Requires at least: 4.7
Tested up to: 6.1
Stable tag: 1.0.3
Requires PHP: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

CHIP - Better Payment & Business Solutions. Securely accept payment with CHIP for GiveWP.

== Description ==

This is an official CHIP plugin for GiveWP.

CHIP is a payment and business solutions platform that allow you to securely sell your products and get paid via multiple local and international payment methods.

This plugin will enable your GiveWP site to be integrated with CHIP as per documented in [API Documentation](https://developer.chip-in.asia/api#online_purchases_custom_payment_flow_direct_post).

== Screenshots ==
* Fill up the form with Brand ID and Secret Key. Tick Enable API and Save changes to activate.
* Optionally, you may set the Brand ID and Secret Key on form basis.
* Donation page. Optionally, the billing fields can be disabled.
* Donation confirmation.
* Give donation page list.

== Changelog ==

= 1.0.3 - 2023-03-08 =
* Fixed - Donator seeing donation failed page when there is previous failed attempt.

= 1.0.2 - 2023-02-17 =
* Fixed - Form created before installing CHIP should adhere to global configuration.

= 1.0.1 - 2022-11-30 =
* Fixed - First and Last name should be separated with space.

= 1.0.0 - 2022-11-29 =
* New - Initial Release.

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=chip-for-givewp&pre-installed-plugin-slug=give&redirect=plugins.php&ni=true)

= Minimum Requirements =

* WordPress 4.7 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "CHIP for GiveWP" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Where is the Brand ID and Secret Key located? =

Brand ID and Secret Key available through our merchant dashboard.

= Do I need to set public key for webhook? =

No.

= Where can I find documentation? =

You can visit our [API documentation](https://developer.chip-in.asia/) for your reference.

= What CHIP API services used in this plugin? =

This plugin rely on CHIP API ([GIVE_CHIP_ROOT_URL](https://gate.chip-in.asia)) as follows:

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

[API Documentation](https://developer.chip-in.asia/)

[CHIP Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
