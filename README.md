# Coinsnap Bitcoin Voting for WordPress

**Paid Bitcoin voting plugin for WordPress — create bot-resistant polls and collect Bitcoin or Lightning payments for every vote with Coinsnap or your own BTCPay Server.**

Coinsnap Bitcoin Voting turns a normal WordPress poll into a **pay-to-vote** interaction. Visitors select an option, pay a small Bitcoin amount, and their vote is counted after successful payment.

Because every vote requires a real payment, the plugin can reduce spam and automated bot voting while also creating a small revenue stream for publishers, communities, contests and interactive websites.

Payments can be made with **Bitcoin via Lightning or on-chain**.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Create paid Bitcoin polls directly in WordPress
- Accept **Lightning and Bitcoin on-chain** payments for votes
- Support for **Coinsnap** and **BTCPay Server**
- Up to **four answer options** per poll
- Define the **price per vote**
- Choose between **one vote per person** or **multiple votes**
- Configure a **voting duration**
- Show updated results after a successful vote
- Optional collection of voter information
- Configurable name, email, address and custom fields
- Multiple supported currencies
- Light and dark themes
- Shortcode-based placement anywhere in WordPress
- Payment confirmation after a successful vote
- Non-custodial / self-custody payment model
- Optional settlement to a bank account through supported Coinsnap partners

## Typical use cases

Bitcoin Voting can be used for:

- community polls
- fan voting
- contests
- feature prioritization
- content selection
- audience engagement
- paid surveys
- membership/community decisions
- bot-resistant online voting

This is not intended as a replacement for legally regulated elections or formal public-sector voting systems.

## Public WordPress.org release

The current public WordPress.org release is **1.3.0**.

WordPress.org currently reports the plugin as tested up to **WordPress 7.1.3**.

> **Developer note:** the current GitHub `main` branch still identifies the plugin as version **1.2.3** and contains older pre-1.3.0 implementation code. The GitHub code and plugin metadata should be synchronized with the WordPress.org 1.3.0 release separately. This README describes the current public plugin position while avoiding unsupported implementation claims where the GitHub source does not yet match the public release.

## How it works

1. Install and activate Coinsnap Bitcoin Voting.
2. Connect **Coinsnap** or your own **BTCPay Server**.
3. Create a poll.
4. Add up to four answer options.
5. Define the payment amount required for each vote.
6. Choose whether a visitor may vote once or multiple times.
7. Set the voting duration and optional voter-information fields.
8. Add the generated shortcode to a WordPress page or post.
9. A visitor selects an option and pays with Bitcoin.
10. After successful payment, the vote is recorded and the updated result is displayed.

## Poll configuration

Each poll can be configured with settings such as:

- Poll title
- Up to four voting options
- Price per vote
- Currency
- One-vote or multiple-vote mode
- Voting duration
- Light or dark theme
- Optional voter information
- Custom field
- Thank-you message
- Payment provider

## Shortcode

The current plugin registers the shortcode:

`[coinsnap_bitcoin_voting]`

Use the shortcode shown in the WordPress backend for the poll you want to publish.

## Coinsnap or BTCPay Server

The plugin supports two payment-provider modes:

- **Coinsnap** — for users who want to accept Bitcoin and Lightning without operating their own payment infrastructure.
- **BTCPay Server** — for users who want to connect their own self-hosted BTCPay Server.

The plugin includes BTCPay Server setup support.

## Bot-resistant paid voting

The plugin uses a simple economic barrier against automated voting: a vote is counted only after the required Bitcoin payment is completed.

This can make large-scale automated manipulation more expensive than a free web poll. It should not be interpreted as a guarantee that a poll is immune to manipulation, nor as identity verification.

## Voter information

Poll owners can optionally collect information such as:

- name
- email address
- address
- a custom field

Only collect personal data when it is necessary for your use case and when you have an appropriate legal basis.

## Settlement

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a **non-custodial / self-custody** approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be converted and settled to a bank account.

## Installation

### Install from WordPress.org

1. In WordPress, go to **Plugins → Add New**.
2. Search for **Coinsnap Bitcoin Voting**.
3. Click **Install Now** and activate the plugin.
4. Open **Bitcoin Voting → Settings**.
5. Connect Coinsnap or BTCPay Server.
6. Create a poll and configure its voting options.
7. Copy the poll shortcode to a page or post.
8. Make a test vote and payment before publishing.

### Install from GitHub

1. Download this repository as a ZIP archive.
2. Upload it through **Plugins → Add New → Upload Plugin**.
3. Install and activate the plugin.
4. Configure the payment provider and create a poll.

## Resources

- [Coinsnap Bitcoin Voting product page](https://coinsnap.io/wp-plugins/wp-bitcoin-voting/)
- [Bitcoin Voting User Guide](https://coinsnap.io/user-guides/wp-bitcoin-voting-user-guide/)
- [WordPress Plugin Directory](https://wordpress.org/plugins/coinsnap-bitcoin-voting/)
- [Live demo](https://voting.coinsnap.org/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### Can voters pay with both Lightning and Bitcoin on-chain?

Yes. The plugin supports both Lightning and Bitcoin on-chain payments.

### Can I use my own BTCPay Server?

Yes. BTCPay Server is supported as an alternative to Coinsnap.

### How many answer options can a poll have?

A poll can offer up to four answer options.

### Can I limit visitors to one vote?

Yes. Polls can be configured for one vote per person or multiple votes.

### Can I set a voting duration?

Yes. The plugin supports a configurable voting duration.

### Can I collect voter information?

Yes. Collection of voter information is optional.

### Does paying for a vote make the poll completely bot-proof?

No. Requiring payment creates an economic barrier against automated voting, but it does not provide formal identity verification or guarantee that manipulation is impossible.

### Do voters need a Coinsnap account?

No. Voters only need a compatible Bitcoin or Lightning wallet.

### Do I need my own Lightning node?

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

### Does Coinsnap hold my Bitcoin?

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

## Changelog

### 1.3.0 — 2026-07-02
- Added a payment confirmation page after successful vote payment.
- Integrated the shared Coinsnap core library for payment settings, transactions, logs, providers, webhooks, exchange rates and logging.
- Improved Coinsnap and BTCPay Server transaction links and provider handling.
- Fixed donor-record handling after successful payments.
- Improved REST API and webhook security.
- Improved BTCPay Server settings compatibility.
- Removed blocking payment-status polling behavior.

### 1.2.3
- Added order ID and standard invoice request fields.
- Improved plugin isolation in the WordPress backend.

### 1.2.2
- Updated the voter-information interface.

### 1.2.1
- Fixed WordPress Plugin Check warnings.

### 1.2.0
- Added payment gateway client class.
- Added support for multiple Coinsnap currencies.
- Added minimum-payment validation for Coinsnap and BTCPay Server.

### 1.1.0
- Added the BTCPay Server setup wizard.

### 1.0.0
- Initial release.
