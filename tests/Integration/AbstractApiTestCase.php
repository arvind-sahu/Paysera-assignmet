<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Infrastructure\Console\SeedDemoAccountsCommand;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient(['environment' => 'test']);
        $this->resetDatabase();
        $this->seedAccounts();
    }

    protected function resetDatabase(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function seedAccounts(): void
    {
        static::getContainer()->get(SeedDemoAccountsCommand::class)->run(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );
    }

    protected function apiRequest(
        string $method,
        string $uri,
        ?array $body = null,
        array $headers = [],
    ): void {
        $defaultHeaders = [
            'HTTP_X_API_KEY' => \App\Tests\Support\TestFixtures::API_KEY,
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            array_merge($defaultHeaders, $headers),
            $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null,
        );
    }
}
