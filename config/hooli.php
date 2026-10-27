<?php

return [
    // Where people write to us: the footer, the FAQ and the structured data name it. The domain isn't chosen yet.
    'contact' => env('HOOLI_CONTACT', 'hello@hooli.example'),

    // The waitlist's optional question. Keys are stored; labels are translated. The same list the app's
    // profile will offer, so the answers say which situations to build for first.
    'situations' => [
        'thyroid' => 'Thyroid issues',
        'meningioma' => 'Meningioma',
        'endometriosis' => 'Endometriosis',
        'pcos' => 'PCOS',
        'pregnancy' => 'Planning a pregnancy',
        'menopause' => 'Menopause',
        'general' => 'Taking care of my hormones in general',
        'other' => 'Something else',
    ],
];
