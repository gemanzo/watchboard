<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotificationChannelRequest;
use App\Http\Requests\UpdateNotificationChannelRequest;
use App\Models\NotificationChannel;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationChannelController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManageChannels = (bool) ($user->planConfig()['notification_channels'] ?? false);

        $channels = $canManageChannels
            ? $user->notificationChannels()
                ->orderBy('created_at')
                ->get()
                ->map(fn (NotificationChannel $ch) => [
                    'id'        => $ch->id,
                    'type'      => $ch->type,
                    'label'     => $ch->label,
                    'is_active' => $ch->is_active,
                    'summary'   => $this->configSummary($ch),
                ])
            : collect();

        return Inertia::render('NotificationChannels/Index', [
            'channels'          => $channels,
            'canManageChannels' => $canManageChannels,
        ]);
    }

    public function create(Request $request): RedirectResponse|Response
    {
        if (! ($request->user()->planConfig()['notification_channels'] ?? false)) {
            return redirect()->route('notification-channels.index');
        }

        return Inertia::render('NotificationChannels/Create');
    }

    public function store(StoreNotificationChannelRequest $request): RedirectResponse
    {
        if (! ($request->user()->planConfig()['notification_channels'] ?? false)) {
            abort(403, 'I canali di notifica sono disponibili solo nel piano Pro.');
        }

        $data = $request->validated();

        $request->user()->notificationChannels()->create([
            'type'      => $data['type'],
            'label'     => $data['label'],
            'is_active' => $data['is_active'] ?? true,
            'config'    => $this->normalizeConfig($data['type'], $data['config']),
        ]);

        return redirect()->route('notification-channels.index')
            ->with('message', 'Canale creato con successo.');
    }

    public function edit(NotificationChannel $notificationChannel): Response
    {
        Gate::authorize('update', $notificationChannel);

        return Inertia::render('NotificationChannels/Edit', [
            'channel' => [
                'id'        => $notificationChannel->id,
                'type'      => $notificationChannel->type,
                'label'     => $notificationChannel->label,
                'is_active' => $notificationChannel->is_active,
                'config'    => $notificationChannel->config,
            ],
        ]);
    }

    public function update(UpdateNotificationChannelRequest $request, NotificationChannel $notificationChannel): RedirectResponse
    {
        Gate::authorize('update', $notificationChannel);

        $data = $request->validated();

        $notificationChannel->update([
            'label'     => $data['label'],
            'is_active' => $data['is_active'] ?? $notificationChannel->is_active,
            'config'    => $this->normalizeConfig($notificationChannel->type, $data['config']),
        ]);

        return redirect()->route('notification-channels.index')
            ->with('message', 'Canale aggiornato con successo.');
    }

    public function destroy(NotificationChannel $notificationChannel): RedirectResponse
    {
        Gate::authorize('delete', $notificationChannel);

        $notificationChannel->delete();

        return redirect()->route('notification-channels.index')
            ->with('message', 'Canale eliminato.');
    }

    public function test(NotificationChannel $notificationChannel): JsonResponse
    {
        Gate::authorize('update', $notificationChannel);

        $result = $this->dispatcher->dispatchTest($notificationChannel);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // -------------------------------------------------------------------------

    private function configSummary(NotificationChannel $channel): string
    {
        return match ($channel->type) {
            'webhook' => $channel->config['url'] ?? '–',
            'slack'   => $channel->config['webhook_url'] ?? '–',
            'email'   => $channel->config['address'] ?? '–',
            default   => '–',
        };
    }

    private function normalizeConfig(string $type, array $config): array
    {
        return match ($type) {
            'webhook' => [
                'url'             => $config['url'],
                'secret'          => $config['secret'] ?? null,
                'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 10),
            ],
            'slack' => [
                'webhook_url'     => $config['webhook_url'],
                'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 10),
            ],
            'email' => [
                'address' => $config['address'],
            ],
            default => $config,
        };
    }
}
