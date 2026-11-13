# Contributing

Thanks for helping improve MonCash integration for Haitian developers!

## Principles

- **Zero runtime dependencies.** The SDK must keep working with nothing but
  PHP 8.1+ and cURL. Dev tools (PHPUnit) are fine.
- **Money safety first.** Never suggest that a gateway redirect proves a
  payment; server-side verification is the only source of truth.
- **Test offline.** All tests must run without network access, using the
  injectable HTTP transport.

## Workflow

1. Fork the repository and create a feature branch.
2. Make your change, add or update tests, and run `vendor/bin/phpunit`.
3. Open a pull request explaining the change and, if it touches the API
   surface, what part of the MonCash API it maps to.

## Reporting bugs

Open an issue with your PHP version, whether you are on the sandbox or in
production, and a redacted sample of the API response. **Never** post real
credentials or customer data.
