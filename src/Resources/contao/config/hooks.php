<?php

use Contao\Database;
use NotificationCenter\Model\Notification;
use NotificationCenter\Model\Message;

$GLOBALS['TL_HOOKS']['loadDataContainer'][] = static function ($table) {
    if ($table !== 'tl_nc_notification') {
        return;
    }

    $objNotification = Notification::findOneByType('member_import_password_reset');
    if (null === $objNotification) {
        $notification = new Notification();
        $notification->title = 'Default Member Import Password Reset';
        $notification->type = 'member_import_password_reset';
        $notification->tstamp = time();
        $notification->save();

        $db = Database::getInstance();
        $gateway = $db->prepare("SELECT id FROM tl_nc_gateway WHERE type=? ORDER BY id ASC")
                      ->limit(1)
                      ->execute('email');
        $gatewayId = $gateway->numRows > 0 ? $gateway->id : 0;

        $message = new Message();
        $message->pid = $notification->id;
        $message->title = 'Default Reset Message';
        $message->gateway = $gatewayId;
        $message->language = 'en';
        $message->fallback = 1;
        $message->recipients = '##email##';
        $message->subject = 'Reset your password';
        $message->text = "Hello ##firstname## ##lastname##,\n\nReset your account password using this link:\n##reset_link##\n\nThank you.";
        $message->html = "<p>Hello ##firstname## ##lastname##,</p><p>Reset your account password using this link:</p><p><a href=\\\"##reset_link##\\\">Reset Password</a></p><p>Thank you.</p>";
        $message->save();
    }
};
