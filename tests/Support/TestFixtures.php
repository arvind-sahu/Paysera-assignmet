<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\Console\SeedDemoAccountsCommand;

final class TestFixtures
{
    public const API_KEY = 'test-api-key';
    public const ALICE = SeedDemoAccountsCommand::ACCOUNT_ALICE;
    public const BOB = SeedDemoAccountsCommand::ACCOUNT_BOB;
    public const CHARLIE = SeedDemoAccountsCommand::ACCOUNT_CHARLIE;
}
