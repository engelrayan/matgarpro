<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order form fields
    |--------------------------------------------------------------------------
    | The default shape of every store's order form. A merchant overrides parts
    | of this per store; anything they have not touched keeps falling back here,
    | so adding a field later switches on for everyone without a data migration.
    |
    | `locked` fields cannot be disabled or made optional. A cash-on-delivery
    | order without a name and a reachable phone is a parcel nobody can confirm
    | and nobody can deliver — that is not a preference, it is the floor.
    |
    | Defaults are deliberately sparse: every extra field on a COD form costs
    | the merchant orders, so a field has to earn its place by being switched on.
    */
    'fields' => [
        'customer_name' => [
            'label' => 'اسمك',
            'placeholder' => '',
            'enabled' => true,
            'required' => true,
            'locked' => true,
            'order' => 1,
        ],

        'customer_phone' => [
            'label' => 'رقم الموبايل',
            'placeholder' => '01xxxxxxxxx',
            'enabled' => true,
            'required' => true,
            'locked' => true,
            'order' => 2,
        ],

        'governorate' => [
            'label' => 'المحافظة',
            'placeholder' => '',
            'enabled' => true,
            'required' => false,
            'locked' => false,
            'order' => 3,
        ],

        'city' => [
            'label' => 'المنطقة',
            'placeholder' => '',
            'enabled' => false,
            'required' => false,
            'locked' => false,
            'order' => 4,
        ],

        'address' => [
            'label' => 'العنوان بالتفصيل',
            'placeholder' => 'الشارع، رقم العمارة، الدور، علامة مميزة',
            'enabled' => true,
            'required' => true,
            'locked' => false,
            'order' => 5,
        ],

        'customer_phone_alt' => [
            'label' => 'رقم تاني (اختياري)',
            'placeholder' => '',
            'enabled' => false,
            'required' => false,
            'locked' => false,
            'order' => 6,
        ],

        'customer_email' => [
            'label' => 'البريد الإلكتروني',
            'placeholder' => '',
            'enabled' => false,
            'required' => false,
            'locked' => false,
            'order' => 7,
        ],

        'note' => [
            'label' => 'ملاحظات على الطلب',
            'placeholder' => '',
            'enabled' => false,
            'required' => false,
            'locked' => false,
            'order' => 8,
        ],
    ],

];
