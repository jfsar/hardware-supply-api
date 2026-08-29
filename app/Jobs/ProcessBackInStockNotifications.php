<?php

namespace App\Jobs;

use App\Enums\AlertSubscriptionStatus;
use App\Models\BackInStockSubscription;
use App\Models\ProductVariant;
use App\Notifications\Engagement\BackInStock;
use App\Services\Notifications\NotificationPreferenceGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessBackInStockNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int  $productVariantId  the restocked variant's id
     */
    public function __construct(public readonly int $productVariantId) {}

    /**
     * Fan out exactly-once to every waiting subscriber (FR-NOTIF-002): the
     * row is locked and marked Notified before any mail is queued, and the
     * preference gate skips opted-out customers without burning the alert.
     */
    public function handle(NotificationPreferenceGate $gate): void
    {
        $variant = ProductVariant::withTrashed()->find($this->productVariantId);

        if ($variant === null) {
            return;
        }

        DB::transaction(function () use ($variant, $gate): void {
            $subscriptions = BackInStockSubscription::query()
                ->where('product_variant_id', $variant->getKey())
                ->where('status', AlertSubscriptionStatus::Active->value)
                ->whereNull('notified_at')
                ->lockForUpdate()
                ->get();

            /** @var ProductVariant|null $fresh Variant used for names only. */
            $context = $this->displayContext($variant);

            foreach ($subscriptions as $subscription) {
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
     * Reload the variant with its product category for stable names.
     *
     * @return array{product: ?string, variant: ?string}
     */
    private function displayContext(ProductVariant $variant): array
    {
        return [
            'product' => $variant->product()->withTrashed()->value('name'),
            'variant' => $variant->name,
        ];
    }

    /**
     * Whether the subscriber's account has opted out of this category.
     */
    private function optedOut(BackInStockSubscription $subscription, NotificationPreferenceGate $gate): bool
    {
        return $subscription->user_id !== null
            && $subscription->user !== null
            && ! $gate->allows($subscription->user, 'back_in_stock');
    }

    /**
     * Queue the restock mail to the subscribed email.
     */
    private function send(BackInStockSubscription $subscription, array $context): void
    {
        Notification::route('mail', (string) $subscription->email)->notify(new BackInStock(
            (int) $subscription->product_variant_id,
            $context['product'],
            $context['variant'],
        ));
    }
}
