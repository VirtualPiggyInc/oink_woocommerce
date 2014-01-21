#Virtual Piggy - WordPress Checkout Module#

###VirtualPiggy Development Docs###

http://docs.virtualpiggy.com/

##Changelog##

0.1.4 | MD5 Checksum: 73a160e7826843b5a649e4250f4c2b35
- Added a MD5 checksum service.
- Improved order info (Shopp).
- Fixed parent checkout issue (wrong property name).
- Fixed unhandled Reject callbacks.
- Reworked checkout workflow (now uses async callback ALWAYS) [WooCommerce].

0.1.3
- Reworked checkout workflow (now uses async callback ALWAYS) [SHOPP].
- Fixed TransactionId missing on Shopp Order listing.

0.1.2
- Better error handling.
- Fixed Shopp issues with this plugin disabled/uninstalled.

0.1.1
- Reworked services integration.
- Reworked Shopp checkout.

0.1.0

- Improved backward compatibility with old jQuery versions.
- Added pre/post execution hooks on all VPCheckout.js methods.
- Added a customization script (custom.js).

0.0.9

- Fixed name/lastname on checkout form.
- Fixed name/lastname on vp report (no more John Doe).

0.0.8

- Reworked gateway selection via radio button.
- Updated the VP checkout button
- Now the plugin version is accesible via JavaScript: VPCheckout.version() on the checkout page.

0.0.7

- Hidden the VP Selector (WooCommerce)
- Hidden the VP Button when the gateway is disabled (WooCommerce/Shopp)

All Right Reserved - VirtualPiggy (c) 2012
