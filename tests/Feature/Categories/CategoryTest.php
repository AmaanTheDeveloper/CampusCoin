<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DefaultCategorySeeder;

beforeEach(function () {
    seedDefaultCategories();
});

test('default income categories exist', function () {
    foreach (['Allowance', 'Part-time Job', 'Scholarship', 'Gift', 'Other Income', 'Loan'] as $name) {
        expect(Category::query()->whereNull('user_id')->where('type', 'income')->where('name', $name)->where('is_default', true)->exists())->toBeTrue();
    }
});

test('default expense categories exist', function () {
    foreach (['Food', 'Transport', 'Hostel/Rent', 'Academics', 'Subscriptions', 'Entertainment', 'Miscellaneous', 'Loan Repayment'] as $name) {
        expect(Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', $name)->where('is_default', true)->exists())->toBeTrue();
    }
});

test('seeder is idempotent', function () {
    $before = Category::query()->whereNull('user_id')->count();
    (new DefaultCategorySeeder)->run();
    (new DefaultCategorySeeder)->run();

    expect(Category::query()->whereNull('user_id')->count())->toBe($before);
});

test('user can create personal category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'expense',
            'name' => 'Lab Fee',
            'is_default' => true,
            'user_id' => 999,
        ])
        ->assertRedirect(route('categories.index'));

    $category = Category::query()->where('user_id', $user->id)->first();

    expect($category)->not->toBeNull();
    expect($category->name)->toBe('Lab Fee');
    expect($category->is_default)->toBeFalse();
    expect($category->user_id)->toBe($user->id);
});

test('user can edit own category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->expense()->create(['name' => 'Old']);

    $this->actingAs($user)
        ->put(route('categories.update', $category), [
            'type' => 'expense',
            'name' => 'New Name',
        ])
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->name)->toBe('New Name');
});

test('user can delete unused personal category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->expense()->create();

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    expect(Category::find($category->id))->toBeNull();
});

test('user cannot delete category used by transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->expense()->create();
    Transaction::factory()->for($user)->create(['category_id' => $category->id, 'type' => 'expense']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasErrors('category');

    expect(Category::find($category->id))->not->toBeNull();
});

test('user cannot edit default category', function () {
    $user = User::factory()->create();
    $default = Category::query()->whereNull('user_id')->where('name', 'Food')->firstOrFail();

    $this->actingAs($user)
        ->put(route('categories.update', $default), [
            'type' => 'expense',
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

test('user cannot delete default category', function () {
    $user = User::factory()->create();
    $default = Category::query()->whereNull('user_id')->where('name', 'Food')->firstOrFail();

    $this->actingAs($user)
        ->delete(route('categories.destroy', $default))
        ->assertForbidden();
});

test('user cannot edit another users category', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->put(route('categories.update', $category), [
            'type' => $category->type->value,
            'name' => 'Stolen',
        ])
        ->assertForbidden();
});

test('user cannot delete another users category', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->delete(route('categories.destroy', $category))
        ->assertForbidden();
});

test('duplicate personal category rejected', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->expense()->create(['name' => 'Coffee']);

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'expense',
            'name' => 'Coffee',
        ])
        ->assertSessionHasErrors('name');
});

test('whitespace only category rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'expense',
            'name' => '   ',
        ])
        ->assertSessionHasErrors('name');
});

test('invalid category type rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'transfer',
            'name' => 'Something',
        ])
        ->assertSessionHasErrors('type');
});

test('user index only shows defaults and own categories', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Category::factory()->for($user)->expense()->create(['name' => 'Mine']);
    Category::factory()->for($other)->expense()->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categories/index')
            ->has('categories')
            ->where('categories', fn ($categories) => collect($categories)->contains('name', 'Mine')
                && ! collect($categories)->contains('name', 'Theirs')
                && collect($categories)->contains('name', 'Food'))
        );
});
