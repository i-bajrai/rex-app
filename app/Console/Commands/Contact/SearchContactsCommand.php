<?php

declare(strict_types=1);

namespace App\Console\Commands\Contact;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\Actions\GetContact;
use Domain\Contact\Actions\SearchContacts;
use Domain\Contact\DataTransferObjects\ContactSearchCriteria;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Console\Command;

final class SearchContactsCommand extends Command
{
    /** @var string */
    protected $signature = 'contact:search
        {--name= : Name prefix (case-insensitive)}
        {--phone= : Exact E164 phone number}
        {--email-domain= : Email domain (case-insensitive)}';

    /** @var string */
    protected $description = 'Search contacts via the shared SearchContacts action.';

    public function handle(SearchContacts $searchContacts, GetContact $getContact): int
    {
        $phoneValue = $this->option('phone');

        $criteria = new ContactSearchCriteria(
            name: $this->stringOrNull('name'),
            phone: is_string($phoneValue) && $phoneValue !== '' ? new PhoneNumber($phoneValue) : null,
            emailDomain: $this->stringOrNull('email-domain'),
        );

        $results = $searchContacts->execute($criteria)
            ->map(static fn (Contact $contact): Contact => $getContact->execute($contact->id))
            ->map(static fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phones' => $contact->phones->map(static fn (ContactPhone $phone): string => (string) $phone->e164)->values()->all(),
                'emails' => $contact->emails->map(static fn (ContactEmail $email): string => (string) $email->address)->values()->all(),
            ])
            ->values()
            ->all();

        $this->line((string) json_encode($results, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
