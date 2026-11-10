# Changelog

## [Unreleased]

### Changed
- `omnireview/trustpilot` is now `omnipulse/trustpilot` (2026-11-10: omnireview and omnifund merged
  into omnipulse, Marco's decision; this repository's history kept, from omnireview-trustpilot at
  16dd5a5): the namespace `Omnipulse\Trustpilot`, requiring `omnisense/omnipulse`; `composer.json`
  replaces `omnireview/trustpilot`, so that a project never installs both. `TrustpilotGateway` is
  `TrustpilotProvider`, `TrustpilotGatewayFactory` is `TrustpilotFactory`, implementing the core's
  `ProviderInterface` and `ProviderFactory` (were `GatewayInterface` and `GatewayFactory`); the
  factory's name is `trustpilot`. Its Terms are unchanged.
- The decoded answers are read with types (`Omnipulse\Support\Data`): the same values, an
  item of a list that is not an object skipped where it stopped the call.

### Added
- CI (glitchr-studio/ci's `php-package.yml`), PHPStan at level max with an empty baseline,
  `phpunit.xml.dist`, and `Tests/BareTest.php`: every class loads with nothing from outside but
  `omnisense/omnipulse` and Symfony's HTTP client contracts.

### Fixed

- The factory's docblock said the `locale` option is also the invitations' language: it is the
  locale of `/web-links` only, and an invitation is sent in its own `Invitation::$locale` (the
  customer's language). No behaviour changed.
