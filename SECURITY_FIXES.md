# Security Fixes for Coinsnap Bitcoin Voting

## Critical Vulnerabilities Fixed

### 1. API Key Exposure in Page Source ⚠️ CRITICAL

**Problem:** API keys were embedded in JavaScript via `wp_localize_script()`, visible in page source.

**Files Modified:**
- `coinsnap-bitcoin-voting.php` (enqueue_scripts function)
- `includes/class-coinsnap-bitcoin-voting-client.php` (new REST endpoint)

**Changes:**
- ❌ Removed API keys from `wp_localize_script()` 
- ✅ Created server-side REST endpoint: `/wp-json/voting/v1/create-invoice`
- ✅ Frontend now sends `poll_id` and `option_id` only
- ✅ Server validates amount from post meta (never from client)
- ✅ Only WordPress nonce passed to frontend

**Before (VULNERABLE):**
```php
$sharedDataArray['coinsnapApiKey'] = $provider_options['coinsnap_api_key']; // Exposed!
wp_localize_script('shared-script', 'Coinsnap_Bitcoin_Voting_sharedData', $sharedDataArray);
```

**After (SECURE):**
```php
// API key stays on server only
$sharedDataArray = [
    'nonce' => wp_create_nonce('wp_rest'),
    'rest_url' => rest_url(),
];
// Client creates invoice via REST endpoint - amount from post meta
```

---

### 2. Private CPT Readable via REST API ⚠️ HIGH

**Problem:** `coinsnap-pds` and `coinsnap-polls` CPTs had `show_in_rest => true` without access control.

**Files Modified:**
- `includes/class-coinsnap-bitcoin-voting-polls.php`
- `includes/class-coinsnap-bitcoin-voting-public-donors.php`

**Changes:**
- ❌ Removed `show_in_rest => true` from CPT registration
- ✅ Added custom REST controller with authentication checks
- ✅ Only authenticated users can read private posts
- ✅ Donor names hidden from anonymous users

**Before:**
```php
register_post_type('coinsnap-pds', [
    'public' => false,
    'show_in_rest' => true,  // ← Exposed!
]);
```

**After:**
```php
register_post_type('coinsnap-pds', [
    'public' => false,
    'show_in_rest' => false,  // ✅ Hidden
    'rest_controller_class' => 'Coinsnap_Authenticated_Posts_Controller',
]);
```

---

### 3. CSRF on Callback Handler (No Nonce) ⚠️ CRITICAL

**Problem:** BTCPay callback handler accepted API keys without nonce verification.

**Files Modified:**
- `coinsnap-bitcoin-voting.php` (template_redirect handler)
- `vendor/src/Auth/class-btcpay-authorizer.php`

**Changes:**
- ❌ Removed self-minting nonce (always validated to itself)
- ✅ Added proper nonce tied to admin user
- ✅ Added `current_user_can('manage_options')` check
- ✅ Added transient-based auth session validation
- ✅ API key probe result must succeed before saving

**Before (VULNERABLE):**
```php
// Attacker sends unauthenticated POST
curl -X POST "https://target.com/?cbv-btcpay-callback=1" \
  -d "apiKey=ATTACKER_KEY" \
  # ← No nonce, no admin check!
```

**After (SECURE):**
```php
// Step 1: Admin initiates auth flow (nonce created)
if ( ! current_user_can( 'manage_options' ) ) { return; }
$nonce = wp_create_nonce( 'coinsnap-btcpay-' . get_current_user_id() );
set_transient( 'coinsnap_btcpay_auth_' . get_current_user_id(), 1, 15 * MINUTE_IN_SECONDS );

// Step 2: Only that admin can complete callback
if ( ! wp_verify_nonce( $nonce, 'coinsnap-btcpay-' . get_current_user_id() ) ) { return; }
if ( ! get_transient( 'coinsnap_btcpay_auth_' . get_current_user_id() ) ) { return; }
```

---

### 4. Invoice Amount Not Validated ⚠️ HIGH

**Problem:** Client could submit 1 satoshi for a $100 vote via arbitrary metadata.

**Files Modified:**
- `includes/class-coinsnap-bitcoin-voting-webhooks.php`
- `includes/class-coinsnap-bitcoin-voting-client.php` (new endpoint)

**Changes:**
- ❌ Removed ability for client to set amount/invoice metadata
- ✅ Server loads amount from poll post meta only
- ✅ Webhook validates amount matches expected price (±0.0001)
- ✅ Webhook validates option_id is 1-4 (valid range)
- ✅ Webhook validates poll is active before creating vote
- ✅ Added UNIQUE index on payment_id to prevent double-counting

**Before:**
```javascript
// ❌ VULNERABLE - Client sends amount
const requestData = {
    amount: amount,  // ← Attacker sets to 1 sat
    metadata: { pollId: 42, optionId: 1 }
};
```

**After:**
```php
// ✅ SECURE - Server reads amount from database
$amount = get_post_meta( $poll_id, '_coinsnap_bitcoin_voting_polls_amount', true );

// Webhook validates
if ( (float)$payload_data['amount'] + 0.0001 < $expected ) {
    return $resp;  // Reject underpaid invoice
}
```

---

## Implementation Steps

### Step 1: Update Main Plugin File
✅ File: `coinsnap-bitcoin-voting.php`
- Remove API keys from `$sharedDataArray`
- Update enqueue_scripts to only send nonce/urls
- Update callback handler with nonce + auth check

### Step 2: Create REST Endpoint for Invoice Creation
✅ File: `includes/class-coinsnap-bitcoin-voting-client.php`
- Register `POST /wp-json/voting/v1/create-invoice`
- Validate poll_id and option_id
- Load amount from post meta
- Return invoice URL/QR only

### Step 3: Update CPT Registration
✅ File: `includes/class-coinsnap-bitcoin-voting-polls.php`
✅ File: `includes/class-coinsnap-bitcoin-voting-public-donors.php`
- Remove `show_in_rest`
- Add authentication wrapper if REST needed

### Step 4: Strengthen Webhook Validation
✅ File: `includes/class-coinsnap-bitcoin-voting-webhooks.php`
- Validate amount against poll price
- Validate option_id in valid range
- Check poll is active
- Add UNIQUE index on payment_id

### Step 5: Update JavaScript
✅ File: `assets/js/shared.js` (existing)
- Remove reference to API keys
- Call new REST endpoint for invoice
- Handle response/errors

---

## Testing

### Test 1: API Key Not in Page Source
```bash
curl -s https://target.com/vote-page | grep -i "apikey\|api.key"
# Expected: (no output)
```

### Test 2: REST CPT Endpoint Forbidden
```bash
curl -s https://target.com/wp-json/wp/v2/coinsnap-pds
# Expected: 401 Unauthorized
```

### Test 3: Callback Requires Admin Nonce
```bash
# Attacker tries to post without nonce
curl -X POST "https://target.com/?cbv-btcpay-callback=1" -d "apiKey=FAKE"
# Expected: Redirects to home (ignores callback)
```

### Test 4: Underpaid Invoice Rejected
```php
// Webhook with 1 sat when poll expects $100
// Expected: Vote not recorded, order status not updated
```

---

## Deployment Checklist

- [ ] Backup database
- [ ] Apply SQL patch (add UNIQUE index on payment_id)
- [ ] Deploy code changes
- [ ] Regenerate API keys in Coinsnap/BTCPay dashboards
- [ ] Re-authenticate payment provider (create new nonce)
- [ ] Test full payment flow
- [ ] Verify no API keys in page source
- [ ] Verify old transactions still visible
- [ ] Clear browser cache (old JS may be cached)

---

## Severity Ratings

| Issue | CVSS | Impact |
|---|---|---|
| API Key Exposure | **9.1** | Attacker reads all invoices, modifies webhooks, creates fake votes |
| CPT REST Leak | **6.5** | Attacker enumerates all donor names + payment times |
| CSRF on Callback | **8.8** | Attacker hijacks payment provider, redirects funds |
| Amount Tampering | **7.2** | Attacker votes at 1 sat instead of full price (currently mitigated by webhook delivery bug) |

---

## References

- [WordPress Security Handbook](https://developer.wordpress.org/plugins/security/)
- [Nonce Verification](https://developer.wordpress.org/plugins/security/nonces/)
- [REST API Authentication](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [OWASP API Top 10](https://owasp.org/www-project-api-security/)
