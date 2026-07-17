<?php

use Spatie\Permission\Models\Permission;
use TypiCMS\Modules\Core\Models\Role;
use TypiCMS\Modules\Core\Models\User;
use TypiCMS\Modules\Core\Support\Permissions;

function roleManager(array $permissions = []): User
{
    $user = User::factory()->create(['superuser' => false]);
    $user->givePermissionTo(array_merge(['update roles', 'create roles', 'read roles'], $permissions));

    return $user;
}

describe('non-superuser with “update roles” permission', function (): void {
    test('cannot store an unknown permission', function (): void {
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['superuser'],
            ])
            ->assertSessionHasErrors('checked_permissions.0');

        expect(Permission::query()->where('name', 'superuser')->exists())->toBeFalse()
            ->and($role->refresh()->permissions)->toBeEmpty();
    });

    test('cannot grant the all sentinel', function (): void {
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['all'],
            ])
            ->assertSessionHasErrors('checked_permissions.0');

        expect(Permission::query()->where('name', 'all')->exists())->toBeFalse()
            ->and($role->refresh()->permissions)->toBeEmpty();
    });

    test('cannot grant a permission it does not have itself', function (): void {
        Permissions::sync();
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['impersonate users'],
            ])
            ->assertForbidden();

        expect($role->refresh()->permissions)->toBeEmpty();
    });

    test('cannot take a permission it does not have itself', function (): void {
        Permissions::sync();
        $role = Role::query()->create(['name' => 'editors']);
        $role->syncPermissions(['impersonate users']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => [],
            ])
            ->assertForbidden();

        expect($role->refresh()->permissions->pluck('name')->all())->toBe(['impersonate users']);
    });

    test('cannot create a role holding a permission it does not have itself', function (): void {
        Permissions::sync();

        $this->actingAs(roleManager())
            ->post(route('admin::store-role'), [
                'name' => 'sneaky',
                'checked_permissions' => ['impersonate users'],
            ])
            ->assertForbidden();

        expect(Role::query()->where('name', 'sneaky')->exists())->toBeFalse();
    });

    test('can grant a permission it has itself', function (): void {
        Permissions::sync();
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(roleManager(['read news']))
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['read news'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($role->refresh()->permissions->pluck('name')->all())->toBe(['read news']);
    });

    test('can rename a role without touching its permissions', function (): void {
        Permissions::sync();
        $role = Role::query()->create(['name' => 'editors']);
        $role->syncPermissions(['impersonate users']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'renamed editors',
                'checked_permissions' => ['impersonate users'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($role->refresh()->name)->toBe('renamed editors')
            ->and($role->permissions->pluck('name')->all())->toBe(['impersonate users']);
    });

    test('cannot rewrite the guard of a role', function (): void {
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(roleManager())
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'guard_name' => 'api',
            ])
            ->assertSessionHasNoErrors();

        expect($role->refresh()->guard_name)->toBe('web');
    });
});

describe('superuser', function (): void {
    test('can grant any permission of the list', function (): void {
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['impersonate users', 'read registrations'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($role->refresh()->permissions->pluck('name')->all())
            ->toEqualCanonicalizing(['impersonate users', 'read registrations']);
    });

    test('cannot store an unknown permission either', function (): void {
        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['superuser'],
            ])
            ->assertSessionHasErrors('checked_permissions.0');

        expect(Permission::query()->where('name', 'superuser')->exists())->toBeFalse();
    });
});

describe('the permissions of the list', function (): void {
    test('are stored when a role is saved', function (): void {
        Permission::query()->whereIn('name', ['read registrations', 'update registrations'])->delete();

        $role = Role::query()->create(['name' => 'editors']);

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->put(route('admin::update-role', $role), [
                'name' => 'editors',
                'checked_permissions' => ['read registrations'],
            ])
            ->assertSessionHasNoErrors();

        expect(Permission::query()->where('name', 'read registrations')->exists())->toBeTrue()
            ->and($role->refresh()->permissions->pluck('name')->all())->toBe(['read registrations']);
    });

    test('are all stored by sync', function (): void {
        Permissions::sync();

        expect(Permission::query()->whereIn('name', Permissions::names())->count())
            ->toBe(count(Permissions::names()));
    });

    test('are stored only once', function (): void {
        Permissions::sync();
        Permissions::sync();

        expect(Permission::query()->where('name', 'read registrations')->count())->toBe(1);
    });
});
