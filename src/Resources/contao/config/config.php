<?php

$projectDir = \Contao\System::getContainer()->getParameter('kernel.project_dir');
require_once $projectDir . '/system/modules/bcs_member_import/classes/MemberImport.php';

$GLOBALS['BE_MOD']['accounts']['bcs_member_import'] = [
    'callback' => \\BrightCloudStudio\\MemberImport\\MemberImport::class,
    'icon'     => 'system/themes/flexible/icons/mgroup.svg',
];
