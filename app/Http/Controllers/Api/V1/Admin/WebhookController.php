<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebhookEndpointRequest;
use App\Http\Requests\Admin\UpdateWebhookEndpointRequest;
use App\Http\Resources\Admin\WebhookDeliveryResource;
use App\Http\Resources\Admin\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Merchant-facing outbound webhook administration (Phase 8 Task 6,
 * FR-NOTIF-003/004). Managed under webhooks.manage; the HMAC secret is
 * generated here and shown exactly once.
 */
class WebhookController extends Controller
{
    /**
     * List endpoints with their subscriptions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()
                ->with('subscriptions')
                ->latest()
                ->paginate((int) ($request->query('per_page') ?? config('reports.per_page', 100)))
        );
    }

    /**
     * Create an endpoint and return its one-time HMAC secret.
     */
    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $secret = Str::random(64);

        $endpoint = WebhookEndpoint::query()->create([
            'name' => $request->string('name')->toString(),
            'url' => rtrim($request->string('url')->toString(), '/'),
            'secret_encrypted' => encrypt($secret),
            'is_active' => true,
        ]);

        $this->syncSubscriptions($endpoint, $request->input('events', []));

        $endpoint->load('subscriptions');

        return response()->json([
            'data' => (new WebhookEndpointResource($endpoint))->revealingSecret($secret),
        ], 201);
    }

    /**
     * Show an endpoint with its active subscriptions.
     */
    public function show(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->load('subscriptions');

        return response()->json([
            'data' => new WebhookEndpointResource($endpoint),
        ]);
    }

    /**
     * Update profile fields and/or the subscribed event set.
     */
    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->fill($request->safe()->only(['name', 'url', 'is_active']));

        if ($endpoint->isDirty()) {
            $endpoint->save();
        }

        if ($request->filled('events')) {
            $this->syncSubscriptions($endpoint, $request->input('events'));
        }

        $endpoint->load('subscriptions');

        return response()->json([
            'data' => new WebhookEndpointResource($endpoint),
        ]);
    }

    /**
     * Deactivate an endpoint (soft delete, FR-NOTIF-003).
     */
    public function destroy(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->delete();

        return response()->json(status: 204);
    }

    /**
     * Operational delivery history for an endpoint (FR-NOTIF-004).
     */
    public function deliveries(Request $request, WebhookEndpoint $endpoint): AnonymousResourceCollection
    {
        return WebhookDeliveryResource::collection(
            $endpoint->deliveries()
                ->latest()
                ->paginate((int) ($request->query('per_page') ?? config('reports.per_page', 100)))
        );
    }

    /**
     * Replace the endpoint's subscription set at the current api_version.
     *
     * @param  array<int, non-empty-string>  $events
     */
    protected function syncSubscriptions(WebhookEndpoint $endpoint, array $events): void
    {
        $endpoint->subscriptions()->delete();

        $apiVersion = (string) config('webhooks.api_version', '1.0');

        foreach ($events as $event) {
            $endpoint->subscriptions()->create([
                'event_type' => $event,
                'api_version' => $apiVersion,
            ]);
        }
    }
}
