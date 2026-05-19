<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\DataTransferObjects\CallOutcome;
use Domain\Contact\Exceptions\ContactHasNoPhoneException;
use Domain\Contact\Exceptions\ContactNotFoundException;

final readonly class PlaceCallToContact
{
    public function __construct(private TelephonyGateway $gateway) {}

    public function execute(int $id): CallOutcome
    {
        $contact = Contact::query()->with('primaryPhone')->find($id);

        throw_if($contact === null, ContactNotFoundException::class, $id);

        $phone = $contact->primaryPhone;

        throw_if($phone === null, ContactHasNoPhoneException::class, $id);

        return $this->gateway->call($phone->e164);
    }
}
