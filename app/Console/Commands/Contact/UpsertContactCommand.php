<?php

declare(strict_types=1);

namespace App\Console\Commands\Contact;

use Domain\Contact\Actions\UpsertContact;
use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class UpsertContactCommand extends Command
{
    /** @var string */
    protected $signature = 'contact:upsert
        {--id= : Update an existing contact by id}
        {--name= : The contact name}
        {--phone=* : E164 phone numbers (repeatable)}
        {--email=* : Email addresses (repeatable)}';

    /** @var string */
    protected $description = 'Create or update a contact via the shared UpsertContact action.';

    public function handle(UpsertContact $action): int
    {
        $idValue = $this->option('id');
        $id = is_numeric($idValue) ? (int) $idValue : null;

        $data = new ContactData(
            name: (string) $this->option('name'),
            phones: $this->toValueObjects('phone', static fn (string $value): PhoneNumber => new PhoneNumber($value)),
            emails: $this->toValueObjects('email', static fn (string $value): EmailAddress => new EmailAddress($value)),
        );

        $contact = $action->execute($data, $id);

        $this->line(sprintf('Contact upserted: id=%d', $contact->id));

        return self::SUCCESS;
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $factory
     * @return list<T>
     */
    private function toValueObjects(string $option, callable $factory): array
    {
        /** @var array<int, mixed> $values */
        $values = (array) $this->option($option);

        return array_values(Collection::make($values)
            ->map(static fn (mixed $value): string => is_string($value) ? $value : '')
            ->map($factory)
            ->all());
    }
}
