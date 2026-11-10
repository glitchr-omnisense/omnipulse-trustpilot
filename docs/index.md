# omnipulse/trustpilot

Trustpilot for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse): a business
unit's TrustScore, its number of reviews, its reviews and the links to its profile and to write
one; with a business user's access, replying to a review and inviting a customer to write one.
The provider calls three Trustpilot hosts:

| Operation | API | Request |
|---|---|---|
| find (by domain) | public API | `GET https://api.trustpilot.com/v1/business-units/find?name=`, `apikey` |
| rating | Data Solutions API | `GET https://datasolutions.trustpilot.com/v1/business-units/{id}`, `apikey` |
| rating's link, write link | public API | `GET https://api.trustpilot.com/v1/business-units/{id}/web-links?locale=`, `apikey` |
| reviews | public API | `GET https://api.trustpilot.com/v1/business-units/{id}/reviews?perPage=&language=&orderBy=createdat.desc`, `apikey` |
| reply | private API | `POST https://api.trustpilot.com/v1/private/reviews/{reviewId}/reply`, Bearer |
| invite | Invitations API | `POST https://invitations-api.trustpilot.com/v1/private/business-units/{id}/email-invitations`, Bearer, `x-business-user-id` |
| token | OAuth | `POST https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/accesstoken`, `grant_type=client_credentials` |

The base addresses are the constants `TrustpilotProvider::API`, `DATA` and `INVITATIONS`. Trustpilot
has no sandbox for these APIs.

## Installation

```sh
composer require omnipulse/trustpilot
```

Requires PHP 8.2, `omnisense/omnipulse` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `OmnipulseBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- A **Trustpilot application** (Trustpilot's developer portal, with a business account): its
  **API key** for the TrustScore, the reviews and the links; its **API secret** for the business
  user's token. The APIs come with Trustpilot's paid plans, which decide which APIs a key opens.
- A **business user ID**: the user who replies and invites (sent as `authorBusinessUserId` and
  `x-business-user-id`).
- The **business unit ID** of the establishment: found once with `find('<domain>')`, then kept.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | sent as the `apikey` header, and the client id of the token |
| `locale` | string | `fr-FR` | no | the `locale` of `/web-links`: the profile's and the write link's language; an invitation is sent in its own `Invitation::$locale` (the customer's language, `fr-FR` by default), never this option |
| `api_secret` | string | `null` | for reply and invite | |
| `business_user_id` | string | `null` | for reply and invite | |
| `template_id` | string | `null` | no | the invitation's `serviceReviewInvitation.templateId` |
| `reply_to` | string | `null` | no | the invitation's `replyTo` |
| `sender_name` | string | `null` | no | the invitation's `senderName` |
| `sender_email` | string | `null` | no | the invitation's `senderEmail` |

`Place` fields read: `id` (the business unit ID). `Invitation` fields: all of them
([below](#behaviour)).

## Capabilities

| Capability | Supported |
|---|---|
| `RatingInterface` | yes |
| `ReviewsInterface` | yes, 100 at most per call |
| `ReplyInterface` | only with `api_secret` and `business_user_id` |
| `InviteInterface` | only with `api_secret` and `business_user_id` |
| `WriteUrlInterface` | yes |

`capabilities()->contracts()`: `rating, reviews, writeUrl`, and `reply, invite` with the business
user. `find(string $domain): Place` is the provider's own method, outside the contract.

## Behaviour

**Find.** `GET .../business-units/find?name=<domain>`: a `Place` with `id` and `name` (the
`displayName`, else the domain). An answer without `id` is a `ProviderException` (`No business unit
for "<domain>".`).

**Rating.** Two calls: the Data Solutions business unit (`value` `score.trustScore`, `count`
`numberOfReviews.total`, or `numberOfReviews` when it is a number), then `/web-links` for `url`
(`profileUrl`).

**Reviews.** `perPage` = `$limit` between 1 and 100, `language` when given (a filter, left out when
`null`), newest first. Each review: `id`, `rating` (`stars`), `text`, `title`, `language`,
`author` (`consumer.displayName`), `publishedAt` (`createdAt`), `reply` from `companyReply`
(`text`, `createdAt`), `url` built as `https://www.trustpilot.com/reviews/<id>` (the review's
public page; the API's own `links` point to the API). One page only.

**Reply.** `POST .../private/reviews/{id}/reply` with `{"authorBusinessUserId": ..., "message":
text}`. The `Reply` is the text sent, dated now (Trustpilot's answer is not read).

**Invite.** `POST .../email-invitations` with `type: email`, `consumerEmail`, `consumerName`,
`locale` (`Invitation::$locale`), `referenceNumber` (`reference`), `replyTo`, `senderName`,
`senderEmail` (the options), and `serviceReviewInvitation` with `templateId`,
`preferredSendTime` (`sendAt` in UTC, `Y-m-d\TH:i:s`) and `redirectUri` (`redirectUrl`); every
key left out when empty. It returns the invitation's `id`, or `null`.

**Token.** Asked on the first reply or invitation: HTTP basic `api_key:api_secret`,
`grant_type=client_credentials`; kept in the provider until 60 seconds before it expires
(`expires_in`, 3600 when Trustpilot does not say), then asked again (no refresh token).

**Every call** goes through `Answer::send()`, with a 10-second timeout.

**Terms** (`terms()`), from Trustpilot's caching best practices
([developers.trustpilot.com/ds-caching-best-practices](https://developers.trustpilot.com/ds-caching-best-practices/))
and rate limits, read on 2026-10-08:

| | |
|---|---|
| `cacheFor` | `86400`: what is shown refreshed every 24 hours at the latest |
| `maxReviews` | `100` |
| `attribution` | `Trustpilot` |
| `authors`, `link` | `true` |
| `noIndex` | `false` |

and in `rules`: the business unit ID may be kept; under 833 calls in 5 minutes and 10,000 in an
hour - keep the data in the back end rather than calling from every page view. Trustpilot also
forbids inviting only the customers likely to be pleased: invite all alike.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\Model\Invitation;
use Omnipulse\Model\Place;
use Omnipulse\Trustpilot\TrustpilotFactory;
use Symfony\Component\HttpClient\HttpClient;

$trustpilot = (new TrustpilotFactory(HttpClient::create()))->create([
    'api_key' => getenv('TRUSTPILOT_API_KEY'),
    'api_secret' => getenv('TRUSTPILOT_API_SECRET'),
    'business_user_id' => getenv('TRUSTPILOT_BUSINESS_USER_ID'),
    'sender_name' => 'Maison Érable',
    'reply_to' => 'contact@maison-erable.example',
]);

$unit = $trustpilot->find('www.maison-erable.example');     // once: keep $unit->id
$rating = $trustpilot->rating($unit);
printf("TrustScore %.1f from %d reviews - %s\n", $rating->value, $rating->count, $rating->url);

foreach ($trustpilot->reviews(new Place($unit->id), 20, 'fr') as $review) {
    if (null === $review->reply) {
        $trustpilot->reply(new Place($unit->id), $review, 'Merci pour votre avis !');
    }
}

$id = $trustpilot->invite(new Place($unit->id), new Invitation('camille@example.org', 'Camille R.', 'ORDER-42', 'fr-FR', new DateTimeImmutable('+3 days')));
echo $trustpilot->writeUrl($unit), ' ', $id, "\n";
```

Not run live (no key). In Symfony:

```yaml
omnipulse:
    providers:
        trustpilot:
            factory: trustpilot
            options:
                api_key: '%env(TRUSTPILOT_API_KEY)%'
                api_secret: '%env(default::TRUSTPILOT_API_SECRET)%'
                business_user_id: '%env(default::TRUSTPILOT_BUSINESS_USER_ID)%'
```

## Errors

| Case | Exception | `providerCode` |
|---|---|---|
| HTTP 401 or 403 | `InvalidKeyException`, `Refused (HTTP <status>): <body>` | the HTTP status |
| another 4xx | `ProviderException`, `<METHOD> <path>: HTTP <status>, <message or details>` | the HTTP status |
| the token refused | `InvalidKeyException`, `The API key and secret were refused for a token.` | the HTTP status |
| no answer, a 5xx, a 429 | `UnreachableException` | the HTTP status |
| a body that is not JSON | `ProviderException` | - |
| no business unit for the domain, no `evaluateUrl` | `ProviderException` | - |
| `reply()`, `invite()` without the business user | `NotSupportedException`, before any call | - |
| no `api_key` | `InvalidConfigException`, when the provider is built | - |

## Verified, and not

Tested offline (`Tests/TrustpilotProviderTest.php`, on `MockHttpClient`): `find()` by domain, the
TrustScore and count from Data Solutions with the `apikey` header, the profile and write links,
the reviews' query and their public URL and reply; the token's Basic header, the reply's body, the
invitation's URL, `x-business-user-id` header and body, the capabilities with a business user;
`NotSupportedException` without one. The answers in `Tests/Fixtures` are written from Trustpilot's
API reference (business units, reviews, web links, the token, the invitation), not captured.

Not verified: **no call was made to Trustpilot** - no application. Whether the Data Solutions API
answers with the plain API key is unknown until tried: Trustpilot's plans decide.

## Responsibility

An independent, unofficial client, not affiliated with Trustpilot A/S. Whoever runs it complies
with Trustpilot's terms ([Terms of Use and Sale for
Businesses](https://legal.trustpilot.com/for-businesses/terms-of-use-and-sale-for-businesses)): see
the README's Responsibility section.
