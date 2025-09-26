<?php

$GLOBALS['BE_MOD']['accounts']['bcs_member_import'] = [
    'tables'   => [],
    'callback' => Bcs\MemberImport\Module\MemberImport::class,
    'icon'     => 'system/themes/flexible/icons/members.svg',
];

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['member_import'] = [
    'member_import_password_reset' => [
        'recipients'    => ['email'],
        'email_subject' => ['subject'],
        'email_text'    => ['text'],
        'email_html'    => ['html'],
    ],
];
