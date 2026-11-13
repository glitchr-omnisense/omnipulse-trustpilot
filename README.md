# omnipulse/trustpilot

Formerly `omnireview/trustpilot` (moved on 2026-11-10, when omnireview and omnifund merged into
omnipulse: `Omnireview\Trustpilot\` is now `Omnipulse\Trustpilot\`, `TrustpilotGateway` is
`TrustpilotProvider`, `TrustpilotGatewayFactory` is `TrustpilotFactory`; this package replaces the
former name).

**Trustpilot** for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse): a business
unit's TrustScore, its number of reviews, its reviews and the links to its profile and to write
one; with a business user's access, replying to a review and inviting a customer to write one.

## Install and a first call

```sh
composer require omnipulse/trustpilot
```

No account for this first call: Trustpilot's answers is recorded
(`Omnipulse\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
A real call needs a Trustpilot API key (its developer portal); replies and invitations need the
business's own account besides (an API secret and a login).

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\Model\Place;
use Omnipulse\Trustpilot\TrustpilotFactory;
use Omnipulse\Testing\RecordedHttpClient;

// Trustpilot's answers, recorded: nothing sent. HttpClient::create() (symfony/http-client) for a real call.
$http = RecordedHttpClient::routes([
    'GET /web-links' => '{"locale": "fr-FR", "profileUrl": "https://fr.trustpilot.com/review/www.maison-erable.example", "evaluateUrl": "https://fr.trustpilot.com/evaluate/www.maison-erable.example"}',
    'GET /46d6a890000064000500e0c3' => '{"id": "46d6a890000064000500e0c3", "displayName": "Maison Érable", "score": {"stars": 4.5, "trustScore": 4.4}, "numberOfReviews": {"total": 469}}',
]);
$trustpilot = (new TrustpilotFactory($http))->create(['api_key' => 'recorded']);

$rating = $trustpilot->rating(new Place('46d6a890000064000500e0c3'));
echo $rating->value, ' from ', $rating->count, ' reviews, ', $rating->url, "\n";   // 4.4 from 469 reviews, https://fr.trustpilot.com/review/...
```

With your credentials, through the family's registry:

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

## Usage policy

Trustpilot's usage policy is declared as data in [`policy.json`](policy.json) (omnisense/contracts'
schema, named in `composer.json`'s `extra.omnisense.policy`), its terms read on
2026-11-10 (https://developers.trustpilot.com/rate-limiting): 833 calls per 5 minutes and 10,000 an hour (Trustpilot's recommended ceilings); what is shown kept 24 hours at most; "Trustpilot" credited on display. omnipulse's registry enforces it - a call over a
limit is never made, a 429 and its `Retry-After` hold every next call
(`UsageLimitReachedException`) - and a site may tighten it, never loosen it
(omnisense/omnipulse's docs/policies.md).

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
