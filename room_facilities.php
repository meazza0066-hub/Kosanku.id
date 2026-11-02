<?php
function room_facility_options()
{
    return [
        'ac' => ['label' => 'AC', 'icon' => 'fas fa-wind'],
        'fan' => ['label' => 'Kipas angin', 'icon' => 'fas fa-fan'],
        'wifi' => ['label' => 'Wi-Fi', 'icon' => 'fas fa-wifi'],
        'bathroom' => ['label' => 'Kamar mandi', 'icon' => 'fas fa-shower'],
    ];
}

function room_facility_keys($storedFacilities)
{
    $facilityKeys = is_array($storedFacilities)
        ? $storedFacilities
        : explode(',', (string) $storedFacilities);

    return array_values(array_intersect(
        array_keys(room_facility_options()),
        array_unique(array_map('trim', $facilityKeys))
    ));
}

function room_default_facilities_for_price($price)
{
    $coolingFacility = (float) $price >= 500000 ? 'ac' : 'fan';

    return [$coolingFacility, 'wifi', 'bathroom'];
}
