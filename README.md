# Coinsnap Bitcoin Voting

Contributors: coinsnap
Tags: Lightning, SATS, bitcoin, voting, polling, BTCPay
Tested up to: 6.8
Stable tag: 1.1.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect small Bitcoin (Satoshi) payments for every vote on your WordPress site. Great for monetized polls, community engagement and SPAM-free surveys



### Coinsnap Bitcoin Voting – Earn Sats with Every Vote

Turn votes into Bitcoin: The Coinsnap Bitcoin Voting plugin lets you collect small Bitcoin (Satoshi) payments for every vote on your WordPress site. Great for monetized polls, community engagement, and SPAM-free surveys.

Visitors vote by paying a small amount of sats (you define the amount). This:

* **Prevents bots** from distorting the result
* **Creates a small income stream** for your site
* **Makes each vote count** (literally)

Coinsnap Bitcoin Voting works with Coinsnap or your own BTCPay Server.



### Requirements:

* A WordPress website
* The Coinsnap Bitcoin Donation plugin
* A [Coinsnap account](https://app.coinsnap.io/register) or your own BTCPay Server

### Features \& functions:

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



### Quick setup:

* Install plugin directly via the WordPress plugin directory
* Configure with just a few clicks
* And that's it!



### Two operating modes:

* Use Coinsnap (no technical know-how required)
* Or use your own BTCPay server (for advanced users)



### Why Coinsnap Bitcoin Voting?

* Open source and free in the WordPress Plugin Directory
* No programming knowledge required
* Immediate credit to your Bitcoin wallet
* GDPR-friendly: no unnecessary data storage
* Continuous further development
* Strong support through our support team, accessible in your Coinsnap account



### More information

* Live demo: [https://voting.coinsnap.org/](https://voting.coinsnap.org/)
* Product page: [https://coinsnap.io/coinsnap-bitcoin-voting-plugin/](https://coinsnap.io/coinsnap-bitcoin-voting-plugin/)
* Installation Guide: [https://coinsnap.io/coinsnap-bitcoin-voting-installation-guide/](https://coinsnap.io/coinsnap-bitcoin-voting-installation-guide/)
* Github plugin page: [https://github.com/Coinsnap/bitcoin-voting/](https://github.com/Coinsnap/bitcoin-voting/)



### Documentation:

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/)
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)



## Installation

### 1\. Install the Coinsnap Bitcoin Voting plug-in from the WordPress directory.

The Coinsnap Bitcoin Voting can be searched and installed in the WordPress plugin directory.

In your WordPress instance, go to the Plugins > Add New section.
In the search you enter Coinsnap and get as a result the Coinsnap Bitcoin Voting plugin displayed.

Then click Install.

After successful installation, click Activate and then you can start setting up the plugin.



### 2\. Connect Coinsnap account with Coinsnap Bitcoin Voting plugin

After you have installed and activated the Coinsnap Bitcoin Voting plugin, you need to set Coinsnap or BTCPay server up. You can find Coinsnap Bitcoin Voting settings in the sidebar on the left under “Bitcoin Voting”.

To set up Bitcoin Lightning voting, please enter your Coinsnap Store ID and your API key besides the other parameters there; you can find these in your Coinsnap account under “Settings -> Store”, “Coinsnap Shop”.

If you don’t have a Coinsnap account yet, you can do so via the link shown: [Coinsnap Registration](https://app.coinsnap.io/register).



### 3\. Create Coinsnap account

### 3.1. Create a Coinsnap Account

Now go to the Coinsnap website at: [https://app.coinsnap.io/register](https://app.coinsnap.io/register) and open an account by entering your email address and a password of your choice.

If you are using a Lightning Wallet with Lightning Login, then you can also open a Coinsnap account with it.

### 3.2. Confirm email address

You will receive an email to the given email address with a confirmation link, which you have to confirm. If you do not find the email, please check your spam folder.

Then please log in to the Coinsnap backend with the appropriate credentials.

### 3.3. Set up website at Coinsnap

After you sign up, you will be asked to provide two pieces of information.

In the Website Name field, enter the name of your online store that you want customers to see when they check out.

In the Lightning Address field, enter the Lightning address to which the Bitcoin and Lightning transactions should be forwarded.

A Lightning address is similar to an e-mail address. Lightning payments are forwarded to this Lightning address and paid out. If you don’t have a Lightning address yet, set up a Lightning wallet that will provide you with a Lightning address.

For more information on Lightning addresses and the corresponding Lightning wallet providers, click here:
https://coinsnap.io/lightning-wallet-mit-lightning-adresse/

After saving settings you can use Store ID and Api Key on the step 2.



### 4\. Configure Coinsnap Bitcoin Voting plugin

### 4.1. Voting shortcode

Go to "Bitcoin Voting" in the sideboard on the left in your WordPress and click on "Bitcoin Voting". At the top of the page you will find shortcode \[bitcoin\_voting] that you can use it in your content.

### 4.2. Configure your settings

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



## Upgrade Notice

Follow updates on plugin's GitHub page:

https://github.com/Coinsnap/Bitcoin-Voting

## Frequently Asked Questions

Plugin's page on Coinsnap website: https://coinsnap.io/en/

## Changelog

#### 1.0.0 :: 2025-01-31

* Initial release.

#### 1.1.0 :: 2025-06-18

* Update: BTCPay setup wizard is added in BTCPay server settings.

#### 1.2.0 :: 2025-09-04

* \* Update: Added payment gateway client class. 
* \* Update: Added support for all the Coinsnap currencies instead of SATS only
* \* Update: Prevented redirect to payment gateway if payment amount is less than 1 SAT or currency in not supported by Coinsnap.
* \* Update: Prevented redirect to BTCPay server if payment amount is less than 0.000005869 BTC (0.50 EUR) for onchain payments, 0.000001 BTC (1 SAT) for Lightning payment or currency is not supported.
* \* Update: Minimum order amount is added to connection status notice.
