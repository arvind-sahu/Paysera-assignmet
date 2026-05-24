<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Component\HttpFoundation\Response;

final class HealthApiTest extends AbstractApiTestCase
{
    public function testHealthEndpoint(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('healthy', $data['status']);
        self::assertSame('ok', $data['checks']['database']);
        self::assertSame('ok', $data['checks']['redis']);
    }
}
