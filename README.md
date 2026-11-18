# Chain2Pay for WHMCS

Official WHMCS payment gateway module for [Chain2Pay](https://chain2pay.is), the card-to-crypto payment gateway operated by Isthmus Business Group S.A.

The module adds Chain2Pay payment gateways to WHMCS invoices. The client pays through a third-party on-ramp provider, and the invoice is updated when the payment is confirmed.

| | |
|---|---|
| Version | 2.3.0 |
| License | Proprietary, as declared in the module files |
| Download | [Releases](https://github.com/chain2pay-is/chain2pay-whmcs/releases) or the merchant dashboard |
| Documentation | https://docs.chain2pay.is |
| Pricing | https://chain2pay.is/pricing |
| Support | support@chain2pay.is |

## Install

Step 1. Download `Chain2Pay-V2.3.0-WHMCS.zip` from the Releases page and extract it.

Step 2. Copy the contents of the `chain2pay-whmcs` folder into the `modules/gateways` directory of your WHMCS installation. The `callback` folder goes to `modules/gateways/callback`.

Step 3. In the WHMCS admin area, open the Payment Gateways settings, activate the Chain2Pay gateways you want and enter your Chain2Pay API key.

Your API key is in the merchant dashboard at https://dashboard.chain2pay.is.

## Official sources

This module is published only in this repository and in the Chain2Pay merchant dashboard. The list of official Chain2Pay websites is at https://chain2pay.is/official-domains.
