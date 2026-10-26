<?php

return [

    'banks' => [
        'bca' => ['label' => 'BCA', 'min' => 10, 'max' => 10],
        'bni' => ['label' => 'BNI', 'min' => 10, 'max' => 10],
        'bri' => ['label' => 'BRI', 'min' => 15, 'max' => 15],
        'mandiri' => ['label' => 'Mandiri', 'min' => 10, 'max' => 13],
        'permata' => ['label' => 'Permata', 'min' => 10, 'max' => 10],
        'cimb' => ['label' => 'CIMB Niaga', 'min' => 12, 'max' => 13],
        'bsi' => ['label' => 'BSI', 'min' => 10, 'max' => 10],
    ],


    'estimate' => env('REFUND_ESTIMATE', '3 to 5 business days'),


    'reminder_after_days' => 3,
    'reminder_max' => 3,
];