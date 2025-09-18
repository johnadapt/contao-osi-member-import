<?php

// Register the backend module under System instead of accounts
$GLOBALS['BE_MOD']['system']['bcs_member_import'] = [
    'callback' => \BrightCloudStudio\MemberImport\MemberImport::class,
    'icon'     => 'system/themes/flexible/icons/mgroup.svg',
];