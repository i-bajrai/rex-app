<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EmailAddressCast;
use Carbon\CarbonInterface;
use Database\Factories\ContactEmailFactory;
use Domain\Contact\ValueObjects\EmailAddress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $contact_id
 * @property-read EmailAddress $address
 * @property-read string $address_domain
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Contact $contact
 */
final class ContactEmail extends Model
{
    /** @use HasFactory<ContactEmailFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'contact_id' => 'integer',
            'address' => EmailAddressCast::class,
            'address_domain' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
