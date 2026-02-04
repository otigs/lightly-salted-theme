<?php

namespace Flynt\Components\ContactSection;

use Flynt\Utils\Options;

use function get_field;

Options::addGlobal('ContactSection', [
    [
        'label' => __('Contact Section Defaults', 'flynt'),
        'name'  => 'labels',
        'type'  => 'group',
        'sub_fields' => [
            [
                'label'         => __('Default Eyebrow', 'flynt'),
                'name'          => 'eyebrow',
                'type'          => 'text',
                'default_value' => 'Get in touch',
            ],
        ],
    ],
]);

add_filter('Flynt/addComponentData?name=ContactSection', function (array $data): array {
    if (function_exists('get_field')) {
        $data['contact_details'] = [
            'contact_email' => get_field('contact_email', 'option'),
            'sales_email'   => get_field('sales_email', 'option'),
            'support_email' => get_field('support_email', 'option'),
            'phone_number'  => get_field('phone_number', 'option'),
        ];
        $data['eyebrow'] = get_field('labels_eyebrow', 'option') ?: __('Get in touch', 'flynt');
    } else {
        $data['contact_details'] = [
            'contact_email' => null,
            'sales_email'   => null,
            'support_email' => null,
            'phone_number'  => null,
        ];
        $data['eyebrow'] = __('Get in touch', 'flynt');
    }

    return $data;
});

