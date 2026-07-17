<?php

use TypiCMS\Modules\Core\Models\User;
use TypiCMS\Modules\Core\Support\Permissions;

describe('the role form', function (): void {
    test('offers every grantable permission', function (): void {
        $response = $this->actingAs(User::factory()->create(['superuser' => true]))
            ->get(route('admin::create-role'))
            ->assertOk();

        foreach (Permissions::names() as $permission) {
            $response->assertSee('value="'.$permission.'"', false);
        }
    });

    test('posts the always granted permissions as hidden inputs', function (string $permission): void {
        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->get(route('admin::create-role'))
            ->assertSee('<input type="hidden" name="checked_permissions[]" value="'.$permission.'" />', false);
    })->with(['change locale', 'update preferences', 'clear cache']);

    test('keeps the identifiers and titles of the global permissions', function (): void {
        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->get(route('admin::create-role'))
            ->assertSee('permission-see-navbar', false)
            ->assertSee('permission-impersonate-users', false)
            ->assertSee('Access dashboard')
            ->assertSee('Empty history')
            ->assertSee(__('Global permissions'))
            ->assertSee(__('Modules permissions'));
    });

    test('offers the permissions of a module registered at runtime', function (): void {
        config(['typicms.modules.cats.permissions' => ['read cats' => 'Read cats']]);

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->get(route('admin::create-role'))
            ->assertSee('value="read cats"', false)
            ->assertSee('Read cats');
    });
});
