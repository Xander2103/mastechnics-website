<?php

namespace App\Services\Hvac\Input;

/**
 * Immutable, normalized description of one airco installation request.
 * The original source answers are preserved untouched in $source.
 */
final class HvacRequestInput
{
    /**
     * @param HvacRoomInput[] $rooms
     */
    public function __construct(
        public readonly int $customerRequestId,
        public readonly string $locale,
        public readonly string $installationType,
        public readonly string $splitType, // single_split / multi_split
        public readonly bool $splitTypeDerived,
        public readonly string $customerType,
        public readonly string $electricalSupply, // '230V mono' etc. or 'unknown'
        public readonly string $hasExistingOutdoorUnit, // yes / no / unknown
        public readonly string $outdoorUnitLocation, // unknown (not asked yet)
        public readonly ?string $houseOlderThan10y, // yes / no / unknown / null
        public readonly ?string $preferredBrand,
        public readonly ?string $budgetPreference,
        public readonly ?string $noisePreference,
        public readonly ?string $wifiPreference,
        public readonly ?string $timeframe,
        public readonly ?string $comments,
        public readonly int $photosCount,
        public readonly array $rooms,
        public readonly array $source,
    ) {
    }

    public function toArray(): array
    {
        return [
            'customer_request_id'       => $this->customerRequestId,
            'locale'                    => $this->locale,
            'installation_type'         => $this->installationType,
            'split_type'                => $this->splitType,
            'split_type_derived'        => $this->splitTypeDerived,
            'customer_type'             => $this->customerType,
            'electrical_supply'         => $this->electricalSupply,
            'has_existing_outdoor_unit' => $this->hasExistingOutdoorUnit,
            'outdoor_unit_location'     => $this->outdoorUnitLocation,
            'house_older_than_10y'      => $this->houseOlderThan10y,
            'preferred_brand'           => $this->preferredBrand,
            'budget_preference'         => $this->budgetPreference,
            'noise_preference'          => $this->noisePreference,
            'wifi_preference'           => $this->wifiPreference,
            'timeframe'                 => $this->timeframe,
            'comments'                  => $this->comments,
            'photos_count'              => $this->photosCount,
            'rooms'                     => array_map(fn (HvacRoomInput $r) => $r->toArray(), $this->rooms),
            'source'                    => $this->source,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            customerRequestId: (int) $data['customer_request_id'],
            locale: (string) $data['locale'],
            installationType: (string) $data['installation_type'],
            splitType: (string) $data['split_type'],
            splitTypeDerived: (bool) $data['split_type_derived'],
            customerType: (string) $data['customer_type'],
            electricalSupply: (string) $data['electrical_supply'],
            hasExistingOutdoorUnit: (string) $data['has_existing_outdoor_unit'],
            outdoorUnitLocation: (string) $data['outdoor_unit_location'],
            houseOlderThan10y: $data['house_older_than_10y'] ?? null,
            preferredBrand: $data['preferred_brand'] ?? null,
            budgetPreference: $data['budget_preference'] ?? null,
            noisePreference: $data['noise_preference'] ?? null,
            wifiPreference: $data['wifi_preference'] ?? null,
            timeframe: $data['timeframe'] ?? null,
            comments: $data['comments'] ?? null,
            photosCount: (int) ($data['photos_count'] ?? 0),
            rooms: array_map(fn (array $r) => HvacRoomInput::fromArray($r), $data['rooms']),
            source: (array) ($data['source'] ?? []),
        );
    }
}
