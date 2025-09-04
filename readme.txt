=== Coinsnap Bitcoin Voting ===

Contributors: coinsnap
Tags: Lightning, bitcoin, voting, polling, BTCPay
Tested up to: 6.8
Stable tag: 1.2.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect small Bitcoin (Satoshi) payments for every vote on your WordPress site. Great for monetized polls, community engagement and SPAM-free surveys


== Coinsnap Bitcoin Voting – Earn Sats with Every Vote ==

Turn votes into Bitcoin: The Coinsnap Bitcoin Voting plugin lets you collect small Bitcoin (Satoshi) payments for every vote on your WordPress site. Great for monetized polls, community engagement, and SPAM-free surveys.

Visitors vote by paying a small amount of sats (you define the amount). This:

* **Prevents bots** from distorting the result
* **Creates a small income stream** for your site
* **Makes each vote count** (literally)

Coinsnap Bitcoin Voting works with Coinsnap or your own BTCPay Server.


== Requirements: ==

* A WordPress website
* The Coinsnap Bitcoin Donation plugin
* A [Coinsnap account](https://app.coinsnap.io/register) or your own BTCPay Server

== Features & functions: ==

* Easy customization of your polls:

	* Offer up to **4 answer options** per poll
	* Define the **price per vote** in sats
	* Choose between **one vote per person** or **multiple votes**
	* Set **voting duration**
	* Optionally collect user info: **name, email, address, or custom field**
	* Turn any poll into a **Bitcoin-powered contest**

* Your voters see **intermediate results** instantly after voting
* Protect against **spam and bots** through pay-to-vote
* **Easy integration via shortcodes** - polls can be placed anywhere on your website by pasting the shortcode at the appropriate place.
* **Receive payments directly into your Bitcoin wallet** - either via Coinsnap or your own BTCPay Server.


== Quick setup: ==

* Install plugin directly via the WordPress plugin directory
* Configure with just a few clicks
* And that's it!


== Two operating modes: ==

* Use Coinsnap (no technical know-how required)
* Or use your own BTCPay server (for advanced users)


== Why Coinsnap Bitcoin Voting? ==

* Open source and free in the WordPress Plugin Directory
* No programming knowledge required
* Immediate credit to your Bitcoin wallet
* GDPR-friendly: no unnecessary data storage
* Continuous further development
* Strong support through our support team, accessible in your Coinsnap account


== More information ==

* Live demo: [https://voting.coinsnap.org/](https://voting.coinsnap.org/)
* Product page: [https://coinsnap.io/coinsnap-bitcoin-voting-plugin/](https://coinsnap.io/coinsnap-bitcoin-voting-plugin/) 
* Installation Guide: [https://coinsnap.io/coinsnap-bitcoin-voting-installation-guide/](https://coinsnap.io/coinsnap-bitcoin-voting-installation-guide/)
* Github plugin page: [https://github.com/Coinsnap/bitcoin-voting/](https://github.com/Coinsnap/bitcoin-voting/)


== Documentation: ==

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/faq/) 
* [Terms and Conditions](https://coinsnap.io/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/privacy/)


== Installation ==

= 1. the Coinsnap Bitcoin Voting plugin from the WordPress directory. =

The Coinsnap Bitcoin Voting plugin can be searched and installed in the WordPress plugin directory.

You can easily find the Coinsnap Bitcoin Voting plugin under **Plugins/Install new plugin** if you enter Coinsnap Bitcoin Voting in the search field. Simply click on **Install now** in the Coinsnap plugin and WordPress will install it for you.

Now WordPress will offer you to **Activate** the plugin – click the button and you are set to go!

Next, you will connect the plugin with your Coinsnap account.


= 1.1. Coinsnap Bitcoin Voting Settings =

After you have installed and activated the Coinsnap Bitcoin Voting plugin, you need to configure the Coinsnap settings. Go to **Bitcoin Voting -> Settings** [1] in the black sidebar on the left.

Now choose your payment gateway **Coinsnap** [1]. (You can also choose BTCPay server if you are using one, and then fill in the respective information.)
Then you’ll have to enter your **Coinsnap Store ID** and your **Coinsnap API Key**. [2] (See below to learn how to retrieve these from your Coinsnap account.)

As soon as you’ve pasted the Store ID and the API Key into their fields, click on **check**. If you see a green message next to it saying **Connection successful**, your plugin is ready to accept Bitcoin votes and credit them to your Lightning wallet.

Don’t forget to klick on **Save changes** before you start configuring your poll(s)!



= 1.2. Enter Store ID and API Key in your Coinsnap Bitcoin Voting Settings =

Go to the **Settings** menu item in your Coinsnap merchant admin backend ([https://app.coinsnap.io/login](https://app.coinsnap.io/login)). Then click on **Store** and you will see your Coinsnap **Store ID** and the Coinsnap **API Key** in the Store section.

**Copy** these two strings and paste them into the matching fields in the **Coinsnap Bitcoin Voting settings** in your WordPress backend.

Click on the “**Save changes**” button at the bottom of the page to apply and save the settings. You are ready to start selling for Bitcoin now: Just create a donation form and place it via the shortcode on your website.


= YOU ARE SET TO SELL FOR BITCOIN NOW! To be sure all works fine, you should now... =


= 1.3. Test the payment method in a Coinsnap Bitcoin Voting poll on your website =

After all settings have been made, a test transaction should be carried out.

Choose any option in your poll. If your poll is not set to gather voter information, a QR code will appear. If you do gather information, you need to fill out the information you require to vote.

You now have to pay your voting “fee” by scanning the displayed QR code and authorizing the payment with your Lightning wallet. After successful payment, you will see a confirmation.


= 2. Install the Coinsnap Bitcoin Voting plugin from our Github page = 

If you don’t want to install the Coinsnap Bitcoin Voting plugin directly from your WordPress backend, download the Coinsnap Bitcoin Voting plugin from the [Coinsnap Github page](https://github.com/Coinsnap/Bitcoin-Voting).

Find the green button labeled **Code** on the top right. When you click on it, the menu opens and **Download ZIP** appears in the dropdown menu. By clicking on it you will download the latest version of the Coinsnap plugin to your computer.

Then use the “**Upload plugin**” function in WordPress to install it. Click on “**Install now**” and the Coinsnap Bitcoin Votingplugin will be added to your WordPress website. It can then be connected to the Coinsnap payment gateway (as explained above).


From here on you can follow 1.1 to 1.3 and you will be set to sell for Bitcoin in no time at all!


=== Upgrade Notice ===

Follow updates on plugin's GitHub page: [https://github.com/Coinsnap/bitcoin-voting/](https://github.com/Coinsnap/bitcoin-voting/)

=== Frequently Asked Questions ===

Plugin's page on Coinsnap website: [https://coinsnap.io/coinsnap-bitcoin-voting-plugin/](https://coinsnap.io/coinsnap-bitcoin-voting-plugin/)

=== Screenshots ===

 
=== Changelog ===

= 1.0.0 :: 2025-04-30 =
* Initial release.

= 1.1.0 :: 2025-06-18 =
* Update: BTCPay setup wizard is added in BTCPay server settings.

= 1.2.0 :: 2025-09-04 =
* Update: Added payment gateway client class. 
* Update: Added support for all the Coinsnap currencies instead of SATS only
* Update: Prevented redirect to payment gateway if payment amount is less than 1 SAT or currency in not supported by Coinsnap.
* Update: Prevented redirect to BTCPay server if payment amount is less than 0.000005869 BTC (0.50 EUR) for onchain payments, 0.000001 BTC (1 SAT) for Lightning payment or currency is not supported.
* Update: Minimum order amount is added to connection status notice.
