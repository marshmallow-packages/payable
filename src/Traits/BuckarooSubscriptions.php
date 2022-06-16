<?php

namespace Marshmallow\Payable\Traits;

use Carbon\Carbon;
use Marshmallow\Payable\Models\Providers\Buckaroo\BuckarooSubscription;

trait BuckarooSubscriptions
{
    public function getBuckarooSubscriptionLocale(): string
    {
        return 'nl-NL';
    }

    public function buckarooSubscriptions()
    {
        $subscription_model = config('payable.models.buckaroo_subscription', BuckarooSubscription::class);
        return $this->morphMany($subscription_model, 'subscribable');
    }

    abstract public function getBuckarooSubscriptionStartDate(): Carbon;
    abstract public function getBuckarooSubscriptionRatePlanCode(): string;
    abstract public function getBuckarooSubscriptionConfigurationCode(): string;
    abstract public function getBuckarooSubscriptionDebtorCode(): string;
    abstract public function getFirstName(): string;
    abstract public function getLastName(): string;
    abstract public function getEmailAddress(): string;
}
