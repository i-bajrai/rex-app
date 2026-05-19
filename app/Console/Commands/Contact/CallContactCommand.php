<?php

declare(strict_types=1);

namespace App\Console\Commands\Contact;

use Domain\Contact\Actions\PlaceCallToContact;
use Illuminate\Console\Command;

final class CallContactCommand extends Command
{
    /** @var string */
    protected $signature = 'contact:call {id : The contact id}';

    /** @var string */
    protected $description = 'Place a call to a contact via the shared PlaceCallToContact action.';

    public function handle(PlaceCallToContact $action): int
    {
        $outcome = $action->execute((int) $this->argument('id'));

        $payload = [
            'status' => $outcome->status->value,
            'duration_seconds' => $outcome->durationSeconds,
            'provider_message' => $outcome->providerMessage,
        ];

        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
