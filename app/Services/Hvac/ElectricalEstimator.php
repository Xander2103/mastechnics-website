<?php

namespace App\Services\Hvac;

/**
 * Generic electrical estimate per capacity class. Manufacturer data on the
 * selected product overrides these values. Always an ESTIMATE: manual
 * verification by the installer/electrician is required.
 */
class ElectricalEstimator
{
    public function forClass(?float $classKw, array $rules): array
    {
        $defaults = $rules['electrical_by_class'] ?? [];
        $key = $classKw !== null ? number_format($classKw, 1, '.', '') : null;
        $match = $key !== null ? ($defaults[$key] ?? null) : null;

        return [
            'class_kw'  => $classKw,
            'breaker_a' => $match['breaker_a'] ?? null,
            'cable'     => $match['cable'] ?? null,
            'voltage'   => $rules['electrical_default_voltage'] ?? '230V mono',
            'source'    => 'generic_rule',
            'warning'   => 'Aparte voedingskring aanbevolen. Dit is een schatting — elektrische controle door de installateur is vereist.',
        ];
    }

    public function fromProduct(array $productData, array $rules): array
    {
        return [
            'class_kw'  => $productData['cooling_capacity_kw'] ?? null,
            'breaker_a' => $productData['required_breaker_a'],
            'cable'     => $productData['required_cable'],
            'voltage'   => $productData['supported_voltage'] ?? ($rules['electrical_default_voltage'] ?? '230V mono'),
            'source'    => 'manufacturer_data',
            'warning'   => 'Fabrikantgegevens. Elektrische controle door de installateur blijft vereist.',
        ];
    }
}
