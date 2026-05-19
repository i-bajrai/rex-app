<?php

declare(strict_types=1);

use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;

test('telephony gateway binding varies outcomes across separate container resolutions', function (): void {
    $statuses = Collection::range(1, 20)
        ->map(function (): CallStatus {
            $this->app->forgetInstance(TelephonyGateway::class);

            return app(TelephonyGateway::class)->call(new PhoneNumber('+61412345678'))->status;
        })
        ->unique()
        ->values();

    expect($statuses->count())->toBeGreaterThan(1);
});
