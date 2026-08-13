<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_registers_a_common_user(): void
    {
        $this->client->jsonRequest('POST', '/users', [
            'fullName' => 'Ana Silva',
            'document' => '529.982.247-25',
            'email' => 'ana-register@example.com',
            'password' => 'supersecret',
            'type' => 'common',
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Ana Silva', $body['fullName']);
        self::assertSame('52998224725', $body['document']);
        self::assertSame('cpf', $body['documentType']);
        self::assertSame('common', $body['type']);
        self::assertSame('0.00', $body['balance']);
        self::assertArrayNotHasKey('passwordHash', $body);
        self::assertArrayNotHasKey('password', $body);
    }

    public function test_rejects_a_duplicate_email(): void
    {
        $payload = [
            'fullName' => 'Test User',
            'document' => '52998224725',
            'email' => 'dup-functional@example.com',
            'password' => 'supersecret',
            'type' => 'common',
        ];
        $this->client->jsonRequest('POST', '/users', $payload);
        self::assertResponseStatusCodeSame(201);

        $payload['document'] = '39053344705';
        $this->client->jsonRequest('POST', '/users', $payload);

        self::assertResponseStatusCodeSame(409);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('duplicate_email', $body['title']);
    }

    public function test_rejects_an_invalid_document(): void
    {
        $this->client->jsonRequest('POST', '/users', [
            'fullName' => 'Test User',
            'document' => '11111111111',
            'email' => 'invalid-doc@example.com',
            'password' => 'supersecret',
            'type' => 'common',
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('invalid_document', $body['title']);
    }

    public function test_shows_a_registered_user(): void
    {
        $this->client->jsonRequest('POST', '/users', [
            'fullName' => 'Show Me',
            'document' => '52998224725',
            'email' => 'show-me@example.com',
            'password' => 'supersecret',
            'type' => 'common',
        ]);
        $id = json_decode((string) $this->client->getResponse()->getContent(), true)['id'];

        $this->client->request('GET', "/users/{$id}");

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('show-me@example.com', $body['email']);
    }

    public function test_unknown_user_is_a_404(): void
    {
        $this->client->request('GET', '/users/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_deposit_increases_the_balance(): void
    {
        $this->client->jsonRequest('POST', '/users', [
            'fullName' => 'Depositor',
            'document' => '52998224725',
            'email' => 'depositor@example.com',
            'password' => 'supersecret',
            'type' => 'common',
        ]);
        $id = json_decode((string) $this->client->getResponse()->getContent(), true)['id'];

        $this->client->jsonRequest('POST', "/users/{$id}/deposit", ['value' => 25.5]);

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('25.50', $body['balance']);
    }
}
