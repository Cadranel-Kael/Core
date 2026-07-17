<?php

use TypiCMS\Modules\Core\Models\Setting;
use TypiCMS\Modules\Core\Support\Permissions;

/*
 * Settings are stored by any user holding “update settings” and merged into the
 * typicms config on boot, so the canonical permission list must stay out of
 * reach of that table. These tests pin the order of the arguments given to
 * array_merge in ModuleServiceProvider.
 */

describe('a setting', function (): void {
    test('does not widen the canonical list through a modules row', function (): void {
        $names = Permissions::names();

        Setting::query()->create(['group_name' => 'modules', 'key_name' => 'cats', 'value' => 'read cats']);
        config(['typicms' => array_merge(new Setting()->allToArray(), config('typicms', []))]);

        expect(Permissions::names())->toBe($names)
            ->and(Permissions::has('read cats'))->toBeFalse();
    });

    test('does not widen the canonical list through a permissions row', function (): void {
        $names = Permissions::names();

        Setting::query()->create(['group_name' => 'permissions', 'key_name' => 'globals', 'value' => 'all']);
        config(['typicms' => array_merge(new Setting()->allToArray(), config('typicms', []))]);

        expect(Permissions::names())->toBe($names)
            ->and(Permissions::has('all'))->toBeFalse();
    });

    test('does not empty the canonical list', function (): void {
        Setting::query()->create(['group_name' => 'config', 'key_name' => 'modules', 'value' => '']);
        config(['typicms' => array_merge(new Setting()->allToArray(), config('typicms', []))]);

        expect(Permissions::names())->toHaveCount(97);
    });
});
