<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Service;

use App\Auth\Entity\User;
use App\Auth\Exception\DuplicateEmailException;
use App\Auth\Exception\EmailNotVerifiedException;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Exception\InvalidEmailException;
use App\Auth\Exception\UnsupportedPasswordException;
use App\Auth\Exception\WeakPasswordException;
use App\Auth\Repository\UserRepositoryInterface;
use App\Auth\Service\UserAuthService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAuthServiceTest extends TestCase
{
    private const string EMAIL = 'person@example.com';
    private const string PASSWORD = 'correct-horse-battery';
    private const string INVALID_EMAIL = 'invalid-email';
    private const string SHORT_PASSWORD = 'short';
    private const string WRONG_PASSWORD = 'incorrect-password';
    private const string EMAIL_ERROR = 'Enter a valid email address.';
    private const string PASSWORD_ERROR = 'Password must contain at least 8 characters.';
    private const string CREDENTIALS_ERROR = 'Invalid email or password.';
    private const string DUPLICATE_ERROR = 'An account with this email already exists.';
    private const string UNVERIFIED_EMAIL_ERROR = 'Confirm your email address before logging in.';
    private const string UNSUPPORTED_PASSWORD_ERROR = 'Password must have at most 72 bytes and no NUL characters.';
    private const string PASSWORD_WITH_NUL = "correct-horse\0battery";
    private const int BCRYPT_MAXIMUM_BYTES = 72;

    public function testRegistersUserWithHashedPasswordAndNormalizedEmail(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn(null);
        $repository->expects(self::once())->method('create')->with(
            self::EMAIL,
            self::callback(static fn (string $hash): bool => password_verify(self::PASSWORD, $hash)),
        )->willReturn(new User(self::EMAIL, 'hashed-password', new DateTimeImmutable()));

        $user = new UserAuthService($repository)->register(' Person@Example.com ', self::PASSWORD);

        self::assertSame(self::EMAIL, $user->email);
    }

    public function testRejectsInvalidEmail(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('create');

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage(self::EMAIL_ERROR);
        new UserAuthService($repository)->register(self::INVALID_EMAIL, self::PASSWORD);
    }

    public function testRejectsShortPassword(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('create');

        $this->expectException(WeakPasswordException::class);
        $this->expectExceptionMessage(self::PASSWORD_ERROR);
        new UserAuthService($repository)->register(self::EMAIL, self::SHORT_PASSWORD);
    }

    /** @return array<string, array{string}> */
    public static function unsupportedPasswords(): array
    {
        return [
            'longer than bcrypt hashes' => [str_repeat('p', self::BCRYPT_MAXIMUM_BYTES + 1)],
            'with a NUL character' => [self::PASSWORD_WITH_NUL],
        ];
    }

    #[DataProvider('unsupportedPasswords')]
    public function testRejectsPasswordsBcryptCannotHashWhole(string $password): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('create');

        $this->expectException(UnsupportedPasswordException::class);
        $this->expectExceptionMessage(self::UNSUPPORTED_PASSWORD_ERROR);
        new UserAuthService($repository)->register(self::EMAIL, $password);
    }

    public function testAcceptsAPasswordOfExactlyTheBcryptLimit(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(null);
        $repository->expects(self::once())->method('create')->willReturn(new User(self::EMAIL, 'hashed-password', new DateTimeImmutable()));

        new UserAuthService($repository)->register(self::EMAIL, str_repeat('p', self::BCRYPT_MAXIMUM_BYTES));
    }

    public function testRejectsSignInWithAPasswordContainingNul(): void
    {
        $user = new User(self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT), new DateTimeImmutable(), new DateTimeImmutable());
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn($user);

        $this->expectException(InvalidCredentialsException::class);
        new UserAuthService($repository)->authenticate(self::EMAIL, self::PASSWORD_WITH_NUL);
    }

    public function testRejectsDuplicateEmail(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(
            new User(self::EMAIL, 'existing-hash', new DateTimeImmutable()),
        );
        $repository->expects(self::never())->method('create');

        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionMessage(self::DUPLICATE_ERROR);
        new UserAuthService($repository)->register(self::EMAIL, self::PASSWORD);
    }

    public function testAuthenticatesValidCredentials(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $hash, new DateTimeImmutable(), new DateTimeImmutable());
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with(self::EMAIL)->willReturn($user);

        self::assertSame($user, new UserAuthService($repository)->authenticate(self::EMAIL, self::PASSWORD));
    }

    public function testRejectsInvalidCredentials(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(null);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage(self::CREDENTIALS_ERROR);
        new UserAuthService($repository)->authenticate(self::EMAIL, self::WRONG_PASSWORD);
    }

    public function testFindsOnlyUnverifiedUserWithMatchingPasswordForResend(): void
    {
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $unverified = new User(self::EMAIL, $passwordHash, new DateTimeImmutable());
        $verified = new User(self::EMAIL, $passwordHash, new DateTimeImmutable(), new DateTimeImmutable());
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturnOnConsecutiveCalls($unverified, $unverified, $verified);
        $service = new UserAuthService($repository);

        self::assertSame($unverified, $service->findUnverifiedForResend(' Person@Example.com ', self::PASSWORD));
        self::assertNull($service->findUnverifiedForResend(self::EMAIL, self::WRONG_PASSWORD));
        self::assertNull($service->findUnverifiedForResend(self::EMAIL, self::PASSWORD));
    }

    public function testRejectsLoginBeforeEmailVerification(): void
    {
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $user = new User(self::EMAIL, $passwordHash, new DateTimeImmutable(), null);
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn($user);

        $this->expectException(EmailNotVerifiedException::class);
        $this->expectExceptionMessage(self::UNVERIFIED_EMAIL_ERROR);
        new UserAuthService($repository)->authenticate(self::EMAIL, self::PASSWORD);
    }
}
