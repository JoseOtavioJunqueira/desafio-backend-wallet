<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Enum\DocumentType;
use App\Domain\Exception\InvalidDocumentException;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Brazilian CPF or CNPJ, normalized to digits-only and validated against its official
 * check-digit algorithm (not just length/format). The README allows either document type for
 * either user type ("CPF/CNPJ e e-mails devem ser únicos"), so the type is inferred from the
 * digit count rather than tied to {@see \App\Domain\Enum\UserType}.
 */
#[ORM\Embeddable]
final class Document
{
    #[ORM\Column(name: 'document_number', type: 'string', length: 14)]
    private readonly string $number;

    #[ORM\Column(name: 'document_type', type: 'string', length: 4, enumType: DocumentType::class)]
    private readonly DocumentType $type;

    private function __construct(string $number, DocumentType $type)
    {
        $this->number = $number;
        $this->type = $type;
    }

    /** Accepts either a masked ("123.456.789-09") or raw document number. */
    public static function fromRaw(string $rawValue): self
    {
        $digits = preg_replace('/\D/', '', $rawValue) ?? '';

        return match (strlen($digits)) {
            11 => self::cpf($digits),
            14 => self::cnpj($digits),
            default => throw InvalidDocumentException::forValue($rawValue),
        };
    }

    public static function cpf(string $digits): self
    {
        if (!self::isValidCpf($digits)) {
            throw InvalidDocumentException::forValue($digits);
        }

        return new self($digits, DocumentType::CPF);
    }

    public static function cnpj(string $digits): self
    {
        if (!self::isValidCnpj($digits)) {
            throw InvalidDocumentException::forValue($digits);
        }

        return new self($digits, DocumentType::CNPJ);
    }

    public function number(): string
    {
        return $this->number;
    }

    public function type(): DocumentType
    {
        return $this->type;
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number;
    }

    private static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        $numbers = array_map('intval', str_split($digits));

        $firstCheckDigit = self::calculateCheckDigit(array_slice($numbers, 0, 9), 10);
        if ($firstCheckDigit !== $numbers[9]) {
            return false;
        }

        $secondCheckDigit = self::calculateCheckDigit(array_slice($numbers, 0, 10), 11);

        return $secondCheckDigit === $numbers[10];
    }

    private static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $numbers = array_map('intval', str_split($digits));
        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstCheckDigit = self::calculateCheckDigit(array_slice($numbers, 0, 12), null, $firstWeights);
        if ($firstCheckDigit !== $numbers[12]) {
            return false;
        }

        $secondCheckDigit = self::calculateCheckDigit(array_slice($numbers, 0, 13), null, $secondWeights);

        return $secondCheckDigit === $numbers[13];
    }

    /**
     * Classic modulo-11 check digit. Either pass a descending starting weight (CPF, weights
     * count down by one per digit) or an explicit weight table (CNPJ, weights are not a plain
     * sequence).
     *
     * @param int[] $digits
     * @param int[]|null $explicitWeights
     */
    private static function calculateCheckDigit(array $digits, ?int $weightStart, ?array $explicitWeights = null): int
    {
        $sum = 0;
        $weight = $weightStart;

        foreach ($digits as $index => $digit) {
            $sum += $digit * ($explicitWeights[$index] ?? $weight);
            if ($weight !== null) {
                $weight--;
            }
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
