<?php

namespace Bcs\MemberImport;

class NotificationTypeSetup
{
    public static function registerTypes(string $table): void
    {
        if ($table !== 'tl_nc_notification') {
            return;
        }

        $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_import_password_reset'] = [
            'recipients'    => ['email'],
            'email_subject' => ['subject'],
            'email_text'    => ['text'],
            'email_html'    => ['html'],
        ];
    }
}
