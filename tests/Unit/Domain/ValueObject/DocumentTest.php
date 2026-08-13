<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Enum\DocumentType;
use App\Domain\Exception\InvalidDocumentException;
use App\Domain\ValueObject\Document;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    #[DataProvider('validCpfs')]
    public function test_accepts_valid_cpf(string $cpf): void
    {
        $document = Document::fromRaw($cpf);

        self::assertSame(DocumentType::CPF, $document->type());
        self::assertSame(preg_replace('/\D/', '', $cpf), $document->number());
    }

    /** @return iterable<string, array{0: string}> */
    public static function validCpfs(): iterable
    {
        yield 'raw digits' => ['52998224725'];
        yield 'masked' => ['529.982.247-25'];
    }

    #[DataProvider('validCnpjs')]
    public function test_accepts_valid_cnpj(string $cnpj): void
    {
        $document = Document::fromRaw($cnpj);

        self::assertSame(DocumentType::CNPJ, $document->type());
        self::assertSame(preg_replace('/\D/', '', $cnpj), $document->number());
    }

    /** @return iterable<string, array{0: string}> */
    public static function validCnpjs(): iterable
    {
        yield 'raw digits' => ['11444777000161'];
        yield 'masked' => ['11.444.777/0001-61'];
    }

    #[DataProvider('invalidDocuments')]
    public function test_rejects_invalid_documents(string $value): void
    {
        $this->expectException(InvalidDocumentException::class);

        Document::fromRaw($value);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'wrong cpf check digits' => ['52998224700'];
        yield 'wrong cnpj check digits' => ['11444777000100'];
        yield 'all repeated digits (cpf length)' => ['11111111111'];
        yield 'all repeated digits (cnpj length)' => ['11111111111111'];
        yield 'too short' => ['123'];
        yield 'too long' => ['123456789012345'];
        yield 'empty' => [''];
    }

    public function test_equals_compares_by_number(): void
    {
        $first = Document::fromRaw('529.982.247-25');
        $second = Document::fromRaw('52998224725');
        $different = Document::cpf('39053344705');

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($different));
    }
}
