<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\UserType;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

// No `repositoryClass` here on purpose: the Domain layer must not reference
// App\Infrastructure. Persistence lives entirely in
// App\Infrastructure\Persistence\Doctrine\DoctrineUserRepository, wired to
// App\Domain\Repository\UserRepositoryInterface in config/services.yaml.
//
// The wallet balance is a plain embedded column on `users`, not a separate `Wallet` entity: it
// has no identity or lifecycle of its own apart from its owner (always created with the user,
// never queried independently), so a distinct aggregate would only be indirection. It was tried
// as a separate entity first and reverted — see docs/adr/0003-wallet-is-not-an-entity.md — a
// bidirectional one-to-one's inverse side cannot be lazily loaded in Doctrine, so every
// `getByIdForUpdate()` pulled it in via an outer join, and PostgreSQL rejects
// `SELECT ... FOR UPDATE` combined with a join on the nullable side.
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_users_document_number', columns: ['document_number'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'full_name', type: 'string', length: 180)]
    private string $fullName;

    #[ORM\Embedded(class: Document::class, columnPrefix: false)]
    private Document $document;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: 'string')]
    private string $passwordHash;

    #[ORM\Column(type: 'string', length: 20, enumType: UserType::class)]
    private UserType $type;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'balance_')]
    private Money $balance;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'balance_updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $balanceUpdatedAt;

    public function __construct(
        string $fullName,
        Document $document,
        string $email,
        string $passwordHash,
        UserType $type,
    ) {
        $this->fullName = $fullName;
        $this->document = $document;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->type = $type;
        $this->balance = Money::zero();
        $this->createdAt = new \DateTimeImmutable();
        $this->balanceUpdatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function document(): Document
    {
        return $this->document;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function type(): UserType
    {
        return $this->type;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function canSendMoney(): bool
    {
        return $this->type === UserType::COMMON;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function credit(Money $amount): void
    {
        $this->balance = $this->balance->add($amount);
        $this->balanceUpdatedAt = new \DateTimeImmutable();
    }

    /** @throws InsufficientFundsException */
    public function debit(Money $amount): void
    {
        if (!$this->balance->isGreaterThanOrEqualTo($amount)) {
            throw InsufficientFundsException::forUser($this->id ?? 0);
        }

        $this->balance = $this->balance->subtract($amount);
        $this->balanceUpdatedAt = new \DateTimeImmutable();
    }
}
