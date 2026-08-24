<?php

/**
 * Datos del centro medico que emite los examenes. Se imprimen en el PDF y en la
 * leyenda oficial de verificacion.
 */
return [
    'name' => env('MEDICAL_CENTER_NAME', 'Centro Médico Ocupacional S.A.S.'),
    'nit' => env('MEDICAL_CENTER_NIT', '900.123.456-7'),
    'license' => env('MEDICAL_CENTER_LICENSE', 'Licencia SST No. 0001-2026'),
    'address' => env('MEDICAL_CENTER_ADDRESS', 'Calle 100 # 15-20, Bogotá D.C.'),
    'phone' => env('MEDICAL_CENTER_PHONE', '+57 601 000 0000'),
    'email' => env('MEDICAL_CENTER_EMAIL', 'contacto@centromedico.test'),
    'physician' => [
        'name' => env('MEDICAL_CENTER_PHYSICIAN', 'Dra. Laura Gómez Restrepo'),
        'license' => env('MEDICAL_CENTER_PHYSICIAN_LICENSE', 'R.M. 12345 - Lic. SO 4567'),
    ],
];
