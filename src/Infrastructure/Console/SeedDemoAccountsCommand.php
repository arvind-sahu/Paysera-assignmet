<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo-accounts', description: 'Seed demo accounts for development and load testing')]
final class SeedDemoAccountsCommand extends Command
{
    /** Stable UUIDs for reproducible tests and k6 scripts. */
    public const ACCOUNT_ALICE = '11111111-1111-4111-8111-111111111111';
    public const ACCOUNT_BOB = '22222222-2222-4222-8222-222222222222';
    public const ACCOUNT_CHARLIE = '33333333-3333-4333-8333-333333333333';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        $accounts = [
            [self::ACCOUNT_ALICE, 'LT611111111111111111', 100_000_00],
            [self::ACCOUNT_BOB, 'LT622222222222222222', 50_000_00],
            [self::ACCOUNT_CHARLIE, 'LT633333333333333333', 10_000_00],
        ];

        foreach ($accounts as [$id, $number, $balanceMinor]) {
            $existing = $this->em->find(AccountEntity::class, $id);
            if ($existing !== null) {
                $existing->balanceMinor = $balanceMinor;
                $existing->updatedAt = $now;
                continue;
            }

            $entity = new AccountEntity();
            $entity->id = $id;
            $entity->accountNumber = $number;
            $entity->balanceMinor = $balanceMinor;
            $entity->currency = 'EUR';
            $entity->active = true;
            $entity->createdAt = $now;
            $entity->updatedAt = $now;
            $this->em->persist($entity);
        }

        $this->em->flush();

        $io->success('Demo accounts ready.');
        $io->table(
            ['Account', 'UUID', 'Balance (EUR)'],
            [
                ['Alice', self::ACCOUNT_ALICE, '100000.00'],
                ['Bob', self::ACCOUNT_BOB, '50000.00'],
                ['Charlie', self::ACCOUNT_CHARLIE, '10000.00'],
            ],
        );

        return Command::SUCCESS;
    }
}
