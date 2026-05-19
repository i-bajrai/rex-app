<?php

declare(strict_types=1);

use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Enums\CallStatus;
use Domain\Contact\Gateways\FakeTelephonyGateway;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;

test('returns one of the five CallStatus values', function (): void {
    $gateway = new FakeTelephonyGateway(seed: 1);

    $outcome = $gateway->call(new PhoneNumber('+61412345678'));

    expect($outcome)->toBeInstanceOf(CallOutcome::class)
        ->and($outcome->status)->toBeInstanceOf(CallStatus::class);
});

test('produces the same outcome for the same seed and number', function (): void {
    $first = new FakeTelephonyGateway(seed: 42)->call(new PhoneNumber('+61412345678'));
    $second = new FakeTelephonyGateway(seed: 42)->call(new PhoneNumber('+61412345678'));

    expect($first->status)->toBe($second->status)
        ->and($first->durationSeconds)->toBe($second->durationSeconds)
        ->and($first->providerMessage)->toBe($second->providerMessage);
});

test('connected outcomes carry a positive integer duration', function (): void {
    $gateway = new FakeTelephonyGateway(seed: 7);

    $outcome = $gateway->call(new PhoneNumber('+61412345678'));

    if ($outcome->status === CallStatus::Connected) {
        expect($outcome->durationSeconds)->toBeInt()
            ->and($outcome->durationSeconds)->toBeGreaterThan(0);
    }
});

test('any seed that produces connected has a duration; otherwise duration is null', function (): void {
    $foundConnected = false;
    $foundNonConnected = false;

    Collection::range(1, 50)->each(function (int $seed) use (&$foundConnected, &$foundNonConnected): void {
        $outcome = new FakeTelephonyGateway(seed: $seed)->call(new PhoneNumber('+61412345678'));

        if ($outcome->status === CallStatus::Connected) {
            $foundConnected = true;
            expect($outcome->durationSeconds)->toBeInt();
        } else {
            $foundNonConnected = true;
            expect($outcome->durationSeconds)->toBeNull();
        }
    });

    expect($foundConnected)->toBeTrue()
        ->and($foundNonConnected)->toBeTrue();
});
