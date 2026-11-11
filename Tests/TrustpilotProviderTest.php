<?php

namespace Omnipulse\Trustpilot\Tests;

use Omnipulse\Exception\NotSupportedException;
use Omnipulse\Model\Invitation;
use Omnipulse\Model\Place;
use Omnipulse\Model\Review;
use Omnipulse\Trustpilot\TrustpilotProvider;
use Omnipulse\Trustpilot\TrustpilotFactory;
use Omnipulse\Exception\UsageLimitReachedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers written from Trustpilot's developer documentation (Data
 * Solutions, Business Units, Service Reviews, Invitations, client
 * credentials): no key was used.
 */
final class TrustpilotProviderTest extends TestCase
{
    private const UNIT = '46d6a890000064000500e0c3';

    /** @var list<array{string, string, array<mixed>}> */
    private array $calls = [];

    /**
     * @param list<string|MockResponse> $answers
     * @param array<string, mixed>      $options
     */
    private function provider(array $answers, array $options = []): TrustpilotProvider
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->calls[] = [$method, $url, $options];
            $answer = array_shift($answers) ?? throw new \LogicException('No answer left for '.$url);
            if ($answer instanceof MockResponse) {
                return $answer;
            }

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$answer.'.json'));
        });
        $provider = (new TrustpilotFactory($http))->create($options + ['api_key' => 'tp-key']);
        self::assertInstanceOf(TrustpilotProvider::class, $provider);

        return $provider;
    }

    public function testTheTrustScoreTheReviewsAndTheLinks(): void
    {
        $provider = $this->provider(['find', 'business-unit', 'web-links', 'reviews', 'web-links']);
        $place = $provider->find('www.maison-erable.example');
        self::assertSame([self::UNIT, 'Maison Érable'], [$place->id, $place->name]);
        self::assertSame('https://api.trustpilot.com/v1/business-units/find?name=www.maison-erable.example', $this->calls[0][1]);

        $rating = $provider->rating($place);
        self::assertSame([4.4, 469, 'https://fr.trustpilot.com/review/www.maison-erable.example'], [$rating->value, $rating->count, $rating->url]);
        self::assertSame('https://datasolutions.trustpilot.com/v1/business-units/'.self::UNIT, $this->calls[1][1]);
        self::assertStringContainsString('apikey: tp-key', self::headers($this->calls[1][2]));

        $review = $provider->reviews($place, 3, 'fr')[0];
        self::assertSame(['507f191e810c19729de860ea', 5.0, 'Parfait', 'Camille R.', 'Merci Camille !'], [$review->id, $review->rating, $review->title, $review->author, $review->reply?->text]);
        self::assertSame('https://www.trustpilot.com/reviews/507f191e810c19729de860ea', $review->url, 'the public page, not the API');
        self::assertSame('https://api.trustpilot.com/v1/business-units/'.self::UNIT.'/reviews?perPage=3&language=fr&orderBy=createdat.desc', $this->calls[3][1]);
        self::assertSame('https://fr.trustpilot.com/evaluate/www.maison-erable.example', $provider->writeUrl($place));
        self::assertSame(86400, $provider->terms()->cacheFor);
    }

    public function testRepliesAndInvitationsWithTheBusinessUsersToken(): void
    {
        $provider = $this->provider(['token', new MockResponse('', ['http_code' => 201]), 'invitation'], ['api_secret' => 'tp-secret', 'business_user_id' => 'bu-1', 'template_id' => 'tpl-1', 'sender_name' => 'Maison Érable', 'reply_to' => 'contact@maison-erable.example']);
        $place = new Place(self::UNIT);

        $provider->reply($place, new Review('trustpilot', '507f191e810c19729de860ea', 5), 'Merci !');
        self::assertSame('https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/accesstoken', $this->calls[0][1]);
        self::assertStringContainsString('Authorization: Basic '.base64_encode('tp-key:tp-secret'), self::headers($this->calls[0][2]));
        self::assertSame(['authorBusinessUserId' => 'bu-1', 'message' => 'Merci !'], json_decode(self::body($this->calls[1][2]), true));

        $id = $provider->invite($place, new Invitation('camille@example.org', 'Camille R.', 'ORDER-42', 'fr-FR', new \DateTimeImmutable('2026-10-10 10:00', new \DateTimeZone('Europe/Paris'))));
        self::assertSame('f4c3b2a1-0000-4000-8000-000000000001', $id);
        self::assertSame('https://invitations-api.trustpilot.com/v1/private/business-units/'.self::UNIT.'/email-invitations', $this->calls[2][1]);
        self::assertStringContainsString('x-business-user-id: bu-1', self::headers($this->calls[2][2]));
        self::assertSame(['replyTo' => 'contact@maison-erable.example', 'locale' => 'fr-FR', 'senderName' => 'Maison Érable', 'referenceNumber' => 'ORDER-42', 'consumerName' => 'Camille R.', 'consumerEmail' => 'camille@example.org', 'type' => 'email', 'serviceReviewInvitation' => ['templateId' => 'tpl-1', 'preferredSendTime' => '2026-10-10T08:00:00']], json_decode(self::body($this->calls[2][2]), true));
        self::assertSame(['rating', 'reviews', 'reply', 'invite', 'writeUrl'], $provider->capabilities()->contracts());
    }

    public function testWithoutTheBusinessUserNoReplyNorInvitation(): void
    {
        $this->expectException(NotSupportedException::class);
        $this->provider([])->invite(new Place(self::UNIT), new Invitation('a@example.org', 'A'));
    }

    /** @param array<mixed> $options a recorded request's options: its headers, one a line */
    private static function headers(array $options): string
    {
        $headers = $options['headers'] ?? [];

        return implode("\n", \is_array($headers) ? array_filter($headers, 'is_string') : []);
    }

    /** @param array<mixed> $options a recorded request's options: its body */
    private static function body(array $options): string
    {
        return \is_string($options['body'] ?? null) ? $options['body'] : '';
    }

    public function testTheTokenRequestIsCountedUnderTokenAndNeverSentOverASitesLimit(): void
    {
        $provider = $this->provider(['token'], ['api_secret' => 'tp-secret', 'business_user_id' => 'bu-1', 'policy' => ['rateLimits' => [['max' => 0, 'window' => 'day', 'operations' => ['token']]]]]);

        try {
            $provider->reply(new Place(self::UNIT), new Review('trustpilot', '507f191e810c19729de860ea', 5), 'Merci !');
            self::fail('The token request went out over the site\'s limit.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame('0/day/application/token', $e->limit?->id());
        }
        self::assertSame([], $this->calls, 'nothing was sent');
    }

}
