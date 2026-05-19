<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Contact\Actions\UpsertContact;
use Domain\Contact\DataTransferObjects\ContactData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class UpsertContactJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ContactData $data) {}

    public function handle(UpsertContact $action): void
    {
        $action->execute($this->data);
    }
}
