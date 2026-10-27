<?php

return [
    // Where people write to us: the footer, the FAQ and the structured data name it.
    'contact' => env('HOOLI_CONTACT', 'hello@gethooli.app'),

    // The site's languages, by the prefix of their URLs: English has none (/), French is /fr, Estonian /et. Each
    // language names itself, and gives its own word for the guides' address.
    'locales' => [
        'en' => ['name' => 'English', 'guides' => 'guides', 'og' => 'en_GB'],
        'fr' => ['name' => 'Français', 'guides' => 'guides', 'og' => 'fr_FR'],
        'et' => ['name' => 'Eesti', 'guides' => 'juhendid', 'og' => 'et_EE'],
    ],

    // The guides, in the order the site lists them. Each is written once per language, in
    // resources/guides/{locale}/{key}.md.
    'guides' => ['endocrine-disruptors', 'progestins-meningioma', 'cosmetic-ingredients'],

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
