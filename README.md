# omnipulse/trustpilot

Formerly `omnireview/trustpilot` (moved on 2026-11-10, when omnireview and omnifund merged into
omnipulse: `Omnireview\Trustpilot\` is now `Omnipulse\Trustpilot\`, `TrustpilotGateway` is
`TrustpilotProvider`, `TrustpilotGatewayFactory` is `TrustpilotFactory`; this package replaces the
former name).

**Trustpilot** for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse): a business
unit's TrustScore, its number of reviews, its reviews and the links to its profile and to write
one; with a business user's access, replying to a review and inviting a customer to write one.

```php
use Omnipulse\Trustpilot\TrustpilotFactory;

$provider = (new TrustpilotFactory($httpClient))->create(['api_key' => getenv('TRUSTPILOT_API_KEY')]);

$unit = $provider->find('www.maison-erable.example');  // once: keep its ID
$provider->rating($unit);                              // the TrustScore, the number of reviews, the profile
$provider->reviews($unit, 10);
$provider->writeUrl($unit);                            // the profile's evaluate link
```

Written from Trustpilot's developer documentation, read on 2026-10-08. **Not verified in real:
no key.**

[Documentation](docs/index.md): the options, the calls, Trustpilot's terms, what was verified.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Trustpilot A/S. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Trustpilot's terms ([Terms
of Use and Sale for
Businesses](https://legal.trustpilot.com/for-businesses/terms-of-use-and-sale-for-businesses)), for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or a
citation, a rate limit to respect), and for any use made of what it returns. The authors publish it
free of charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
