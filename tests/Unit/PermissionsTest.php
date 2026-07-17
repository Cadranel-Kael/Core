<?php

use TypiCMS\Modules\Core\Support\Permissions;

describe('canonical permission list', function (): void {
    test('is the global permissions plus the module ones and nothing else', function (): void {
        $expected = array_merge(
            array_keys(Permissions::alwaysGranted()),
            array_keys(Permissions::globals()),
        );

        foreach (config('typicms.modules') as $data) {
            $expected = array_merge($expected, array_keys($data['permissions'] ?? []));
        }

        expect(Permissions::names())->toEqualCanonicalizing(array_unique($expected));
    });

    test('holds no duplicate', function (): void {
        $names = Permissions::names();

        expect(array_unique($names))->toHaveCount(count($names));
    });

    test('holds only strings', function (): void {
        expect(array_filter(Permissions::names(), fn ($name): bool => ! is_string($name)))->toBe([]);
    });

    test('does not grant a permission twice', function (): void {
        expect(array_intersect(array_keys(Permissions::alwaysGranted()), array_keys(Permissions::globals())))->toBe([]);
    });

    test('never contains the all sentinel', function (): void {
        expect(Permissions::has('all'))->toBeFalse();
    });

    test('never contains a route name', function (): void {
        expect(Permissions::has('update-registration'))->toBeFalse()
            ->and(Permissions::has('update registrations'))->toBeTrue();
    });

    test('never contains a permission no module offers anymore', function (string $name): void {
        expect(Permissions::has($name))->toBeFalse();
    })->with([
        'read subscriptions',
        'read forum_categories',
        'create forum_categories',
        'update forum_categories',
        'delete forum_categories',
        'read forum_discussions',
        'delete forum_discussions',
    ]);
});

describe('global permissions', function (): void {
    test('are kept when no module is configured', function (): void {
        config(['typicms.modules' => []]);

        expect(Permissions::names())->toEqualCanonicalizing([
            'change locale',
            'update preferences',
            'clear cache',
            'see navbar',
            'see dashboard',
            'read settings',
            'update settings',
            'see history',
            'clear history',
            'see unpublished items',
            'impersonate users',
        ]);
    });

    test('are kept when the module config is not an array', function (): void {
        config(['typicms.modules' => 'broken']);

        expect(Permissions::names())->toHaveCount(11);
    });
});

describe('module permissions', function (): void {
    test('are read from the config of a module registered at runtime', function (): void {
        config(['typicms.modules.cats.permissions' => ['read cats' => 'Read']]);

        expect(Permissions::has('read cats'))->toBeTrue();
    });

    test('are never memoized', function (): void {
        expect(Permissions::has('read cats'))->toBeFalse();

        config(['typicms.modules.cats.permissions' => ['read cats' => 'Read']]);

        expect(Permissions::has('read cats'))->toBeTrue();
    });

    test('are dropped when the module has no permission', function (): void {
        config(['typicms.modules.cats' => ['sidebar' => ['weight' => 10]]]);

        expect(Permissions::grouped())->not->toHaveKey('Cats');
    });

    test('are dropped when malformed', function (): void {
        config([
            'typicms.modules.bad' => ['permissions' => 'not an array'],
            'typicms.modules.worse' => ['permissions' => [0 => 'integer key', 'array label' => ['not a string']]],
        ]);

        expect(Permissions::has('integer key'))->toBeFalse()
            ->and(Permissions::has('array label'))->toBeFalse()
            ->and(array_filter(Permissions::names(), fn ($name): bool => ! is_string($name)))->toBe([]);
    });
});

describe('grouped permissions', function (): void {
    test('merge the modules sharing the same translated title', function (): void {
        config([
            'typicms.modules.cats' => ['permissions' => ['read cats' => 'Read']],
            'typicms.modules.Cats' => ['permissions' => ['delete cats' => 'Delete']],
        ]);

        expect(Permissions::grouped()['Cats'])->toBe(['read cats' => 'Read', 'delete cats' => 'Delete']);
    });

    test('are sorted by title', function (): void {
        $titles = array_keys(Permissions::grouped());
        $sorted = $titles;
        sort($sorted, SORT_LOCALE_STRING);

        expect($titles)->toBe($sorted);
    });
});
