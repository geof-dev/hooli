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

    // What a product is, for the app's API: its kind, and its category, within which the app offers better swaps.
    'kinds' => ['cosmetic', 'food', 'household', 'medicine', 'other'],
    'categories' => [
        'face-care', 'body-care', 'hair-care', 'sun-care', 'deodorant', 'oral-care', 'makeup', 'intimate-care',
        'baby-care', 'fragrance', 'food', 'drink', 'supplement', 'cleaning', 'laundry', 'medicine', 'other',
    ],

    // Changes to EU rules, which the app checks the user's products against. Substances go by the ids the app knows
    // them by; the words are in the app's languages.
    'updates' => [
        [
            'id' => 'vitamin-a-limits',
            'date' => '2024-04-03',
            'substances' => ['vitamin-a'],
            'title' => [
                'en' => 'Lower limits for vitamin A in cosmetics',
                'et' => 'A-vitamiinile kosmeetikas madalamad piirnormid',
                'ru' => 'Более низкие нормы витамина A в косметике',
            ],
            'body' => [
                'en' => 'The EU has set lower limits for vitamin A (retinol and its esters) in cosmetics, especially in body lotions.',
                'et' => 'EL on kehtestanud kosmeetikas A-vitamiinile (retinoolile ja selle estritele) madalamad piirnormid, eriti ihupiimades.',
                'ru' => 'ЕС установил более низкие нормы содержания витамина A (ретинола и его эфиров) в косметике, особенно в лосьонах для тела.',
            ],
            'source' => 'Commission Regulation (EU) 2024/996',
        ],
    ],
];
