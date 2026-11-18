# Chain2Pay — Custom Provider (WHMCS)

A single, configurable payment gateway that can route invoices to **any**
Chain2Pay provider. Instead of activating a dedicated module per provider, you
type the provider's slug and the module calls the Chain2Pay API with it.

## When to use it

- A provider you want isn't shipped as its own module.
- You want to switch the active provider without activating another module.

Dedicated modules (MoonPay, Revolut, Coinbase, …) still work as before — the
Custom Provider module is a flexible alternative that supports all of them.

## Setup

1. Upload the `chain2pay_custom.php` module to `modules/gateways/` and the
   callback `chain2pay_custom.php` to `modules/gateways/callback/` (already
   included in this package).
2. In WHMCS, go to **Setup → Payments → Payment Gateways → All Payment
   Gateways** and activate **Chain2Pay Custom Provider**.
3. Open **Manage** and fill in:
   - **Provider slug** — the provider to use, all lowercase. Examples:
     `multihosted`, `crypto`, `revolut`, `moonpay`, `coinbase`, `alchemy`, `alchemylocal`, `banxa`, `blockchain`, `ramp`, `robinhood`, `stixramp`, `paypal`, `stripe`, `skrill`, `btcdirect`, `coindisco`.
   - **Description** — what the buyer sees at checkout.
   - **Chain2Pay API Key** — your `c2p_live_...` or `c2p_sandbox_...` key.
     The same key works for every Chain2Pay gateway; if left empty it falls
     back to the key configured on the main Chain2Pay (multihosted) module.
4. Save.

## Notes

- The slug is always sent lowercase. An unknown slug returns a clean error
  from the API; a valid one returns the checkout link immediately.
- Minimum amount, signed callbacks and the 60% under-payment safeguard work
  exactly like the other Chain2Pay modules.
- Transient connection blips are retried automatically (up to 3 attempts with
  backoff) before any "connection failed" error is shown.
