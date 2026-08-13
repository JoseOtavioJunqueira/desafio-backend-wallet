<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\RegisterUser;

use App\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Application\UseCase\RegisterUser\RegisterUserUseCase;
use App\Domain\Enum\UserType;
use App\Domain\Exception\DuplicateDocumentException;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\InvalidDocumentException;
use App\Tests\Fakes\FakePasswordHasher;
use App\Tests\Fakes\InMemoryUserRepository;
use App\Tests\Fakes\PassthroughTransactionManager;
use PHPUnit\Framework\TestCase;

final class RegisterUserUseCaseTest extends TestCase
{
    private InMemoryUserRepository $users;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->useCase = new RegisterUserUseCase(
            $this->users,
            new PassthroughTransactionManager(),
            new FakePasswordHasher(),
        );
    }

    public function test_registers_a_user_with_a_hashed_password_and_a_zero_balance(): void
    {
        $user = $this->useCase->execute(new RegisterUserCommand(
            'Ana Silva',
            '529.982.247-25',
            'ana@example.com',
            'supersecret',
            UserType::COMMON,
        ));

        self::assertSame('Ana Silva', $user->fullName());
        self::assertSame('52998224725', $user->document()->number());
        self::assertSame('hashed:supersecret', $user->passwordHash());
        self::assertSame('0.00', (string) $user->balance());
        self::assertNotNull($user->id());
        self::assertSame($user, $this->users->findById($user->id()));
    }

    public function test_rejects_a_duplicate_email(): void
    {
        $this->useCase->execute(new RegisterUserCommand('A', '52998224725', 'dup@example.com', 'password', UserType::COMMON));

        $this->expectException(DuplicateEmailException::class);

        $this->useCase->execute(new RegisterUserCommand('B', '39053344705', 'dup@example.com', 'password', UserType::COMMON));
    }

    public function test_rejects_a_duplicate_document(): void
    {
        $this->useCase->execute(new RegisterUserCommand('A', '52998224725', 'a@example.com', 'password', UserType::COMMON));

        $this->expectException(DuplicateDocumentException::class);

        $this->useCase->execute(new RegisterUserCommand('B', '52998224725', 'b@example.com', 'password', UserType::COMMON));
    }

    public function test_rejects_an_invalid_document(): void
    {
        $this->expectException(InvalidDocumentException::class);

        $this->useCase->execute(new RegisterUserCommand('A', '11111111111', 'a@example.com', 'password', UserType::COMMON));
    }
}
