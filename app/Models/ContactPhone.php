<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PhoneNumberCast;
use Carbon\CarbonInterface;
use Database\Factories\ContactPhoneFactory;
use Domain\Contact\ValueObjects\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $contact_id
 * @property-read PhoneNumber $e164
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Contact $contact
 */
final class ContactPhone extends Model
{
    /** @use HasFactory<ContactPhoneFactory> */
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
            'e164' => PhoneNumberCast::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
