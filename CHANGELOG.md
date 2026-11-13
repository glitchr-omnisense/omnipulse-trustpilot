# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0`, `omnisense/omnipulse` `^1.0` with no stability flag, so `composer require omnipulse/trustpilot` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

### Changed
- Token requests counted: the business user's client-credentials request goes through the provider's guard, checked before it is sent (a
  site's limit naming `token`, the provider's waits) and counted after, in the registry's limiter.
  `TrustpilotFactory` gives the provider its guard (`Config::guard()`, the constructor's last argument `tokens`): each token request is counted under `token`, and under Trustpilot's 833 calls per 5 minutes and 10,000 an hour, which count every call. Needs omnisense/contracts 16c8da1 or later (`Config::guard()`; 1568f2c, what the sites'
  vendors hold, has it). Tests/TrustpilotProviderTest: a site's limit of 0 tokens a day, the token request never sent.

### Added
- Trustpilot's usage policy declared as data: `policy.json` (omnisense/contracts' schema), its terms
  read on 2026-11-10, named in `composer.json` (`extra.omnisense.policy`, and
  `omnisense/contracts` required). The provider implements `DeclaresUsagePolicyInterface` and gives
  the policy to its Capabilities; the factory hands the provider's Backoff to its HTTP calls, so
  that a 429 and its `Retry-After` hold every next call. `Tests/PolicyTest.php`.

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
