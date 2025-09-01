# CHANGELOG

## 2.0.1

Hygiene release for compatibility up to PHP 8.5.

- Implicitly nullable params are now explicitly nullable.
- #[ReturnTypeWillChange] attributes added as appropriate.
- Upgrade PHPStan to 1.0, modify phpstan.neon to ignore older notices.
- Stringify fetches in tests for consistency across PHP versions.
- Add Github workflow.
- Fix link in docs.
- Use ::class instead of ::CLASS.

## 2.0.0

Initial release.
