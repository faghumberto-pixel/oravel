<?php

namespace App\Services\Push;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender implements PushSender
{
    public function send(array $subscription, string $payload): ?bool
    {
        $vapid = config('services.webpush');

        if (blank($vapid['public'] ?? null) || blank($vapid['private'] ?? null)) {
            return null;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['public'],
            'privateKey' => $vapid['private'],
        ]], ['TTL' => 3600], 10);

        $report = $webPush->sendOneNotification(Subscription::create([
            'endpoint' => $subscription['endpoint'],
            'publicKey' => $subscription['p256dh'],
            'authToken' => $subscription['auth'],
        ]), $payload);

        if ($report->isSuccess()) {
            return true;
        }

        return $report->isSubscriptionExpired() ? false : null;
    }
}
