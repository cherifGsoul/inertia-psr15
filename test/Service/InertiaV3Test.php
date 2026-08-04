<?php

declare(strict_types=1);

namespace InertiaPsr15Test\Service;

use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Service\Inertia;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

use function array_keys;
use function json_decode;

final class InertiaV3Test extends TestCase
{
    public function testSerializesV3MetadataAndNeverInvokesPlainCallableStrings(): void
    {
        $inertia = $this->inertia([
            'X-Inertia' => 'true',
        ]);
        $inertia->encryptHistory();
        $inertia->clearHistory();
        $inertia->preserveFragment();
        $inertia->share('auth.user', [
            'id' => 1,
        ]);

        $page = $this->page($inertia->render('Feed', [
            'later'   => Inertia::defer(fn () => ['safe'], 'sidebar'),
            'posts'   => Inertia::merge([
                'data' => [[
                    'id' => 1,
                ]],
            ])->append('data', 'id'),
            'plans'   => Inertia::once(fn () => ['basic'])->as('all-plans'),
            'literal' => 'phpinfo',
            'feed'    => Inertia::scroll([
                'data'         => [],
                'current_page' => 1,
                'next_page'    => 2,
            ]),
        ]));

        self::assertSame('phpinfo', $page['props']['literal']);
        self::assertSame([
            'sidebar' => ['later'],
        ], $page['deferredProps']);
        self::assertSame(['posts.data', 'feed.data'], $page['mergeProps']);
        self::assertSame(['posts.data.id'], $page['matchPropsOn']);
        self::assertSame(['auth'], $page['sharedProps']);
        self::assertSame([
            'prop'      => 'plans',
            'expiresAt' => null,
        ], $page['onceProps']['all-plans']);
        self::assertSame([
            'pageName'     => 'page',
            'previousPage' => null,
            'nextPage'     => 2,
            'currentPage'  => 1,
        ], $page['scrollProps']['feed']);
        self::assertTrue($page['encryptHistory']);
        self::assertTrue($page['clearHistory']);
        self::assertTrue($page['preserveFragment']);
    }

    public function testResolvesExplicitDeferredPropsRescuesFailuresAndSkipsKnownOnceProps(): void
    {
        $inertia = $this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'deferred,broken,plans',
            'X-Inertia-Except-Once-Props' => 'plans',
        ]);
        $page = $this->page($inertia->render('Dashboard', [
            'deferred' => Inertia::defer(fn () => 'loaded'),
            'broken'   => Inertia::defer(static function(): never {
                throw new RuntimeException('sensitive failure');
            }, rescue: true),
            'plans'    => Inertia::once(fn () => ['must not run']),
        ]));

        self::assertSame('loaded', $page['props']['deferred']);
        self::assertArrayNotHasKey('broken', $page['props']);
        self::assertSame(['must not run'], $page['props']['plans']); // Explicit partial reload refreshes once props.
        self::assertSame(['broken'], $page['rescuedProps']);
        self::assertSame([
            'prop'      => 'plans',
            'expiresAt' => null,
        ], $page['onceProps']['plans']);
    }

    public function testKnownOncePropIsNotResolvedOnARegularVisit(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Except-Once-Props' => 'plans',
        ])->render('Billing', [
            'plans' => Inertia::once(static function(): never {
                throw new RuntimeException('must not resolve');
            }),
        ]));

        self::assertArrayNotHasKey('plans', $page['props']);
        self::assertSame([
            'prop'      => 'plans',
            'expiresAt' => null,
        ], $page['onceProps']['plans']);
    }

    public function testRejectsUnsafeWrapperInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Inertia::scroll([], '../data');
    }

    public function testRejectsUnsafeMergePathsAndScrollMetadata(): void
    {
        foreach ([
            static fn () => Inertia::merge([])->append("items\r\nX-Evil: yes"),
            static fn () => Inertia::merge([])->append('items', "id\x00"),
            static fn () => Inertia::scroll([], metadata: [
                'pageName'     => "page\nInjected",
                'previousPage' => null,
                'nextPage'     => null,
                'currentPage'  => 1,
            ])->metadata([]),
        ] as $factory) {
            try {
                $factory();
                self::fail('Unsafe protocol metadata must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsControlCharactersInInertiaRedirectLocations(): void
    {
        $inertia = $this->inertia([
            'X-Inertia' => 'true',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $inertia->location("/safe\r\nX-Evil: yes");
    }

    public function testRejectsUnsafeDeferredGroupsOnceKeysAndNegativeTtls(): void
    {
        foreach ([
            static fn () => Inertia::defer(static fn () => null, "group\ninvalid"),
            static fn () => Inertia::once(null)->as("cache\rkey"),
            static fn () => Inertia::once(null)->until(-1),
        ] as $factory) {
            try {
                $factory();
                self::fail('Unsafe once/deferred metadata must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMalformedUtf8FailsClosedBeforeAnInertiaJsonResponseIsCreated(): void
    {
        $this->expectException(JsonException::class);

        $this->inertia([
            'X-Inertia' => 'true',
        ])->render('Users', [
            'invalid' => "\xB1\x31",
        ]);
    }

    public function testInvalidPartialHeaderPathsCannotSelectOptionalProps(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Users',
            'X-Inertia-Partial-Data'      => 'users..email',
        ])->render('Users', [
            'users' => Inertia::optional(static function(): never {
                throw new RuntimeException('must not resolve');
            }),
        ]));

        self::assertArrayNotHasKey('users', $page['props']);
    }

    public function testUnpacksSharedAndPageDottedPropsAndAlwaysIncludesErrors(): void
    {
        $inertia = $this->inertia([
            'X-Inertia' => 'true',
        ]);
        $inertia->share('auth.user', [
            'id' => 1,
        ]);
        $inertia->shareOnce('auth.countries', fn () => ['UA']);

        $page = $this->page($inertia->render('Dashboard', [
            'auth.name' => 'Jane',
            'errors'    => [
                'email' => 'Invalid',
            ],
        ]));

        self::assertSame([
            'user'      => [
                'id' => 1,
            ],
            'countries' => ['UA'],
            'name'      => 'Jane',
        ], $page['props']['auth']);
        self::assertSame([
            'email' => 'Invalid',
        ], $page['props']['errors']);
        self::assertSame(['auth'], $page['sharedProps']);
        self::assertArrayHasKey('auth.countries', $page['onceProps']);

        $partial = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'name',
        ])->render('Dashboard', [
            'name' => 'Jane',
        ]));
        self::assertSame([], $partial['props']['errors']);
    }

    public function testCollectsMetadataForDeferredAndOptionalWrapperChains(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia' => 'true',
        ])->render('Dashboard', [
            'activity' => Inertia::defer(fn () => [
                'id' => 1,
            ])->deepMerge()->once(),
            'settings' => Inertia::optional(fn () => [
                'dark' => true,
            ])->once(),
        ]));

        self::assertSame([
            'default' => ['activity'],
        ], $page['deferredProps']);
        self::assertSame(['activity'], $page['deepMergeProps']);
        self::assertSame(['activity', 'settings'], array_keys($page['onceProps']));
        self::assertArrayNotHasKey('activity', $page['props']);
        self::assertArrayNotHasKey('settings', $page['props']);
    }

    public function testScrollResetSuppressesMergeAndEmitsResetMetadata(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Feed',
            'X-Inertia-Partial-Data'      => 'feed',
            'X-Inertia-Reset'             => 'feed',
        ])->render('Feed', [
            'feed' => Inertia::scroll([
                'data'         => [],
                'current_page' => 1,
            ]),
        ]));

        self::assertArrayNotHasKey('mergeProps', $page);
        self::assertTrue($page['scrollProps']['feed']['reset']);
    }

    public function testOnceKeysRejectWhitespaceAndCommaSeparators(): void
    {
        foreach ([' plans', 'plans ', 'plans,other'] as $key) {
            try {
                Inertia::once(null)->as($key);
                self::fail('Unsafe once key must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCachedDeferredOncePropDoesNotAdvertiseDeferredReload(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Except-Once-Props' => 'stats',
        ])->render('Dashboard', [
            'stats' => Inertia::defer(static function(): never {
                throw new RuntimeException('must not resolve');
            })->once(),
        ]));

        self::assertArrayNotHasKey('stats', $page['props']);
        self::assertArrayNotHasKey('deferredProps', $page);
        self::assertArrayHasKey('stats', $page['onceProps']);
    }

    public function testNestedPartialDeferredRescueDoesNotBypassTheWrapper(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data'      => 'auth.user',
        ])->render('Dashboard', [
            'auth' => Inertia::defer(static function(): never {
                throw new RuntimeException('must be rescued');
            }, rescue: true),
        ]));

        self::assertArrayNotHasKey('auth', $page['props']);
        self::assertSame(['auth'], $page['rescuedProps']);
    }

    public function testDefaultErrorsIsAnEmptyJsonObject(): void
    {
        $response = $this->inertia([
            'X-Inertia' => 'true',
        ])->render('Dashboard');

        self::assertStringContainsString('"errors":{}', (string) $response->getBody());
    }

    public function testNestedPartialExceptResolvesOptionalWrapperThenExcludesTheRequestedChild(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Except'    => 'auth.user',
        ])->render('Dashboard', [
            'auth' => Inertia::optional(fn () => [
                'user'         => 'hidden',
                'internalFlag' => true,
            ]),
        ]));

        self::assertSame([
            'internalFlag' => true,
        ], $page['props']['auth']);
    }

    public function testNestedPartialExceptRescuesADeferredWrapperFailure(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Except'    => 'auth.user',
        ])->render('Dashboard', [
            'auth' => Inertia::defer(static function(): never {
                throw new RuntimeException('must be rescued');
            }, rescue: true),
        ]));

        self::assertArrayNotHasKey('auth', $page['props']);
        self::assertSame(['auth'], $page['rescuedProps']);
    }

    public function testPartialScrollPrependIntentUsesPrependMetadata(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                              => 'true',
            'X-Inertia-Partial-Component'            => 'Feed',
            'X-Inertia-Partial-Data'                 => 'feed',
            'X-Inertia-Infinite-Scroll-Merge-Intent' => 'prepend',
        ])->render('Feed', [
            'feed' => Inertia::scroll([
                'data'         => [],
                'current_page' => 2,
                'prev_page'    => 1,
            ]),
        ]));

        self::assertSame(['feed.data'], $page['prependProps']);
        self::assertArrayNotHasKey('mergeProps', $page);
        self::assertSame(1, $page['scrollProps']['feed']['previousPage']);
    }

    public function testFreshOncePropOverridesTheClientOnceCache(): void
    {
        $page = $this->page($this->inertia([
            'X-Inertia'                   => 'true',
            'X-Inertia-Except-Once-Props' => 'plans',
        ])->render('Billing', [
            'plans' => Inertia::once(fn () => ['fresh'])->fresh(),
        ]));

        self::assertSame(['fresh'], $page['props']['plans']);
        self::assertSame('plans', $page['onceProps']['plans']['prop']);
    }

    /** @param array<non-empty-string, array<string>|string> $headers */
    private function inertia(array $headers): Inertia
    {
        $request = new ServerRequest([], [], '/users?filter=active', 'GET', 'php://memory', $headers);
        $root    = new class implements RootViewProviderInterface {
            public function __invoke(Page $page): string
            {
                return '<html></html>';
            }
        };

        return new Inertia($request, new ResponseFactory(), new StreamFactory(), $root);
    }

    /** @return array<string, mixed> */
    private function page(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
