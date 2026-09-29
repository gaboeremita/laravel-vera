<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('sets, changes and clears the creator password, stored hashed and never returned', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('creator-password.show'))->assertExactJson(['isSet' => false]);

    $this->putJson(route('creator-password.update'), ['password' => 'lighthouse keeper'])->assertNoContent();
    $stored = $user->fresh()->getAttributes()['creator_password'];
    expect($stored)->not->toBe('lighthouse keeper')->and(Hash::check('lighthouse keeper', $stored))->toBeTrue();
    $this->getJson(route('creator-password.show'))->assertExactJson(['isSet' => true]);

    $this->putJson(route('creator-password.update'), ['password' => 'a new tide rises'])->assertNoContent();
    expect(Hash::check('a new tide rises', $user->fresh()->creator_password))->toBeTrue();

    $this->putJson(route('creator-password.update'), ['password' => null])->assertNoContent();
    expect($user->fresh()->creator_password)->toBeNull()
        ->and($user->fresh()->toArray())->not->toHaveKey('creator_password');
});

it('refuses a password that is too short', function () {
    $this->actingAs(User::factory()->create())->putJson(route('creator-password.update'), ['password' => 'short'])->assertJsonValidationErrors('password');
});
