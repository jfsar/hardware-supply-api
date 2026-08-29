<?php

namespace App\Jobs;

use App\Enums\AlertSubscriptionStatus;
use App\Models\PriceDropSubscription;
use App\Models\ProductVariant;
use App\Notifications\Engagement\PriceDrop;
use App\Services\Notifications\NotificationPreferenceGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessPriceDropNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int  $productVariantId  the repriced variant's id
     */
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $previousMinor,
        public readonly int $newMinor,
        public readonly string $currencyCode,
    ) {}

    /**
     * Notify active price-drop subscribers exactly once when the recorded
     * drop crosses their threshold (or any drop when no target is set).
     */
    public function handle(NotificationPreferenceGate $gate): void
    {
        $variant = ProductVariant::withTrashed()->find($this->productVariantId);

        if ($variant === null) {
            return;
        }

        DB::transaction(function () use ($variant, $gate): void {
            $subscriptions = PriceDropSubscription::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('status', AlertSubscriptionStatus::Active->value)
                ->whereNull('notified_at')
                ->lockForUpdate()
                ->get();

            $context = [
                'product' => $variant->product()->withTrashed()->value('name'),
                'variant' => $variant->name,
            ];

            foreach ($subscriptions as $subscription) {
                if (! $this->crossesThreshold($subscription)) {
                    continue;
                }

                if ($this->optedOut($subscription, $gate)) {
                    continue;
                }

                $subscription->status = AlertSubscriptionStatus::Notified;
                $subscription->notified_at = now();
                $subscription->save();

                $this->send($subscription, $context);
            }
        });
    }

    /**
     * A drop triggers always-below-the-last-price subscribers and anyone
     * whose target price has now been met or beaten (FR-PRICE-005).
     */
    private function crossesThreshold(PriceDropSubscription $subscription): bool
    {
        if ($subscription->target_price_minor === null) {
            return $this->newMinor < $this->previousMinor;
        }

        return $this->newMinor <= (int) $subscription->target_price_minor;
    }

    /**
     * Whether the subscriber's account has opted out of this category.
     */
    private function optedOut(PriceDropSubscription $subscription, NotificationPreferenceGate $gate): bool
    {
        return $subscription->user_id !== null
            && $subscription->user !== null
            && ! $gate->allows($subscription->user, 'price_drop');
    }

    /**
     * Queue the price-drop mail to the subscribed email.
     */
    private function send(PriceDropSubscription $subscription, array $context): void
    {
        Notification::route('mail', (string) $subscription->email)->notify(new PriceDrop(
            (int) $subscription->product_variant_id,
            $context['product'],
            $context['variant'],
            $this->previousMinor,
            $this->newMinor,
            $this->currencyCode,
        ));
    }
}
