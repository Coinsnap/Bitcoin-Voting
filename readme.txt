=== Coinsnap Bitcoin Voting ===

Contributors: coinsnap
Tags: bitcoin, lightning, voting, polling, btcpay
Tested up to: 7.1.3
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create paid Bitcoin polls on WordPress. Collect Lightning or on-chain payments for every vote with Coinsnap or your own BTCPay Server.

== Description ==

= Paid Bitcoin voting directly on WordPress =

Coinsnap Bitcoin Voting turns a normal WordPress poll into a **pay-to-vote** interaction.

Visitors choose an option, pay a small Bitcoin amount, and the vote is counted after successful payment. Requiring a payment creates an economic barrier against spam and automated bot voting while also giving publishers and communities a way to monetize participation.

Payments can be made with **Bitcoin via Lightning or on-chain**.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

= Key features =

* Create paid Bitcoin polls in WordPress
* Accept **Lightning and Bitcoin on-chain** payments for votes
* Support for **Coinsnap** and **BTCPay Server**
* Up to **four answer options** per poll
* Define the **price per vote**
* Choose between **one vote per person** or **multiple votes**
* Configure a **voting duration**
* Display updated poll results after payment
* Optional collection of voter information
* Name, email, address and custom-field options
* Multiple supported currencies
* Light and dark themes
* Shortcode-based placement
* Payment confirmation after successful voting payment
* Non-custodial / self-custody payment model
* Optional bank settlement through supported Coinsnap partners

= Typical use cases =

Use Bitcoin Voting for:

* community polls
* fan voting
* contests
* feature prioritization
* content selection
* paid surveys
* audience engagement
* community decisions
* bot-resistant online voting

This plugin is not intended as a replacement for legally regulated elections or formal public-sector voting systems.

= How it works =

1. Install and activate the plugin.
2. Connect Coinsnap or your own BTCPay Server.
3. Create a poll.
4. Add up to four answer options.
5. Define the Bitcoin payment amount required for a vote.
6. Choose one-vote or multiple-vote mode.
7. Set the voting duration and optional voter information.
8. Add the poll shortcode to a page or post.
9. A visitor selects an option and pays with Bitcoin.
10. The vote is recorded after successful payment and the updated result is shown.

= Poll settings =

Each poll can include:

* Poll title
* Up to four answer options
* Price per vote
* Currency
* One-vote or multiple-vote mode
* Voting duration
* Light or dark theme
* Optional voter information
* Custom field
* Thank-you message
* Payment provider

= Shortcode =

The plugin uses:

[coinsnap_bitcoin_voting]

Use the shortcode displayed in the WordPress backend for the poll you want to publish.

= Coinsnap or BTCPay Server =

The plugin supports:

* **Coinsnap** — accept Bitcoin and Lightning without operating your own payment infrastructure.
* **BTCPay Server** — connect your own self-hosted BTCPay Server.

= Bot-resistant paid voting =

A vote is counted only after the required Bitcoin payment is completed.

This creates an economic barrier against automated voting, but it does not provide identity verification and should not be understood as a guarantee that manipulation is impossible.

= Voter information =

Voter information is optional. Depending on the poll configuration, you can request information such as name, email address, address or a custom field.

Only collect personal data when it is necessary and when you have an appropriate legal basis.

= Settlement =

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be converted and settled to a bank account.

= More information =

* Product page: https://coinsnap.io/wp-plugins/wp-bitcoin-voting/
* User Guide: https://coinsnap.io/user-guides/wp-bitcoin-voting-user-guide/
* WordPress Plugin Directory: https://wordpress.org/plugins/coinsnap-bitcoin-voting/
* Demo: https://voting.coinsnap.org/
* GitHub: https://github.com/Coinsnap/Bitcoin-Voting
* Coinsnap Wallet: https://coinsnap.io/wallet/
* Coinsnap API: https://docs.coinsnap.io/
* Coinsnap account: https://app.coinsnap.io/register

== Installation ==

1. In WordPress, go to **Plugins → Add New**.
2. Search for **Coinsnap Bitcoin Voting**.
3. Click **Install Now** and activate the plugin.
4. Go to **Bitcoin Voting → Settings**.
5. Select Coinsnap or BTCPay Server and connect the provider.
6. Create a poll.
7. Configure answer options, price per vote, voting duration and optional voter fields.
8. Copy the poll shortcode to a page or post.
9. Make a test vote and Bitcoin payment.

== Frequently Asked Questions ==

= Can voters pay with both Lightning and Bitcoin on-chain? =

Yes. The plugin supports both Lightning and Bitcoin on-chain payments.

= Can I use my own BTCPay Server? =

Yes. BTCPay Server is supported as an alternative to Coinsnap.

= How many answer options can a poll have? =

A poll can offer up to four answer options.

= Can I limit visitors to one vote? =

Yes. Polls can be configured for one vote per person or multiple votes.

= Can I set a voting duration? =

Yes. Voting duration is configurable.

= Can I collect voter information? =

Yes. Voter information is optional.

= Does paying for a vote make the poll completely bot-proof? =

No. Requiring payment creates an economic barrier against automated voting, but does not provide formal identity verification or guarantee that manipulation is impossible.

= Do voters need a Coinsnap account? =

No. Voters only need a compatible Bitcoin or Lightning wallet.

= Do I need my own Lightning node? =

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

= Does Coinsnap hold my Bitcoin? =

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

== Screenshots ==

1. Install Coinsnap Bitcoin Voting from the WordPress Plugin Directory
2. Coinsnap payment-provider settings
3. BTCPay Server payment-provider settings
4. BTCPay Server setup wizard
5. Polls list
6. Poll settings
7. Voter-information settings
8. Paid Bitcoin voting form
9. Bitcoin and Lightning payment QR code
10. Payment confirmation
11. Updated poll results

== Changelog ==

= 1.3.0 :: 2026-07-02 =
* Added Payment Confirmation page after successful vote payment.
* Integrated the shared Coinsnap core library for settings, transactions, logs, payment providers, webhooks, exchange rates and logging.
* Fixed Active and One Vote admin-column symbols.
* Fixed empty Thank You Message column output.
* Fixed provider handling in the Transactions list.
* Fixed Coinsnap and BTCPay Server invoice links.
* Fixed donor records after successful payments.
* Fixed REST API URL handling.
* Improved translation calls.
* Fixed BTCPay Server settings compatibility.
* Replaced blocking payment-status polling with a single database status query.
* Improved REST endpoint permissions.
* Added the submenu alias required for the BTCPay OAuth callback.

= 1.2.3 =
* Added order ID and standard invoice request fields.
* Improved plugin isolation from other Coinsnap plugins.

= 1.2.2 =
* Updated the voter-information interface.

= 1.2.1 =
* Fixed WordPress Plugin Check warnings.

= 1.2.0 =
* Added payment gateway client class.
* Added support for multiple Coinsnap currencies.
* Added minimum-payment validation for Coinsnap and BTCPay Server.

= 1.1.0 =
* Added the BTCPay Server setup wizard.

= 1.0.0 =
* Initial release.
