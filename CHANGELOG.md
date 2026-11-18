# Changelog

All notable changes to the Chain2Pay WHMCS gateway modules are listed here.

## Unreleased

- Module descriptions (`whmcs.json`) reworded. No change to the PHP code.

## 2.3.0

Current public release. The installable archive is attached to the
[v2.3.0 release](https://github.com/chain2pay-is/chain2pay-whmcs/releases/tag/v2.3.0)
and is the same file that merchants download from the Chain2Pay dashboard.

- One WHMCS gateway module per supported provider, plus a hosted
  multi-provider checkout and a custom-provider module (see
  `README-custom-provider.md`).
- Invoice marked as paid from the signed Chain2Pay callback
  (`callback/` directory).

The PHP source in this repository is the unpacked content of that archive.
