<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LevelContentChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LevelContentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $slug)->first() ?? Role::factory()->create(['slug' => $slug]);

        return User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
            ...$attributes,
        ]);
    }

    public function test_only_the_owner_hears_about_a_new_figure(): void
    {
        $teacher = $this->userWithRole('teacher', ['name' => 'Profe Luis']);
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $admin = $this->userWithRole('admin');
        $level = Level::factory()->create();

        $this->actingAs($teacher)
            ->postJson(route('api.levels.contents.store', $level), ['name' => 'Enchufla'])
            ->assertCreated();

        Notification::assertSentTo($owner, LevelContentChangedNotification::class, function (LevelContentChangedNotification $notification) use ($owner, $level): bool {
            $payload = $notification->toDatabase($owner);

            return $payload['changes'] === ['figure_created']
                && $payload['figure_name'] === 'Enchufla'
                && $payload['level_id'] === $level->id
                && $payload['dance_type_id'] === $level->dance_type_id
                && $payload['actor_name'] === 'Profe Luis'
                && $notification->via($owner) === ['database', 'mail'];
        });
        Notification::assertNotSentTo($admin, LevelContentChangedNotification::class);
        Notification::assertNotSentTo($teacher, LevelContentChangedNotification::class);
    }

    public function test_an_update_lists_what_changed(): void
    {
        $teacher = $this->userWithRole('teacher');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create([
            'name' => 'Sombrero',
            'description' => null,
            'video_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
        ]);

        $this->actingAs($teacher)->patchJson(route('api.level-contents.update', $figure), [
            'name' => 'Sombrero',
            'description' => 'Giro con la mano por encima',
            'video_url' => 'https://www.youtube.com/watch?v=bbbbbbbbbbb',
        ])->assertOk();

        Notification::assertSentTo($owner, LevelContentChangedNotification::class, function (LevelContentChangedNotification $notification) use ($owner): bool {
            $mail = $notification->toMail($owner)->render();

            return $notification->changes === ['description_added', 'video_changed']
                && str_contains($mail, 'Se agregó la descripción')
                && str_contains($mail, 'Se cambió el video');
        });
    }

    public function test_removing_the_video_is_reported(): void
    {
        $teacher = $this->userWithRole('teacher');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create(['video_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa']);

        $this->actingAs($teacher)->patchJson(route('api.level-contents.update', $figure), [
            'name' => $figure->name,
            'description' => $figure->description,
            'video_url' => null,
        ])->assertOk();

        Notification::assertSentTo($owner, LevelContentChangedNotification::class, fn (LevelContentChangedNotification $notification): bool => $notification->changes === ['video_removed']);
    }

    public function test_saving_without_changes_does_not_notify(): void
    {
        $teacher = $this->userWithRole('teacher');
        $this->userWithRole('staff', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create();

        $this->actingAs($teacher)->patchJson(route('api.level-contents.update', $figure), [
            'name' => $figure->name,
            'description' => $figure->description,
            'video_url' => $figure->video_url,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_the_owner_editing_is_not_notified(): void
    {
        $owner = $this->userWithRole('admin', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create();

        $this->actingAs($owner)->patchJson(route('api.level-contents.update', $figure), [
            'name' => 'Otro nombre',
            'description' => $figure->description,
            'video_url' => $figure->video_url,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_repeated_edits_mail_once_per_figure_but_always_reach_the_bell(): void
    {
        $teacher = $this->userWithRole('teacher');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create(['name' => 'Uno']);

        foreach (['Dos', 'Tres'] as $name) {
            $this->actingAs($teacher)->patchJson(route('api.level-contents.update', $figure), [
                'name' => $name,
                'description' => $figure->description,
                'video_url' => $figure->video_url,
            ])->assertOk();
        }

        $sent = Notification::sent($owner, LevelContentChangedNotification::class);

        $this->assertCount(2, $sent);
        $this->assertSame(['database', 'mail'], $sent[0]->via($owner));
        $this->assertSame(['database'], $sent[1]->via($owner));
    }

    public function test_deleting_a_figure_is_reported(): void
    {
        $admin = $this->userWithRole('admin');
        $owner = $this->userWithRole('staff', ['is_owner' => 1]);
        $figure = LevelContent::factory()->create(['name' => 'Vacilala']);

        $this->actingAs($admin)
            ->deleteJson(route('api.level-contents.destroy', $figure))
            ->assertNoContent();

        Notification::assertSentTo($owner, LevelContentChangedNotification::class, function (LevelContentChangedNotification $notification) use ($owner): bool {
            return $notification->changes === ['figure_deleted']
                && str_contains($notification->toMail($owner)->subject, 'Vacilala');
        });
    }
}
