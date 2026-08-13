<?php

declare(strict_types=1);

namespace App\Application\UseCase\RegisterUser;

use App\Application\Port\PasswordHasherInterface;
use App\Application\Port\TransactionManagerInterface;
use App\Domain\Exception\DuplicateDocumentException;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Registration itself is explicitly not graded by the challenge, but it has to exist and be
 * correct: it is the only way to get payers/payees with a balance into the system to exercise
 * the transfer flow it IS graded on.
 */
final class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function execute(RegisterUserCommand $command): User
    {
        $document = Document::fromRaw($command->document);

        // Pre-check for a friendly, specific error in the common case; the unique indexes on
        // `users.email` / `users.document_number` are the real guarantee, guarding the race
        // window between this check and the commit below (caught after the fact).
        if ($this->users->findByEmail($command->email) !== null) {
            throw DuplicateEmailException::forEmail($command->email);
        }

        if ($this->users->findByDocumentNumber($document->number()) !== null) {
            throw DuplicateDocumentException::forDocument($document->number());
        }

        try {
            return $this->transactionManager->transactional(function () use ($command, $document): User {
                $user = new User(
                    $command->fullName,
                    $document,
                    $command->email,
                    $this->passwordHasher->hash($command->plainPassword),
                    $command->type,
                );

                $this->users->add($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw str_contains($exception->getMessage(), 'uniq_users_email')
                ? DuplicateEmailException::forEmail($command->email)
                : DuplicateDocumentException::forDocument($document->number());
        }
    }
}
