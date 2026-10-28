<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use App\Shared\Http\Enum\HttpMethodEnum;
use App\Shared\Http\Enum\ResponseStatusEnum;
use DomainException;
use App\Auth\Infrastructure\Session\CurrentUser;
use App\Shared\Http\CsrfToken;
use App\Shared\Http\Exception\InvalidRequestException;
use App\Shared\Http\Exception\PreconditionRequiredException;
use App\Shared\Http\MapException;
use App\Shared\Http\LoadTestKey;
use App\Shared\Http\RateLimit;
use App\Shared\Http\RateLimiter;
use App\Shared\Http\RequireApiKey;
use App\Shared\Http\RequireMerchant;
use App\Shared\Http\Request;
use App\Shared\Http\Responder;
use App\Shared\Http\Route;
use App\Shared\Http\UsesSession;
use App\Shared\Http\Router;
use App\Shared\Http\Session;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Unit\Support\FrozenClock;
use Tests\Unit\Support\InMemoryCacheStore;
use Tests\Unit\Support\MerchantUserRepository;

final class RouterTest extends TestCase
{
    private const string LOAD_TEST_KEY = 'test-load-test-key';
    private const string ROUTE_PATH = '/';
    private const string MISSING_ROUTE_PATH = '/missing';
    private const string DISPATCHED_MESSAGE = 'route dispatched';
    private const string NOT_FOUND_MESSAGE = '404 Not Found';
    private const string FORBIDDEN_MESSAGE = '403 Forbidden';
    public const string PRODUCT_ID_PARAMETER = 'id';
    private const string PRODUCT_ID = '42';
    private const string PRODUCT_PATH = '/products/42';
    private const string NESTED_PRODUCT_PATH = '/products/42/reviews';
    private const string NON_NUMERIC_PRODUCT_PATH = '/products/abc';
    public const string MAPPED_FAILURE = 'The product does not exist.';
    public const string INVALID_INPUT = 'The limit must be a positive integer.';
    private const string MISSING_API_PATH = '/api/v1/missing';
    private const string PRODUCT_API_PATH = '/api/v1/products/42';
    public const string API_KEY_VARIABLE = 'ROUTER_TEST_API_KEY';
    private const string API_KEY = 'router-test-key';
    public const string RATE_LIMIT_VARIABLE = 'ROUTER_TEST_RATE_LIMIT';
    private const string ONE_REQUEST_PER_MINUTE = '1/60';
    private const string CLIENT = '192.0.2.1';
    private const string OTHER_CLIENT = '192.0.2.2';
    private const string INTERNAL_CLIENT = '172.18.0.7';
    private const string RETRY_DETAIL_PREFIX = 'Too many requests. Try again in ';
    private const string POST_VALUE = 'post route dispatched';
    private const string SESSION_API_PATH = '/api/v1/session';
    private const string PANEL_PATH = '/api/v1/panel/orders';
    private const string CSRF_HEADER = 'x-csrf-token';
    private CsrfToken $csrfToken;
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            (new Session())->start();
        }

        $this->originalSession = $_SESSION;
        $_SESSION = [];
        $this->csrfToken = new CsrfToken(new Session());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        http_response_code(ResponseStatusEnum::OK->value);
    }

    public function testDispatchesAttributedGetRoute(): void
    {
        $controller = new class () {
            public const string PATH = '/';
            public const string MESSAGE = 'route dispatched';
            public bool $called = false;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function index(): void
            {
                $this->called = true;
                echo self::MESSAGE;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, self::ROUTE_PATH));
        $output = ob_get_clean();

        self::assertTrue($controller->called);
        self::assertSame(self::DISPATCHED_MESSAGE, $output);
    }

    public function testReturnsNotFoundForUnknownRoute(): void
    {
        $router = $this->router();
        http_response_code(200);
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, self::MISSING_ROUTE_PATH));
        $output = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertSame(self::NOT_FOUND_MESSAGE, $output);
    }

    public function testUnknownApiRouteIsNotFoundProblem(): void
    {
        $router = $this->router();
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, self::MISSING_API_PATH));
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
        self::assertSame(
            ResponseStatusEnum::NOT_FOUND->value,
            json_decode($output, true, flags: JSON_THROW_ON_ERROR)['status'],
        );
    }

    public function testApiPostDoesNotRequireCsrfTokenBecauseApiIsSessionless(): void
    {
        $controller = new class () {
            public const string PATH = '/api/v1/carts';
            public bool $called = false;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            public function create(): void
            {
                $this->called = true;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        $router->handle(new Request(HttpMethodEnum::POST->value, $controller::PATH));

        self::assertTrue($controller->called);
    }

    public function testMerchantRoutesRejectAnonymousVisitors(): void
    {
        $controller = new class () {
            private const string PATH = '/api/v1/panel/orders';
            public int $calls = 0;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            #[RequireMerchant]
            public function list(): void
            {
                $this->calls++;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        ob_start();
        $router->handle(new Request(HttpMethodEnum::GET->value, self::PANEL_PATH));
        ob_end_clean();

        self::assertSame(0, $controller->calls);
        self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
    }

    public function testSessionApiRouteRequiresTheCsrfTokenHeader(): void
    {
        $controller = new class () {
            private const string PATH = '/api/v1/session';
            public int $calls = 0;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            #[UsesSession]
            public function signIn(): void
            {
                $this->calls++;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        ob_start();
        $router->handle(new Request(HttpMethodEnum::POST->value, self::SESSION_API_PATH));
        $router->handle(new Request(
            HttpMethodEnum::POST->value,
            self::SESSION_API_PATH,
            headers: [self::CSRF_HEADER => $this->csrfToken->create()],
        ));
        ob_end_clean();

        self::assertSame(1, $controller->calls);
    }

    public function testAnswersMethodNotAllowedForAKnownPathWithAnotherMethod(): void
    {
        $controller = new class () {
            public const string PATH = '/api/v1/products/{id:\\d+}';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(): void
            {
            }

            #[Route(HttpMethodEnum::PATCH, self::PATH)]
            public function edit(): void
            {
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        ob_start();
        $router->handle(new Request(HttpMethodEnum::DELETE->value, self::PRODUCT_API_PATH));
        $output = (string) ob_get_clean();

        self::assertSame(ResponseStatusEnum::METHOD_NOT_ALLOWED->value, http_response_code());
        self::assertStringContainsString('This path accepts only GET, PATCH.', $output);
    }

    public function testBuildsOnlyTheControllerOfTheMatchedRoute(): void
    {
        $built = [];
        $products = new class () {
            public const string PATH = '/api/v1/products';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function list(): void
            {
            }
        };
        $reports = new class () {
            public const string PATH = '/api/v1/reports';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(): void
            {
            }
        };
        $router = $this->router();
        $router->registerController($products::class, static function () use ($products, &$built): object {
            $built[] = $products::PATH;

            return $products;
        });
        $router->registerController($reports::class, static function () use ($reports, &$built): object {
            $built[] = $reports::PATH;

            return $reports;
        });

        $router->handle(new Request(HttpMethodEnum::GET->value, $products::PATH));

        self::assertSame([$products::PATH], $built, 'Controllers of other routes, and their database or cache connections, are never built.');
    }

    public function testRateLimitedRouteAnswersTooManyRequestsOnceAClientExceedsItsLimit(): void
    {
        putenv(sprintf('%s=%s', self::RATE_LIMIT_VARIABLE, self::ONE_REQUEST_PER_MINUTE));
        $controller = new class () {
            public const string PATH = '/api/v1/carts';
            public int $calls = 0;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            #[RateLimit(RouterTest::RATE_LIMIT_VARIABLE)]
            public function create(): void
            {
                $this->calls++;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        try {
            $router->handle(new Request(HttpMethodEnum::POST->value, $controller::PATH, clientAddress: self::CLIENT));
            ob_start();
            $router->handle(new Request(HttpMethodEnum::POST->value, $controller::PATH, clientAddress: self::CLIENT));
            $output = ob_get_clean();
            self::assertSame(ResponseStatusEnum::TOO_MANY_REQUESTS->value, http_response_code());
            $router->handle(new Request(HttpMethodEnum::POST->value, $controller::PATH, clientAddress: self::OTHER_CLIENT));
        } finally {
            putenv(self::RATE_LIMIT_VARIABLE);
        }

        self::assertIsString($output);
        self::assertStringContainsString(self::RETRY_DETAIL_PREFIX, $output);
        self::assertSame(2, $controller->calls);
    }

    public function testTheLoadTestKeyLiftsRateLimitsAndAWrongKeyDoesNot(): void
    {
        putenv(sprintf('%s=%s', self::RATE_LIMIT_VARIABLE, self::ONE_REQUEST_PER_MINUTE));
        $controller = new class () {
            public const string PATH = '/api/v1/carts';
            public int $calls = 0;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            #[RateLimit(RouterTest::RATE_LIMIT_VARIABLE)]
            public function create(): void
            {
                $this->calls++;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        $request = static fn (string $key, string $address = self::INTERNAL_CLIENT): Request => new Request(
            HttpMethodEnum::POST->value,
            $controller::PATH,
            headers: ['x-load-test-key' => $key],
            clientAddress: $address,
        );

        try {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $router->handle($request(self::LOAD_TEST_KEY));
            }
            ob_start();
            $router->handle($request('guessed-key'));
            $router->handle($request('guessed-key'));
            $router->handle($request(self::LOAD_TEST_KEY, self::CLIENT));
            $router->handle($request(self::LOAD_TEST_KEY, self::CLIENT));
            ob_end_clean();
        } finally {
            putenv(self::RATE_LIMIT_VARIABLE);
        }

        self::assertSame(ResponseStatusEnum::TOO_MANY_REQUESTS->value, http_response_code());
        self::assertSame(5, $controller->calls, 'Three requests with the key pass the limit of one; a wrong key, or the right key from the internet, gets one request through, then 429.');
    }

    public function testRouteRequiringAnApiKeyRejectsMissingOrWrongKeys(): void
    {
        putenv(sprintf('%s=%s', self::API_KEY_VARIABLE, self::API_KEY));
        $controller = new class () {
            public const string PATH = '/api/v1/reports/daily-revenue';
            public int $calls = 0;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            #[RequireApiKey(RouterTest::API_KEY_VARIABLE)]
            public function report(): void
            {
                $this->calls++;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        try {
            foreach ([[], ['x-api-key' => 'wrong-key']] as $headers) {
                ob_start();
                $router->handle(new Request(HttpMethodEnum::GET->value, $controller::PATH, headers: $headers));
                ob_end_clean();
                self::assertSame(ResponseStatusEnum::UNAUTHORIZED->value, http_response_code());
            }

            $router->handle(new Request(HttpMethodEnum::GET->value, $controller::PATH, headers: ['x-api-key' => self::API_KEY]));
        } finally {
            putenv(self::API_KEY_VARIABLE);
        }

        self::assertSame(1, $controller->calls);
    }

    public function testDispatchesAttributedPostRoute(): void
    {
        $controller = new class () {
            public const string PATH = '/';
            public const string MESSAGE = 'post route dispatched';

            #[Route(HttpMethodEnum::POST, self::PATH)]
            public function submit(): void
            {
                echo self::MESSAGE;
            }
        };
        $request = new Request(
            HttpMethodEnum::POST->value,
            self::ROUTE_PATH,
            headers: [self::CSRF_HEADER => $this->csrfToken->create()],
        );
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();

        $router->handle($request);
        $output = ob_get_clean();

        self::assertSame(self::POST_VALUE, $output);
    }

    public function testRejectsDuplicateAttributedRoutes(): void
    {
        $controller = new class () {
            public const string PATH = '/';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function first(): void
            {
            }

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function second(): void
            {
            }
        };
        $router = $this->router();

        $this->expectException(LogicException::class);
        $router->registerController($controller::class, static fn (): object => $controller);
    }

    public function testRejectsPostRequestsWithoutCsrfToken(): void
    {
        $controller = new class () {
            private const string PATH = '/';
            public bool $called = false;

            #[Route(HttpMethodEnum::POST, self::PATH)]
            public function submit(): void
            {
                $this->called = true;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        ob_start();
        $router->handle(new Request(HttpMethodEnum::POST->value, self::ROUTE_PATH));
        $output = ob_get_clean();

        self::assertFalse($controller->called);
        self::assertSame(403, http_response_code());
        self::assertSame(self::FORBIDDEN_MESSAGE, $output);
    }

    public function testRejectsEveryStateChangingBrowserRequestWithoutCsrfToken(): void
    {
        $controller = new class () {
            private const string PATH = '/';
            public bool $called = false;

            #[Route(HttpMethodEnum::PUT, self::PATH)]
            public function replace(): void
            {
                $this->called = true;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        ob_start();
        $router->handle(new Request(HttpMethodEnum::PUT->value, self::ROUTE_PATH));
        $output = ob_get_clean();

        self::assertFalse($controller->called);
        self::assertSame(self::FORBIDDEN_MESSAGE, $output);
    }

    public function testPassesRequestWithPathParametersToAction(): void
    {
        $controller = new class () {
            public const string PATH = '/products/{id}';
            public ?string $productId = null;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(Request $request): void
            {
                $this->productId = $request->routeParameter(RouterTest::PRODUCT_ID_PARAMETER);
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        $router->handle(new Request(HttpMethodEnum::GET->value, self::PRODUCT_PATH));

        self::assertSame(self::PRODUCT_ID, $controller->productId);
    }

    public function testPathParameterDoesNotMatchAcrossSegments(): void
    {
        $controller = new class () {
            public const string PATH = '/products/{id}';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(Request $request): void
            {
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, self::NESTED_PRODUCT_PATH));
        ob_end_clean();

        self::assertSame(404, http_response_code());
    }

    public function testRejectsRouteMethodWithNonRequestParameter(): void
    {
        $controller = new class () {
            public const string PATH = '/';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(string $value): void
            {
            }
        };

        $this->expectException(LogicException::class);
        $this->router()->registerController($controller::class, static fn (): object => $controller);
    }

    public function testPathParameterConstraintRejectsNonMatchingSegment(): void
    {
        $controller = new class () {
            public const string PATH = '/products/{id:\\d+}';
            public bool $called = false;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(Request $request): void
            {
                $this->called = true;
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, self::NON_NUMERIC_PRODUCT_PATH));
        ob_end_clean();
        $router->handle(new Request(HttpMethodEnum::GET->value, self::PRODUCT_PATH));

        self::assertTrue($controller->called);
    }

    public function testPathParameterConstraintMayUseQuantifiers(): void
    {
        $controller = new class () {
            public const string PATH = '/codes/{code:[a-f]{2}-\\d{3}}';
            public ?string $code = null;

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(Request $request): void
            {
                $this->code = $request->routeParameter('code');
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        $router->handle(new Request(HttpMethodEnum::GET->value, '/codes/ab-123'));

        self::assertSame('ab-123', $controller->code);
    }

    public function testMapsDeclaredExceptionToProblemResponse(): void
    {
        $controller = new class () {
            public const string PATH = '/';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            #[MapException(DomainException::class, ResponseStatusEnum::NOT_FOUND)]
            public function show(): void
            {
                throw new DomainException(RouterTest::MAPPED_FAILURE);
            }
        };

        $problem = $this->problemFrom($controller);

        self::assertSame(ResponseStatusEnum::NOT_FOUND->value, http_response_code());
        self::assertSame(self::MAPPED_FAILURE, $problem['detail']);
    }

    public function testMapsInvalidApiRequestToBadRequestProblem(): void
    {
        $controller = new class () {
            public const string PATH = '/api/v1/products';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(): void
            {
                throw new InvalidRequestException(RouterTest::INVALID_INPUT);
            }
        };

        $problem = $this->problemFrom($controller, $controller::PATH);

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame(self::INVALID_INPUT, $problem['detail']);
    }

    public function testMissingPreconditionIsPreconditionRequiredProblem(): void
    {
        $controller = new class () {
            public const string PATH = '/api/v1/orders/x/cancel';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function cancel(): void
            {
                throw new PreconditionRequiredException(RouterTest::INVALID_INPUT);
            }
        };

        $problem = $this->problemFrom($controller, $controller::PATH);

        self::assertSame(ResponseStatusEnum::PRECONDITION_REQUIRED->value, http_response_code());
        self::assertSame(ResponseStatusEnum::PRECONDITION_REQUIRED->value, $problem['status']);
    }

    public function testInvalidBrowserRequestIsPlainBadRequest(): void
    {
        $controller = new class () {
            public const string PATH = '/login';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(): void
            {
                throw new InvalidRequestException(RouterTest::INVALID_INPUT);
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();

        $router->handle(new Request(HttpMethodEnum::GET->value, $controller::PATH));
        $output = ob_get_clean();

        self::assertSame(ResponseStatusEnum::BAD_REQUEST->value, http_response_code());
        self::assertSame('400 Bad Request', $output);
    }

    public function testDoesNotSwallowUndeclaredExceptions(): void
    {
        $controller = new class () {
            public const string PATH = '/';

            #[Route(HttpMethodEnum::GET, self::PATH)]
            public function show(): void
            {
                throw new DomainException(RouterTest::MAPPED_FAILURE);
            }
        };
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);

        $this->expectException(DomainException::class);
        $router->handle(new Request(HttpMethodEnum::GET->value, self::ROUTE_PATH));
    }

    /** @return array<string, mixed> */
    private function problemFrom(object $controller, string $path = self::ROUTE_PATH): array
    {
        $router = $this->router();
        $router->registerController($controller::class, static fn (): object => $controller);
        ob_start();
        $router->handle(new Request(HttpMethodEnum::GET->value, $path));
        $output = ob_get_clean();
        self::assertIsString($output);
        $problem = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);

        return $problem;
    }

    private function router(): Router
    {
        return new Router(
            $this->csrfToken,
            new Session(),
            new Responder(),
            new CurrentUser(new Session(), new MerchantUserRepository()),
            new RateLimiter(new InMemoryCacheStore(), new FrozenClock(), new NullLogger()),
            new LoadTestKey(self::LOAD_TEST_KEY),
        );
    }
}
