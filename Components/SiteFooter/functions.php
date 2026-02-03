<?php

namespace Flynt\Components\SiteFooter;

use Flynt\Utils\Options;

Options::addGlobal('SiteFooter', [
    [
        'label' => __('Logo', 'flynt'),
        'name' => 'logo_image',
        'type' => 'image',
        'return_format' => 'array',
        'preview_size' => 'thumbnail',
    ],
    [
        'label' => __('Tagline', 'flynt'),
        'name' => 'tagline',
        'type' => 'text',
    ],
    [
        'label' => __('Description', 'flynt'),
        'name' => 'description',
        'type' => 'textarea',
        'rows' => 2,
    ],
    [
        'label' => __('Social Links', 'flynt'),
        'name' => 'social_links',
        'type' => 'repeater',
        'layout' => 'table',
        'sub_fields' => [
            [
                'label' => __('Platform', 'flynt'),
                'name' => 'platform',
                'type' => 'text',
            ],
            [
                'label' => __('URL', 'flynt'),
                'name' => 'url',
                'type' => 'url',
            ],
        ],
    ],
    [
        'label' => __('Footer Nav Groups', 'flynt'),
        'name' => 'nav_groups',
        'type' => 'repeater',
        'layout' => 'block',
        'sub_fields' => [
            [
                'label' => __('Heading', 'flynt'),
                'name' => 'heading',
                'type' => 'text',
            ],
            [
                'label' => __('Links', 'flynt'),
                'name' => 'links',
                'type' => 'repeater',
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'label' => __('Label', 'flynt'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('URL', 'flynt'),
                        'name' => 'url',
                        'type' => 'url',
                    ],
                ],
            ],
            [
                'label' => __('Contact Address (HTML)', 'flynt'),
                'instructions' => __('Use for Contact column instead of links.', 'flynt'),
                'name' => 'contact_address',
                'type' => 'wysiwyg',
            ],
        ],
    ],
    [
        'label' => __('Footer Badges', 'flynt'),
        'name' => 'footer_badges',
        'type' => 'repeater',
        'layout' => 'table',
        'sub_fields' => [
            [
                'label' => __('Text', 'flynt'),
                'name' => 'text',
                'type' => 'text',
            ],
        ],
    ],
    [
        'label' => __('Copyright Text', 'flynt'),
        'name' => 'copyright',
        'type' => 'text',
    ],
    [
        'label' => __('Legal Links', 'flynt'),
        'name' => 'legal_links',
        'type' => 'repeater',
        'layout' => 'table',
        'sub_fields' => [
            [
                'label' => __('Label', 'flynt'),
                'name' => 'label',
                'type' => 'text',
            ],
            [
                'label' => __('URL', 'flynt'),
                'name' => 'url',
                'type' => 'url',
            ],
        ],
    ],
], 'Footer');
