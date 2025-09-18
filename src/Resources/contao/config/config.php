<?php

// Register backend module under Accounts → Member Import
$GLOBALS['BE_MOD']['accounts']['bcs_member_import'] = [
    'tables' => [],
    'callback' => \\Bcs\\MemberImport\\Module\\MemberImport::class,
    'icon' => 'system/themes/flexible/icons/members.svg'
];
