<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRequest extends Model
{
    protected $fillable = [
        'locale',
        'service_slug',
        'request_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'description',
        'brand',
        'device_model',
        'serial_number',
        'unknown_device_details',
        'metadata',
        'status',
        'source',
        'service_category',
        'urgency_level',
        'preferred_time',
        'customer_message',
        'ai_summary',
        'ai_detected_missing_fields',
    ];

    protected $casts = [
        'metadata' => 'array',
        'unknown_device_details' => 'boolean',
        'ai_detected_missing_fields' => 'array',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerRequestAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerRequestNote::class)->latest();
    }

    public function getMissingInfoChecklist(): array
    {
        $metadata = $this->metadata ?? [];
        $answers  = $metadata['answers'] ?? [];
        $missing  = [];

        // 1. No attachments
        $noAttachments = $this->relationLoaded('attachments')
            ? $this->attachments->isEmpty()
            : $this->attachments()->doesntExist();
        if ($noAttachments) {
            $missing[] = "Geen foto's of bestanden toegevoegd.";
        }

        // 2. No phone
        if (empty($this->customer_phone)) {
            $missing[] = 'Geen telefoonnummer ingevuld.';
        }

        // 3. No email
        if (empty($this->customer_email)) {
            $missing[] = 'Geen e-mailadres ingevuld.';
        }

        // 4. Incomplete location
        if (empty($answers['street']) || empty($answers['postal_code']) || empty($answers['city'])) {
            $missing[] = 'Locatiegegevens zijn onvolledig.';
        }

        // 5. No description
        if (empty($this->description) && empty($this->customer_message)) {
            $missing[] = 'Geen duidelijke beschrijving ingevuld.';
        }

        // 6. No preferred_time
        if (empty($this->preferred_time)) {
            $missing[] = 'Geen gewenst moment ingevuld.';
        }

        // 7. Brand/model missing (and not unknown)
        if ((empty($this->brand) || empty($this->device_model)) && ! $this->unknown_device_details) {
            $missing[] = 'Merk/model ontbreekt.';
        }

        // 8. Airco offerte — no rooms
        if ($this->service_category === 'airco_offerte' && empty($answers['rooms'])) {
            $missing[] = 'Geen kamerinformatie ingevuld.';
        }

        // 9. Airco offerte — no house age
        if ($this->service_category === 'airco_offerte' && empty($answers['airco_house_age'])) {
            $missing[] = 'Leeftijd woning niet ingevuld.';
        }

        // 10. Airco offerte — incomplete rooms (add once even if multiple rooms are incomplete)
        if ($this->service_category === 'airco_offerte' && is_array($answers['rooms'] ?? null) && ! empty($answers['rooms'])) {
            $roomIncomplete = false;
            foreach ($answers['rooms'] as $room) {
                if (
                    empty($room['type']) ||
                    empty($room['width']) ||
                    empty($room['length']) ||
                    empty($room['attic_or_flat_roof']) ||
                    empty($room['large_windows'])
                ) {
                    $roomIncomplete = true;
                    break;
                }
            }
            if ($roomIncomplete) {
                $missing[] = 'Kamerinformatie is onvolledig.';
            }
        }

        return $missing;
    }
}
