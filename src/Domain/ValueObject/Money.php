<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidMoneyAmountException;
use Doctrine\ORM\Mapping as ORM;

/**
 * An amount of Brazilian Real, stored as an integer number of cents so arithmetic never
 * touches floating point. Mapped as a Doctrine embeddable so it can live directly on
 * {@see \App\Domain\Model\Wallet} and {@see \App\Domain\Model\Transaction} columns.
 *
 * Cents are persisted as a 32-bit `integer` column (not `bigint`): Doctrine's bigint type
 * round-trips through PHP as a string to stay safe on 32-bit builds, which would force every
 * consumer of this value object to special-case hydration. A 32-bit range (~R$ 21 million per
 * wallet/transfer) is far beyond what this challenge needs; scaling past it is a one-line
 * column type change, noted in docs/adr/0002-money-as-integer-cents.md.
 */
#[ORM\Embeddable]
final class Money implements \Stringable
{
    #[ORM\Column(name: 'cents', type: 'integer')]
    private readonly int $cents;

    private function __construct(int $cents)
    {
        if ($cents < 0) {
            throw InvalidMoneyAmountException::resultingBalanceCannotBeNegative();
        }

        $this->cents = $cents;
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Accepts any string (not just numeric-string) on purpose: callers pass through untrusted
     * request/CLI input, and validating "is this actually numeric" is this method's job, not
     * every caller's.
     *
     * @param string|float|int $amount decimal reais, e.g. "100.50" or 100.5
     *
     * @throws InvalidMoneyAmountException if $amount is not a valid decimal number
     */
    public static function fromDecimal(string|float|int $amount): self
    {
        $normalized = is_string($amount) ? str_replace(',', '.', $amount) : (string) $amount;

        if (!is_numeric($normalized)) {
            throw InvalidMoneyAmountException::mustBePositive();
        }

        // bcmath-free rounding to cents: shift two decimal places, then round half away from zero.
        $cents = (int) round(((float) $normalized) * 100);

        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /** Decimal string with two fraction digits, e.g. "100.50". */
    public function __toString(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }
}
