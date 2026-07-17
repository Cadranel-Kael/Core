<?php

use TypiCMS\Modules\Core\Models\Role;
use TypiCMS\Modules\Core\Models\User;
use TypiCMS\Modules\Core\Support\Permissions;

function userManager(): User
{
    $user = User::factory()->create(['superuser' => false]);
    $user->givePermissionTo(['update users', 'create users', 'read users', 'edit profile']);

    return $user;
}

function administratorRole(): Role
{
    Permissions::sync();
    $role = Role::query()->create(['name' => 'administrator role']);
    $role->syncPermissions(Permissions::names());

    return $role;
}

function userPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'email' => $user->email,
        'first_name' => 'First',
        'last_name' => 'Last',
        'locale' => 'en',
    ], $overrides);
}

describe('non-superuser with “update users” permission', function (): void {
    test('cannot give itself the administrator role', function (): void {
        $actor = userManager();
        $administrator = administratorRole();

        $this->actingAs($actor)
            ->put(route('admin::update-user', $actor), userPayload($actor, [
                'checked_roles' => [$administrator->id],
            ]))
            ->assertForbidden();

        expect($actor->refresh()->roles)->toBeEmpty()
            ->and($actor->can('impersonate users'))->toBeFalse();
    });

    test('cannot give the administrator role to somebody else', function (): void {
        $administrator = administratorRole();
        $target = User::factory()->create(['superuser' => false]);

        $this->actingAs(userManager())
            ->put(route('admin::update-user', $target), userPayload($target, [
                'checked_roles' => [$administrator->id],
            ]))
            ->assertForbidden();

        expect($target->refresh()->roles)->toBeEmpty();
    });

    test('cannot create a user holding the administrator role', function (): void {
        $administrator = administratorRole();

        $this->actingAs(userManager())
            ->post(route('admin::store-user'), [
                'email' => 'accomplice@gmail.com',
                'first_name' => 'A',
                'last_name' => 'B',
                'locale' => 'en',
                'checked_roles' => [$administrator->id],
            ])
            ->assertForbidden();

        expect(User::query()->where('email', 'accomplice@gmail.com')->exists())->toBeFalse();
    });

    test('cannot take a role carrying permissions it does not have', function (): void {
        $administrator = administratorRole();
        $target = User::factory()->create(['superuser' => false]);
        $target->assignRole($administrator);

        $this->actingAs(userManager())
            ->put(route('admin::update-user', $target), userPayload($target, [
                'checked_roles' => [],
            ]))
            ->assertForbidden();

        expect($target->refresh()->roles->pluck('name')->all())->toBe(['administrator role']);
    });

    test('cannot give a role that does not exist', function (): void {
        $actor = userManager();

        $this->actingAs($actor)
            ->put(route('admin::update-user', $actor), userPayload($actor, [
                'checked_roles' => [999999],
            ]))
            ->assertSessionHasErrors('checked_roles.0');

        expect($actor->refresh()->roles)->toBeEmpty();
    });

    test('can give a role carrying only permissions it has', function (): void {
        $actor = userManager();
        $role = Role::query()->create(['name' => 'readers']);
        $role->syncPermissions(['read users']);
        $target = User::factory()->create(['superuser' => false]);

        $this->actingAs($actor)
            ->put(route('admin::update-user', $target), userPayload($target, [
                'checked_roles' => [$role->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($target->refresh()->roles->pluck('name')->all())->toBe(['readers']);
    });

    test('can give a role carrying no permission', function (): void {
        $role = Role::query()->create(['name' => 'visitors role']);
        $target = User::factory()->create(['superuser' => false]);

        $this->actingAs(userManager())
            ->put(route('admin::update-user', $target), userPayload($target, [
                'checked_roles' => [$role->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($target->refresh()->roles->pluck('name')->all())->toBe(['visitors role']);
    });

    test('can update a user without touching its roles', function (): void {
        $administrator = administratorRole();
        $target = User::factory()->create(['superuser' => false, 'email' => 'target@gmail.com']);
        $target->assignRole($administrator);

        $this->actingAs(userManager())
            ->put(route('admin::update-user', $target), userPayload($target, [
                'email' => 'renamed-target@gmail.com',
                'checked_roles' => [$administrator->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($target->refresh()->email)->toBe('renamed-target@gmail.com')
            ->and($target->roles->pluck('name')->all())->toBe(['administrator role']);
    });
});

describe('superuser', function (): void {
    test('can give the administrator role', function (): void {
        $administrator = administratorRole();
        $target = User::factory()->create(['superuser' => false]);

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->put(route('admin::update-user', $target), userPayload($target, [
                'checked_roles' => [$administrator->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($target->refresh()->roles->pluck('name')->all())->toBe(['administrator role']);
    });

    test('can create a user holding the administrator role', function (): void {
        $administrator = administratorRole();

        $this->actingAs(User::factory()->create(['superuser' => true]))
            ->post(route('admin::store-user'), [
                'email' => 'new-admin@gmail.com',
                'first_name' => 'A',
                'last_name' => 'B',
                'locale' => 'en',
                'checked_roles' => [$administrator->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(User::query()->where('email', 'new-admin@gmail.com')->first()->roles->pluck('name')->all())
            ->toBe(['administrator role']);
    });
});
