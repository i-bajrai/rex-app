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

            $this->replacePhones($contact, $data->phones);
            $this->replaceEmails($contact, $data->emails);

            return $contact->fresh(['phones', 'emails']) ?? $contact;
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
    private function replacePhones(Contact $contact, array $phones): void
    {
        $contact->phones()->delete();

        Collection::make($phones)->each(function (PhoneNumber $phone) use ($contact): void {
            $contact->phones()->create(['e164' => (string) $phone]);
        });
    }

    /**
     * @param  list<EmailAddress>  $emails
     */
    private function replaceEmails(Contact $contact, array $emails): void
    {
        $contact->emails()->delete();

        Collection::make($emails)->each(function (EmailAddress $email) use ($contact): void {
            $contact->emails()->create(['address' => (string) $email]);
        });
    }
}
