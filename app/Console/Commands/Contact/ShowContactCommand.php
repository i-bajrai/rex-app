<?php

declare(strict_types=1);

namespace App\Console\Commands\Contact;

use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\Actions\GetContact;
use Illuminate\Console\Command;

final class ShowContactCommand extends Command
{
    /** @var string */
    protected $signature = 'contact:show {id : The contact id}';

    /** @var string */
    protected $description = 'Show a contact via the shared GetContact action.';

    public function handle(GetContact $action): int
    {
        $contact = $action->execute((int) $this->argument('id'));

        $payload = [
            'id' => $contact->id,
            'name' => $contact->name,
            'phones' => $contact->phones->map(static fn (ContactPhone $phone): string => (string) $phone->e164)->values()->all(),
            'emails' => $contact->emails->map(static fn (ContactEmail $email): string => (string) $email->address)->values()->all(),
        ];

        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
