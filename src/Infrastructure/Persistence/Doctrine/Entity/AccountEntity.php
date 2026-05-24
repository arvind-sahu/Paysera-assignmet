<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\UniqueConstraint(name: 'uniq_account_number', columns: ['account_number'])]
class AccountEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(name: 'account_number', type: 'string', length: 34)]
    public string $accountNumber;

    #[ORM\Column(name: 'balance_minor', type: 'bigint')]
    public int $balanceMinor;

    #[ORM\Column(type: 'string', length: 3)]
    public string $currency;

    #[ORM\Column(type: 'boolean')]
    public bool $active = true;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    public int $version = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
}
