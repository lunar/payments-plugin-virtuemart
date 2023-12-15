# Lunar Online Payments for Joomla VirtueMart

The software is provided “as is”, without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement.


## Supported VirtueMart versions

* The plugin has been tested with most versions of Virtuemart at every iteration. We recommend using the latest version of Virtuemart, but if that is not possible for some reason, test the plugin with your Virtuemart version and it would probably function properly.

## Installation

*NOTE: It requires PHP version >=7.4

Once you have installed VirtueMart on your Joomla setup, follow these simple steps:
  1. Signup at [lunar.app](https://lunar.app) (it’s free)
  1. Create an account
  1. Create an app key for your Joomla website
  1. Upload the plugin zip trough the 'Extensions' screen in Joomla.
  1. Activate the plugin through the 'Extensions' screen in Joomla.
  1. Under VirtueMart payment methods create a new payment method and select Lunar.
  1. In the configuration tab, choose the payment method.
  1. Insert the app key and your public key in the settings for the Lunar payment gateway you just created


## Updating settings

Under the VirtueMart Lunar payment method settings, you can:
 * Update the payment method text in the payment gateways list
 * Update the payment method description in the payment gateways list
 * Update the title that shows up in the payment popup
 * Add public & app keys
 * Change the capture type (Instant/Manual)

 ## How to

 1. Capture
 * In Instant mode, the orders are captured automatically
 * In Delayed mode you can capture an order by moving the order to the `Shipped` status from pending.
 2. Refund
   * To refund an order move the order into `Refunded` status.
 3. Void/Cancel
   * To void/cancel an order move the order into `Cancelled` status (if the order was not captured, then will void it).

## Available features
1. Capture
   * Virtuemart admin panel: full capture
   * Lunar admin panel: full/partial capture
2. Refund
   * Virtuemart admin panel: full refund
   * Lunar admin panel: full/partial refund
3. Void
   * Virtuemart admin panel: full void
   * Lunar admin panel: full/partial void
