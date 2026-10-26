<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\RidesRelationManager;
use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists players with their ride totals and never shows their e-mail', function (): void {
    $player = User::factory()->create(['nickname' => 'Camille Rider', 'email' => 'camille@example.org']);
    Ride::factory()->for($player)->create(['distance_m' => 12_300]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$player])
        ->assertSee('Camille Rider')
        ->assertSee('12.3 km')
        ->assertDontSee('camille@example.org');
});

it('searches by name only, not by e-mail', function (): void {
    $player = User::factory()->create(['nickname' => 'Camille Rider', 'email' => 'secret@example.org']);

    Livewire::test(ListUsers::class)
        ->searchTable('secret@example.org')
        ->assertCanNotSeeTableRecords([$player])
        ->searchTable('Camille')
        ->assertCanSeeTableRecords([$player]);
});

it('shows what a player rode on their page', function (): void {
    $player = User::factory()->create(['email' => 'camille@example.org']);
    $course = Course::factory()->create(['name' => ['fr' => 'Corniche test']]);
    $ride = Ride::factory()->for($player)->for($course)->create();

    Livewire::test(RidesRelationManager::class, ['ownerRecord' => $player, 'pageClass' => ViewUser::class])
        ->assertCanSeeTableRecords([$ride])
        ->assertSee('Corniche test');

    Livewire::test(ViewUser::class, ['record' => $player->getKey()])->assertDontSee('camille@example.org');
});

it('hides the admins from the players listing', function (): void {
    $player = User::factory()->create();
    $staff = User::factory()->create(['is_admin' => true]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$player])
        ->assertCanNotSeeTableRecords([$staff]);
});
