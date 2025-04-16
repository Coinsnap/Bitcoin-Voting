=== Coinsnap Bitcoin Voting ===

Contributors: coinsnap
Tags: Lightning, SATS, bitcoin, voting, polling, BTCPay
Tested up to: 6.7
Stable tag: 1.0.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instant Bitcoin/Sats powered voting system on Wordpress website directly to your wallet

== Description ==

[Coinsnap](https://coinsnap.io/en/) Bitcoin Voting plugin allows you to process Bitcoin Lightning powered votes over the Lightning network. 
With the Coinsnap Bitcoin-Lightning payment plugin you only need a Lightning wallet with a Lightning address to accept Bitcoin Lightning votes on your Wordpress site.

* Coinsnap Bitcoin Voting Demo Site: https://voting.coinsnap.org/
* Blog Article: https://coinsnap.io/en/
* WordPress: https://wordpress.org/plugins/bitcoin-voting/
* GitHub: https://github.com/Coinsnap/Bitcoin-Voting


== Bitcoin and Lightning payments in Voting Plugin ==

The Coinsnap Bitcoin Voting plugin allows you to launch Bitcoin-powered voting on your site fast and simply.

With the Coinsnap Bitcoin Lightning payment processing plugin you can immediately accept Bitcoin Lightning payments for votes on your site. You don't need your own Lightning node or any other technical requirements if you'd like to provide payments via Coinsnap payment gateway.

Simply register on [Coinsnap](https://app.coinsnap.io/register), enter your own Lightning address and install the Coinsnap payment module in your Wordpress backend. Add your store ID and your API key which you'll find in your Coinsnap account, and your voters can pay with Bitcoin Lightning right away!

If you want to use another BTCPay server as Payment provider, you need to know your store ID, your API key and gateway URL as required parameters.


= Coinsnap features: =

* **All you need is your email and a Lightning Wallet with a Lightning address. [Here you can find an overview of suitable Lightning Wallets](https://coinsnap.io/en/lightning-wallet-with-lightning-address/)**

* **Accept Bitcoin and Lightning payments** in your online store **without running your own technical infrastructure.** You do not need your own server, nor do you need to run your own Lightning Node. You also do not need a shop-system, for you can sell right out of your forms using the Coinsnap for Content Form 7-plugin.

* **Quick and easy registration at Coinsnap**: Just enter your email address and your Lightning address – and you are ready to integrate the payment module and start selling for Bitcoin Lightning. You will find the necessary IDs and Keys in your Coinsnap account, too.

* **100% protected privacy**:
    * We do not collect personal data.
    * For the registration you only need an e-mail address, which we will also use to inform you when you have received a payment.
    * No other personal information is required as long as you request a withdrawal to a Lightning address or Bitcoin address.

* **Only 1 % fees!**:
    * No basic fee, no transaction fee, only 1% on the invoice amount with referrer code.
    * Without referrer code the fee is 1.25%.
    * Get a referrer code from our [partners](https://coinsnap.io/en/partner/) and customers and save 0.25% fee.

* **No KYC needed**:
    * Direct, P2P payments (instantly to your Lightning wallet)
    * No intermediaries and paperwork
    * Transaction information is only shared between you and your customer

* **Sophisticated merchant’s admin dashboard in Coinsnap:**:
    * See all your transactions at a glance
    * Follow-up on individual payments
    * See issues with payments
    * Export reports

* **A Bitcoin payment via Lightning offers significant advantages**:
    * Lightning **payments are executed immediately.**
    * Lightning **payments are credited directly to the recipient.**
    * Lightning **payments are inexpensive.**
    * Lightning **payments are guaranteed.** No chargeback risk for the merchant.
    * Lightning **payments can be used worldwide.**
    * Lightning **payments are perfect for micropayments.**

* **Multilingual interface and support**: We speak your language


= Documentation: =

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/) 
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)


== Installation ==

### 1. Install the Coinsnap Bitcoin Voting plug-in from the WordPress directory. ###

The Coinsnap Bitcoin Voting can be searched and installed in the WordPress plugin directory.

In your WordPress instance, go to the Plugins > Add New section.
In the search you enter Coinsnap and get as a result the Coinsnap Bitcoin Voting plugin displayed.

Then click Install.

After successful installation, click Activate and then you can start setting up the plugin.


### 2. Connect Coinsnap account with Coinsnap Bitcoin Voting plugin ###

After you have installed and activated the Coinsnap Bitcoin Voting plugin, you need to set Coinsnap or BTCPay server up. You can find Coinsnap Bitcoin Voting settings in the sidebar on the left under “Bitcoin Voting”.

To set up Bitcoin Lightning voting, please enter your Coinsnap Store ID and your API key besides the other parameters there; you can find these in your Coinsnap account under “Settings -> Store”, “Coinsnap Shop”.

If you don’t have a Coinsnap account yet, you can do so via the link shown: [Coinsnap Registration](https://app.coinsnap.io/register).


### 3. Create Coinsnap account ####

### 3.1. Create a Coinsnap Account ####

Now go to the Coinsnap website at: [https://app.coinsnap.io/register](https://app.coinsnap.io/register) and open an account by entering your email address and a password of your choice.

If you are using a Lightning Wallet with Lightning Login, then you can also open a Coinsnap account with it.

### 3.2. Confirm email address ####

You will receive an email to the given email address with a confirmation link, which you have to confirm. If you do not find the email, please check your spam folder.

Then please log in to the Coinsnap backend with the appropriate credentials.

### 3.3. Set up website at Coinsnap ###

After you sign up, you will be asked to provide two pieces of information.

In the Website Name field, enter the name of your online store that you want customers to see when they check out.

In the Lightning Address field, enter the Lightning address to which the Bitcoin and Lightning transactions should be forwarded.

A Lightning address is similar to an e-mail address. Lightning payments are forwarded to this Lightning address and paid out. If you don’t have a Lightning address yet, set up a Lightning wallet that will provide you with a Lightning address.

For more information on Lightning addresses and the corresponding Lightning wallet providers, click here:
https://coinsnap.io/lightning-wallet-mit-lightning-adresse/

After saving settings you can use Store ID and Api Key on the step 2.


### 4. Configure Coinsnap Bitcoin Voting plugin ####

### 4.1. Voting shortcode ###

Go to "Bitcoin Voting" in the sideboard on the left in your WordPress and click on "Bitcoin Voting". At the top of the page you will find shortcode [bitcoin_voting] that you can use it in your content.

### 4.2. Configure your settings ####

Scroll down a little bit, and you'll find Coinsnap Bitcoin Voting plugin settings:

* Currency
* Theme (Light/Dark)
* Button Text
* Title Text
* Default amount in chosen currency
* Default Message
* Thank you page URL
* Payment gateway (Coinsnap / BTCPay server)

After you will fill all the necessary data you can use shortcode in your content and get Bitcoin Lightning votes.



=== Upgrade Notice ===

Follow updates on plugin's GitHub page:

https://github.com/Coinsnap/Bitcoin-Voting

=== Frequently Asked Questions ===

Plugin's page on Coinsnap website: https://coinsnap.io/en/

=== Screenshots ===

 
=== Changelog ===

= 1.0.0 :: 2025-01-31 =
* Initial release.