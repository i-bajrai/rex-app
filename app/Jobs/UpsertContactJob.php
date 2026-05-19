<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Contact\Actions\UpsertContact;
use Domain\Contact\DataTransferObjects\ContactData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
final class UpsertContactJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ContactData $data) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(UpsertContact $action): void
    {
        $action->execute($this->data);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('UpsertContactJob failed', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'contact_name' => $this->data->name,
        ]);
    }
}
