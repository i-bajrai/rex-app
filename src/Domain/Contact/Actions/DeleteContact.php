<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

final class DeleteContact
{
    public function execute(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $contact = Contact::query()->find($id);

            if ($contact === null) {
                return;
            }

            $contact->delete();
        });
    }
}
