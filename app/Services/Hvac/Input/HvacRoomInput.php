<?php

namespace App\Services\Hvac\Input;

/**
 * Immutable, normalized description of one room.
 *
 * "assumed" flags mark values that did NOT come from the customer but from
 * rule-set installation assumptions — the admin UI must show these as
 * assumptions, and the calculation carries matching warnings.
 */
final class HvacRoomInput
{
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly string $type,
        public readonly float $widthM,
        public readonly float $lengthM,
        public readonly float $heightM,
        public readonly float $areaM2,
        public readonly string $insulation,
        public readonly ?string $insulationOther,
        public readonly string $orientation,
        public readonly ?string $orientationOther,
        public readonly string $roofType,
        public readonly ?string $roofTypeOther,
        public readonly string $windowType,
        public readonly ?string $windowTypeOther,
        public readonly ?float $windowAreaM2,
        public readonly int $occupants,
        public readonly bool $occupantsAssumed,
        public readonly array $equipment,
        public readonly bool $equipmentAssumed,
        public readonly float $pipeLengthM,
        public readonly int $pipeBends,
        public readonly float $pipeRiseM,
        public readonly bool $pipeAssumed,
        public readonly string $drainage, // available / none / unknown
    ) {
    }

    public function toArray(): array
    {
        return [
            'index'             => $this->index,
            'name'              => $this->name,
            'type'              => $this->type,
            'width_m'           => $this->widthM,
            'length_m'          => $this->lengthM,
            'height_m'          => $this->heightM,
            'area_m2'           => $this->areaM2,
            'insulation'        => $this->insulation,
            'insulation_other'  => $this->insulationOther,
            'orientation'       => $this->orientation,
            'orientation_other' => $this->orientationOther,
            'roof_type'         => $this->roofType,
            'roof_type_other'   => $this->roofTypeOther,
            'window_type'       => $this->windowType,
            'window_type_other' => $this->windowTypeOther,
            'window_area_m2'    => $this->windowAreaM2,
            'occupants'         => $this->occupants,
            'occupants_assumed' => $this->occupantsAssumed,
            'equipment'         => $this->equipment,
            'equipment_assumed' => $this->equipmentAssumed,
            'pipe_length_m'     => $this->pipeLengthM,
            'pipe_bends'        => $this->pipeBends,
            'pipe_rise_m'       => $this->pipeRiseM,
            'pipe_assumed'      => $this->pipeAssumed,
            'drainage'          => $this->drainage,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            index: (int) $data['index'],
            name: (string) $data['name'],
            type: (string) $data['type'],
            widthM: (float) $data['width_m'],
            lengthM: (float) $data['length_m'],
            heightM: (float) $data['height_m'],
            areaM2: (float) $data['area_m2'],
            insulation: (string) $data['insulation'],
            insulationOther: $data['insulation_other'] ?? null,
            orientation: (string) $data['orientation'],
            orientationOther: $data['orientation_other'] ?? null,
            roofType: (string) $data['roof_type'],
            roofTypeOther: $data['roof_type_other'] ?? null,
            windowType: (string) $data['window_type'],
            windowTypeOther: $data['window_type_other'] ?? null,
            windowAreaM2: isset($data['window_area_m2']) ? (float) $data['window_area_m2'] : null,
            occupants: (int) $data['occupants'],
            occupantsAssumed: (bool) $data['occupants_assumed'],
            equipment: (array) $data['equipment'],
            equipmentAssumed: (bool) $data['equipment_assumed'],
            pipeLengthM: (float) $data['pipe_length_m'],
            pipeBends: (int) $data['pipe_bends'],
            pipeRiseM: (float) $data['pipe_rise_m'],
            pipeAssumed: (bool) $data['pipe_assumed'],
            drainage: (string) $data['drainage'],
        );
    }
}
