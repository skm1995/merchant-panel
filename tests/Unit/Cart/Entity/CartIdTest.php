<?php

declare(strict_types=1);

namespace Tests\Unit\Cart\Entity;

use App\Cart\Entity\CartId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Cart\CartFixtures;

final class CartIdTest extends TestCase
{
    private const string PATTERN_FORMAT = '/^%s$/';
    private const int BINARY_LENGTH = 16;

    public function testGeneratesUniqueVersionFourUuids(): void
    {
        $first = CartId::generate();

        self::assertMatchesRegularExpression(sprintf(self::PATTERN_FORMAT, CartId::PATTERN), $first->value);
        self::assertNotSame($first->value, CartId::generate()->value);
    }

    public function testRoundTripsThroughSixteenByteBinaryStorage(): void
    {
        $id = CartId::fromString(CartFixtures::CART_ID);

        self::assertSame(self::BINARY_LENGTH, strlen($id->toBinary()));
        self::assertSame(CartFixtures::CART_ID, CartId::fromBinary($id->toBinary())->value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIds(): iterable
    {
        yield 'not a uuid' => ['not-a-uuid'];
        yield 'uppercase' => [strtoupper(CartFixtures::CART_ID)];
        yield 'version 7' => ['01890a5d-ac96-774b-bcce-b302099a8057'];
    }

    #[DataProvider('invalidIds')]
    public function testRejectsAnythingButCanonicalVersionFourUuids(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        CartId::fromString($value);
    }
}
