<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use Domain\Contact\Exceptions\ContactNotFoundException;

final class GetContact
{
    public function execute(int $id): Contact
    {
        $contact = Contact::query()->with(['phones', 'emails'])->find($id);

        throw_if($contact === null, ContactNotFoundException::class, $id);

        return $contact;
    }
}
