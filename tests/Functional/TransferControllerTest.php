<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Enum\UserType;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Money;
use App\Tests\Fakes\FakeAuthorizerGateway;
use App\Tests\Fakes\FakeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $users;
    private FakeAuthorizerGateway $authorizer;
    private FakeNotifier $notifier;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep the same container/fakes across every request in the test — the default
        // per-request kernel reboot would otherwise hand back a *fresh* FakeAuthorizerGateway,
        // silently discarding any ->deny() set up before $client->request().
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->users = $container->get(UserRepositoryInterface::class);
        $this->authorizer = $container->get(FakeAuthorizerGateway::class);
        $this->notifier = $container->get(FakeNotifier::class);
    }

    public function test_a_funded_common_user_can_pay_a_merchant(): void
    {
        $payer = $this->createUser(UserType::COMMON, '52998224725', 'payer@example.com', '100.00');
        $payee = $this->createUser(UserType::MERCHANT, '11444777000161', 'payee@example.com', '0.00');

        $this->client->jsonRequest('POST', '/transfer', ['value' => 40.0, 'payer' => $payer->id(), 'payee' => $payee->id()]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('completed', $body['status']);
        self::assertSame('40.00', $body['value']);

        $this->entityManager->clear();
        self::assertSame('60.00', (string) $this->users->findById($payer->id())->balance());
        self::assertSame('40.00', (string) $this->users->findById($payee->id())->balance());
        self::assertCount(1, $this->notifier->notified());
    }

    public function test_merchant_cannot_be_a_payer(): void
    {
        $payer = $this->createUser(UserType::MERCHANT, '11444777000161', 'merchant@example.com', '100.00');
        $payee = $this->createUser(UserType::COMMON, '52998224725', 'payee@example.com', '0.00');

        $this->client->jsonRequest('POST', '/transfer', ['value' => 10.0, 'payer' => $payer->id(), 'payee' => $payee->id()]);

        self::assertResponseStatusCodeSame(403);
        $this->assertJsonProblem('merchant_cannot_send_money');
    }

    public function test_insufficient_funds(): void
    {
        $payer = $this->createUser(UserType::COMMON, '52998224725', 'poor@example.com', '5.00');
        $payee = $this->createUser(UserType::COMMON, '39053344705', 'rich@example.com', '0.00');

        $this->client->jsonRequest('POST', '/transfer', ['value' => 10.0, 'payer' => $payer->id(), 'payee' => $payee->id()]);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonProblem('insufficient_funds');
    }

    public function test_unknown_payee_is_a_404(): void
    {
        $payer = $this->createUser(UserType::COMMON, '52998224725', 'payer2@example.com', '100.00');

        $this->client->jsonRequest('POST', '/transfer', ['value' => 10.0, 'payer' => $payer->id(), 'payee' => 999_999]);

        self::assertResponseStatusCodeSame(404);
        $this->assertJsonProblem('user_not_found');
    }

    public function test_a_denied_authorization_reverts_the_balance(): void
    {
        $payer = $this->createUser(UserType::COMMON, '52998224725', 'denied-payer@example.com', '100.00');
        $payee = $this->createUser(UserType::COMMON, '39053344705', 'denied-payee@example.com', '0.00');
        $this->authorizer->deny();

        $this->client->jsonRequest('POST', '/transfer', ['value' => 10.0, 'payer' => $payer->id(), 'payee' => $payee->id()]);

        self::assertResponseStatusCodeSame(403);
        $this->assertJsonProblem('transfer_not_authorized');

        $this->entityManager->clear();
        self::assertSame('100.00', (string) $this->users->findById($payer->id())->balance());
        self::assertCount(0, $this->notifier->notified());
    }

    public function test_missing_fields_are_rejected_with_field_level_violations(): void
    {
        $this->client->jsonRequest('POST', '/transfer', []);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('validation_failed', $body['title']);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('value', $fields);
        self::assertContains('payer', $fields);
        self::assertContains('payee', $fields);
    }

    public function test_idempotency_key_prevents_a_duplicate_transfer(): void
    {
        $payer = $this->createUser(UserType::COMMON, '52998224725', 'idem-payer@example.com', '100.00');
        $payee = $this->createUser(UserType::COMMON, '39053344705', 'idem-payee@example.com', '0.00');

        $payload = json_encode(['value' => 10.0, 'payer' => $payer->id(), 'payee' => $payee->id()]);
        $key = 'test-idempotency-key-' . uniqid();

        $this->client->request('POST', '/transfer', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Idempotency-Key' => $key,
        ], content: $payload);
        $first = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('POST', '/transfer', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Idempotency-Key' => $key,
        ], content: $payload);
        $second = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame($first['id'], $second['id']);

        $this->entityManager->clear();
        // If the key had NOT been honored, this would be 80.00 (two debits of 10).
        self::assertSame('90.00', (string) $this->users->findById($payer->id())->balance());
    }

    private function createUser(UserType $type, string $document, string $email, string $balance): User
    {
        $user = new User('Test User', Document::fromRaw($document), $email, 'hash', $type);
        $this->users->add($user);
        $user->credit(Money::fromDecimal($balance));
        $this->entityManager->flush();

        return $user;
    }

    private function assertJsonProblem(string $expectedTitle): void
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($expectedTitle, $body['title']);
    }
}
