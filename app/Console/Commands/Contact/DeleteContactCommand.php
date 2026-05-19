<?php

declare(strict_types=1);

namespace App\Console\Commands\Contact;

use Domain\Contact\Actions\DeleteContact;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Illuminate\Console\Command;

final class DeleteContactCommand extends Command
{
    /** @var string */
    protected $signature = 'contact:delete {id : The contact id}';

    /** @var string */
    protected $description = 'Delete a contact via the shared DeleteContact action.';

    public function handle(DeleteContact $action): int
    {
        $id = (int) $this->argument('id');

        try {
            $action->execute($id);
        } catch (ContactNotFoundException $contactNotFoundException) {
            $this->error($contactNotFoundException->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('Contact %d deleted.', $id));

        return self::SUCCESS;
    }
}
