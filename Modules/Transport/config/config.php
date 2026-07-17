<?php

return [
    'name' => 'Transport',
    'transaction_attempts' => 5,
    'queue' => 'transport',
    'catalog_20_version' => 'SUNAT-2026-06-01',
    'reasons' => [
        '01' => 'Venta',
        '02' => 'Compra',
        '03' => 'Venta con entrega a terceros',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '05' => 'Consignacion',
        '06' => 'Devolucion',
        '07' => 'Recojo de bienes transformados',
        '08' => 'Importacion',
        '09' => 'Exportacion',
        '13' => 'Otros',
        '14' => 'Venta sujeta a confirmacion del comprador',
        '17' => 'Traslado de bienes para transformacion',
        '18' => 'Traslado por emisor itinerante',
        '19' => 'Traslado de mercancia extranjera',
    ],
];
