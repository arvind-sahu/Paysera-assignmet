<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\TestFixtures;
use Symfony\Component\HttpFoundation\Response;

final class TransferListApiTest extends AbstractApiTestCase
{
    public function testListTransfersReturnsCreatedTransfers(): void
    {
        $this->createTransfer('list-transfer-001', '50.00');

        $this->apiRequest('GET', '/api/v1/transfers');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['pagination']['total']);
        self::assertSame('50.00', $data['items'][0]['amount']);
        self::assertSame('completed', $data['items'][0]['status']);
    }

    public function testRecentTransfersDefaultsToLast30Days(): void
    {
        $this->createTransfer('recent-transfer-001', '25.00');

        $this->apiRequest('GET', '/api/v1/transfers/recent');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(30, $data['periodDays']);
        self::assertSame(1, $data['pagination']['total']);
    }

    public function testAccountTransfersEndpointFiltersByAccount(): void
    {
        $this->createTransfer('account-transfer-001', '15.00');

        $this->apiRequest('GET', '/api/v1/accounts/' . TestFixtures::ALICE . '/transfers');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['pagination']['total']);
        self::assertSame(TestFixtures::ALICE, $data['items'][0]['fromAccountId']);
    }

    public function testV2ListUsesEnvelopeFormat(): void
    {
        $this->createTransfer('v2-list-transfer-001', '5.00');

        $this->apiRequest('GET', '/api/v2/transfers');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('v2', $data['meta']['apiVersion']);
        self::assertArrayHasKey('pagination', $data['meta']);
        self::assertCount(1, $data['data']);
    }

    public function testV2CreateTransferReturnsEnvelope(): void
    {
        $this->apiRequest('POST', '/api/v2/transfers', [
            'fromAccountId' => TestFixtures::ALICE,
            'toAccountId' => TestFixtures::BOB,
            'amount' => '12.00',
            'currency' => 'EUR',
            'description' => 'Test payment',
        ], ['HTTP_IDEMPOTENCY_KEY' => 'v2-create-transfer-001']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('v2', $data['meta']['apiVersion']);
        self::assertSame('12.00', $data['data']['amount']);
        self::assertSame('Test payment', $data['data']['description']);
    }

    private function createTransfer(string $idempotencyKey, string $amount): void
    {
        $this->apiRequest('POST', '/api/v1/transfers', [
            'fromAccountId' => TestFixtures::ALICE,
            'toAccountId' => TestFixtures::BOB,
            'amount' => $amount,
            'currency' => 'EUR',
        ], ['HTTP_IDEMPOTENCY_KEY' => $idempotencyKey]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
