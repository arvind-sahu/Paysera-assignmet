<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\TestFixtures;
use Symfony\Component\HttpFoundation\Response;

final class TransferApiTest extends AbstractApiTestCase
{
    public function testSuccessfulTransfer(): void
    {
        $this->apiRequest('POST', '/api/v1/transfers', [
            'fromAccountId' => TestFixtures::ALICE,
            'toAccountId' => TestFixtures::BOB,
            'amount' => '125.50',
            'currency' => 'EUR',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'integration-success-1']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('completed', $data['status']);
        self::assertSame('125.50', $data['amount']);

        $this->apiRequest('GET', '/api/v1/accounts/' . TestFixtures::ALICE);
        $alice = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('99874.50', $alice['balance']);

        $this->apiRequest('GET', '/api/v1/accounts/' . TestFixtures::BOB);
        $bob = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('50125.50', $bob['balance']);
    }

    public function testInsufficientFundsReturns422(): void
    {
        $this->apiRequest('POST', '/api/v1/transfers', [
            'fromAccountId' => TestFixtures::CHARLIE,
            'toAccountId' => TestFixtures::BOB,
            'amount' => '999999.00',
            'currency' => 'EUR',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('INSUFFICIENT_FUNDS', $data['error']);
    }

    public function testIdempotentReplayDoesNotDoubleDebit(): void
    {
        $payload = [
            'fromAccountId' => TestFixtures::ALICE,
            'toAccountId' => TestFixtures::CHARLIE,
            'amount' => '10.00',
            'currency' => 'EUR',
        ];
        $key = 'idempotent-key-001';

        $this->apiRequest('POST', '/api/v1/transfers', $payload, ['HTTP_IDEMPOTENCY_KEY' => $key]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->apiRequest('POST', '/api/v1/transfers', $payload, ['HTTP_IDEMPOTENCY_KEY' => $key]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $second = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($first['reference'], $second['reference']);
        self::assertTrue($second['replayed']);

        $this->apiRequest('GET', '/api/v1/accounts/' . TestFixtures::ALICE);
        $alice = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('99990.00', $alice['balance']);
    }

    public function testUnauthorizedWithoutApiKey(): void
    {
        $this->client->request('POST', '/api/v1/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'fromAccountId' => TestFixtures::ALICE,
            'toAccountId' => TestFixtures::BOB,
            'amount' => '1.00',
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
