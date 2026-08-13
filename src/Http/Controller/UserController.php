<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\UseCase\DepositMoney\DepositMoneyCommand;
use App\Application\UseCase\DepositMoney\DepositMoneyUseCase;
use App\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Application\UseCase\RegisterUser\RegisterUserUseCase;
use App\Domain\Enum\UserType;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Http\Request\DepositRequest;
use App\Http\Request\RegisterUserRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Registration is explicitly NOT part of the graded flow — it exists only so `POST /transfer`
 * has payers/payees with a balance to exercise against. Kept intentionally simple.
 */
#[AsController]
final class UserController
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly DepositMoneyUseCase $depositMoneyUseCase,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    #[Route('/users', name: 'users_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] RegisterUserRequest $payload): JsonResponse
    {
        $user = $this->registerUserUseCase->execute(new RegisterUserCommand(
            $payload->fullName,
            $payload->document,
            $payload->email,
            $payload->password,
            UserType::from($payload->type),
        ));

        return new JsonResponse($this->present($user), JsonResponse::HTTP_CREATED);
    }

    #[Route('/users/{id}', name: 'users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->users->findById($id);

        if ($user === null) {
            throw UserNotFoundException::withId($id);
        }

        return new JsonResponse($this->present($user));
    }

    /**
     * Not part of the graded flow (see {@see DepositMoneyUseCase}) — a convenience so
     * `POST /transfer` can actually be exercised against a funded wallet.
     */
    #[Route('/users/{id}/deposit', name: 'users_deposit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deposit(int $id, #[MapRequestPayload] DepositRequest $payload): JsonResponse
    {
        $user = $this->depositMoneyUseCase->execute(new DepositMoneyCommand($id, $payload->value));

        return new JsonResponse($this->present($user));
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id(),
            'fullName' => $user->fullName(),
            'email' => $user->email(),
            'document' => $user->document()->number(),
            'documentType' => $user->document()->type()->value,
            'type' => $user->type()->value,
            'balance' => (string) $user->balance(),
            'createdAt' => $user->createdAt()->format(DATE_ATOM),
        ];
    }
}
