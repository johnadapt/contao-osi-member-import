<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{bcs_member_import_legend},bcs_import_email_from,bcs_import_email_fromName,bcs_import_email_replyTo,bcs_import_email_subject,bcs_import_email_body,bcs_import_resetPage,bcs_import_debug';


$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_email_from'] = [
    'label'     => ['From Email', 'Override the from email address'],
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_email_fromName'] = [
    'label'     => ['From Name', 'Override the from name'],
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_email_replyTo'] = [
    'label'     => ['Reply-To Email', 'Override the reply-to email'],
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_email_subject'] = [
    'label'     => ['Fallback Reset Subject', 'Subject line for fallback password reset emails'],
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_email_body'] = [
    'label'     => ['Fallback Reset Body', 'Body for fallback password reset emails. Tokens: ##firstname##, ##lastname##, ##username##, ##reset_link##'],
    'inputType' => 'textarea',
    'eval'      => ['rte'=>'tinyMCE', 'tl_class'=>'clr'],
    'sql'       => "mediumtext NULL",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_resetPage'] = [
    'label'     => ['Password Reset Page', 'Select the page with the lost password module'],
    'inputType' => 'pageTree',
    'eval'      => ['fieldType'=>'radio', 'tl_class'=>'w100'],
    'sql'       => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bcs_import_debug'] = [
    'label'     => ['Enable NC Debug Dump', 'Show Notification Center type dump on the Member Import page'],
    'inputType' => 'checkbox',
    'eval'      => ['tl_class'=>'clr m12'],
    'sql'       => "char(1) NOT NULL default ''",
];
