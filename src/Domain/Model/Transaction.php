<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\TransactionStatus;
use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable audit record of a transfer attempt, successful or not. Rejected attempts are kept
 * (not discarded) so support/ops can answer "what happened to my transfer" without grepping logs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'payer_id', referencedColumnName: 'id', nullable: false)]
    private User $payer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'payee_id', referencedColumnName: 'id', nullable: false)]
    private User $payee;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
    private Money $amount;

    #[ORM\Column(type: 'string', length: 20, enumType: TransactionStatus::class)]
    private TransactionStatus $status;

    #[ORM\Column(name: 'authorization_reference', type: 'string', length: 255, nullable: true)]
    private ?string $authorizationReference;

    #[ORM\Column(name: 'rejection_reason', type: 'string', length: 255, nullable: true)]
    private ?string $rejectionReason;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        User $payer,
        User $payee,
        Money $amount,
        TransactionStatus $status,
        ?string $authorizationReference,
        ?string $rejectionReason,
    ) {
        $this->payer = $payer;
        $this->payee = $payee;
        $this->amount = $amount;
        $this->status = $status;
        $this->authorizationReference = $authorizationReference;
        $this->rejectionReason = $rejectionReason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function completed(User $payer, User $payee, Money $amount, string $authorizationReference): self
    {
        return new self($payer, $payee, $amount, TransactionStatus::COMPLETED, $authorizationReference, null);
    }

    public static function rejected(User $payer, User $payee, Money $amount, string $reason): self
    {
        return new self($payer, $payee, $amount, TransactionStatus::REJECTED, null, $reason);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function payer(): User
    {
        return $this->payer;
    }

    public function payee(): User
    {
        return $this->payee;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function authorizationReference(): ?string
    {
        return $this->authorizationReference;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::COMPLETED;
    }
}
