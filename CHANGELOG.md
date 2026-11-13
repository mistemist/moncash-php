# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2026-11-13

### Added

- Initial release.
- OAuth2 client-credentials authentication with token caching and early refresh.
- `createPayment()` returning a payment token and MonCash gateway redirect URL.
- `retrieveOrderPayment()` and `retrieveTransactionPayment()` for server-side verification.
- Sandbox and production environments.
- Offline PHPUnit test suite using an injectable HTTP transport.
