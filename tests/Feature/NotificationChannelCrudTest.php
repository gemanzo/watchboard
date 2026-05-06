<?php

use App\Models\NotificationChannel;
use App\Models\User;

// ─── Index ────────────────────────────────────────────────────────────────────

test('authenticated user can view notification channels index', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->get(route('notification-channels.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotificationChannels/Index'));
});

test('guest is redirected from notification channels index', function () {
    $this->get(route('notification-channels.index'))
        ->assertRedirect(route('login'));
});

test('index lists only the authenticated user\'s channels', function () {
    $user  = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);

    NotificationChannel::factory()->create(['user_id' => $user->id, 'label' => 'Mine']);
    NotificationChannel::factory()->create(['user_id' => $other->id, 'label' => 'Theirs']);

    $this->actingAs($user)
        ->get(route('notification-channels.index'))
        ->assertInertia(fn ($page) => $page
            ->component('NotificationChannels/Index')
            ->has('channels', 1)
            ->where('channels.0.label', 'Mine')
        );
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('user can view the create channel form', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->get(route('notification-channels.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotificationChannels/Create'));
});

// ─── Store ────────────────────────────────────────────────────────────────────

test('user can create a webhook channel', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'      => 'webhook',
            'label'     => 'My Webhook',
            'is_active' => true,
            'config'    => [
                'url'             => 'https://example.com/webhook',
                'secret'          => 'my-secret',
                'timeout_seconds' => 10,
            ],
        ])
        ->assertRedirect(route('notification-channels.index'));

    $channel = $user->notificationChannels()->first();
    expect($channel)->not->toBeNull()
        ->and($channel->type)->toBe('webhook')
        ->and($channel->label)->toBe('My Webhook')
        ->and($channel->config['url'])->toBe('https://example.com/webhook')
        ->and($channel->config['secret'])->toBe('my-secret');
});

test('user can create a slack channel', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'      => 'slack',
            'label'     => 'Slack Alerts',
            'is_active' => true,
            'config'    => ['webhook_url' => 'https://hooks.slack.com/services/abc/def/ghi'],
        ])
        ->assertRedirect(route('notification-channels.index'));

    expect($user->notificationChannels()->first()->type)->toBe('slack');
});

test('user can create an email channel', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'      => 'email',
            'label'     => 'Ops Team',
            'is_active' => true,
            'config'    => ['address' => 'ops@example.com'],
        ])
        ->assertRedirect(route('notification-channels.index'));

    expect($user->notificationChannels()->first()->config['address'])->toBe('ops@example.com');
});

test('store rejects webhook without url', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'   => 'webhook',
            'label'  => 'Bad',
            'config' => ['url' => ''],
        ])
        ->assertSessionHasErrors('config.url');
});

test('store rejects invalid channel type', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'   => 'sms',
            'label'  => 'Bad',
            'config' => [],
        ])
        ->assertSessionHasErrors('type');
});

// ─── Edit ─────────────────────────────────────────────────────────────────────

test('user can view edit form for own channel', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('notification-channels.edit', $channel))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('NotificationChannels/Edit')
            ->where('channel.id', $channel->id)
        );
});

test('user cannot view edit form for another user\'s channel', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $other   = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('notification-channels.edit', $channel))
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('user can update own channel label', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->webhook()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('notification-channels.update', $channel), [
            'type'      => 'webhook',
            'label'     => 'Updated Label',
            'is_active' => true,
            'config'    => ['url' => 'https://example.com/hook', 'secret' => null, 'timeout_seconds' => 10],
        ])
        ->assertRedirect(route('notification-channels.index'));

    expect($channel->fresh()->label)->toBe('Updated Label');
});

test('user cannot update another user\'s channel', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $other   = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->webhook()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->put(route('notification-channels.update', $channel), [
            'type'   => 'webhook',
            'label'  => 'Hacked',
            'config' => ['url' => 'https://evil.com'],
        ])
        ->assertForbidden();
});

// ─── Destroy ─────────────────────────────────────────────────────────────────

test('user can delete own channel', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('notification-channels.destroy', $channel))
        ->assertRedirect(route('notification-channels.index'));

    expect(NotificationChannel::find($channel->id))->toBeNull();
});

test('user cannot delete another user\'s channel', function () {
    $user    = User::factory()->create(['plan' => 'pro']);
    $other   = User::factory()->create(['plan' => 'pro']);
    $channel = NotificationChannel::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->delete(route('notification-channels.destroy', $channel))
        ->assertForbidden();
});

// ─── Plan gating ──────────────────────────────────────────────────────────────

test('free user sees canManageChannels false on index', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->get(route('notification-channels.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canManageChannels', false)
            ->where('channels', [])
        );
});

test('pro user sees canManageChannels true on index', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->get(route('notification-channels.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canManageChannels', true)
        );
});

test('free user is redirected from create form', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->get(route('notification-channels.create'))
        ->assertRedirect(route('notification-channels.index'));
});

test('free user cannot create a channel via POST', function () {
    $user = User::factory()->create(['plan' => 'free']);

    $this->actingAs($user)
        ->post(route('notification-channels.store'), [
            'type'   => 'webhook',
            'label'  => 'Test',
            'config' => ['url' => 'https://example.com/hook'],
        ])
        ->assertForbidden();

    expect($user->notificationChannels()->count())->toBe(0);
});

test('pro user can access create form', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    $this->actingAs($user)
        ->get(route('notification-channels.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NotificationChannels/Create'));
});
