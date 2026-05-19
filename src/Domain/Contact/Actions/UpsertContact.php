<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\DataTransferObjects\ContactData;
use Domain\Contact\Exceptions\ContactNoIdentifiersException;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Domain\Contact\Exceptions\DuplicateContactEmailException;
use Domain\Contact\Exceptions\DuplicateContactPhoneException;
use Domain\Contact\ValueObjects\EmailAddress;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UpsertContact
{
    public function execute(ContactData $data, ?int $id = null): Contact
    {
        $this->guardHasIdentifiers($data);
        $this->guardNoDuplicates($data, $id);

        return DB::transaction(function () use ($data, $id): Contact {
            $contact = $this->resolveContact($id);

            $contact->fill($data->toModelAttributes())->save();

            $this->syncPhones($contact, $data->phones);
            $this->syncEmails($contact, $data->emails);

            return $contact->load(['phones', 'emails']);
        });
    }

    private function guardHasIdentifiers(ContactData $data): void
    {
        throw_if($data->phones === [] && $data->emails === [], ContactNoIdentifiersException::class);
    }

    private function guardNoDuplicates(ContactData $data, ?int $id): void
    {
        Collection::make($data->phones)->each(function (PhoneNumber $phone) use ($id): void {
            $query = ContactPhone::query()->where('e164', (string) $phone);

            if ($id !== null) {
                $query->where('contact_id', '!=', $id);
            }

            throw_if($query->exists(), DuplicateContactPhoneException::class, (string) $phone);
        });

        Collection::make($data->emails)->each(function (EmailAddress $email) use ($id): void {
            $query = ContactEmail::query()->where('address', (string) $email);

            if ($id !== null) {
                $query->where('contact_id', '!=', $id);
            }

            throw_if($query->exists(), DuplicateContactEmailException::class, (string) $email);
        });
    }

    private function resolveContact(?int $id): Contact
    {
        if ($id === null) {
            return new Contact;
        }

        $contact = Contact::query()->find($id);

        throw_if($contact === null, ContactNotFoundException::class, $id);

        return $contact;
    }

    /**
     * @param  list<PhoneNumber>  $phones
     */
    private function syncPhones(Contact $contact, array $phones): void
    {
        $incoming = Collection::make($phones)->map(static fn (PhoneNumber $phone): string => (string) $phone);
        $existing = $contact->phones()->get();
        $existingValues = $existing->map(static fn (ContactPhone $phone): string => (string) $phone->e164);

        $idsToDelete = $existing
            ->reject(static fn (ContactPhone $phone): bool => $incoming->contains((string) $phone->e164))
            ->pluck('id');

        if ($idsToDelete->isNotEmpty()) {
            $contact->phones()->whereKey($idsToDelete)->delete();
        }

        $incoming
            ->reject(static fn (string $value): bool => $existingValues->contains($value))
            ->each(static fn (string $value) => $contact->phones()->create(['e164' => $value]));
    }

    /**
     * @param  list<EmailAddress>  $emails
     */
    private function syncEmails(Contact $contact, array $emails): void
    {
        $incoming = Collection::make($emails)->map(static fn (EmailAddress $email): string => (string) $email);
        $existing = $contact->emails()->get();
        $existingValues = $existing->map(static fn (ContactEmail $email): string => (string) $email->address);

        $idsToDelete = $existing
            ->reject(static fn (ContactEmail $email): bool => $incoming->contains((string) $email->address))
            ->pluck('id');

        if ($idsToDelete->isNotEmpty()) {
            $contact->emails()->whereKey($idsToDelete)->delete();
        }

        $incoming
            ->reject(static fn (string $value): bool => $existingValues->contains($value))
            ->each(static fn (string $value) => $contact->emails()->create(['address' => $value]));
    }
}
