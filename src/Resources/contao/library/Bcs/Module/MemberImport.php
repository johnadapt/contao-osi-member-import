<?php

namespace Bcs\MemberImport\Module;

use Contao\BackendModule;
use Contao\Database;
use Contao\Input;
use Contao\Message;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        $this->Template->headline = 'BCS Member Import';

        // Handle file upload
        if (Input::post('FORM_SUBMIT') === 'tl_member_import') {
            $this->processCsv();
        }
    }

    private function processCsv(): void
    {
        $file = $_FILES['csv_file']['tmp_name'] ?? null;

        if (!$file || !is_uploaded_file($file)) {
            Message::addError('No CSV file uploaded.');
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            Message::addError('Failed to open uploaded CSV file.');
            return;
        }

        $db = Database::getInstance();

        $header = fgetcsv($handle);
        if (!$header) {
            Message::addError('CSV file is empty or invalid.');
            return;
        }

        $imported = [];
        $skipped = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['email'])) {
                $skipped[] = ['row' => $data, 'reason' => 'Missing email'];
                continue;
            }

            // Check if member exists
            $existing = $db->prepare("SELECT id FROM tl_member WHERE email=?")
                           ->execute($data['email']);
            if ($existing->numRows > 0) {
                $skipped[] = ['row' => $data, 'reason' => 'Duplicate email'];
                continue;
            }

            // Handle member group
            $groupId = null;
            if (!empty($data['group'])) {
                $group = $db->prepare("SELECT id FROM tl_member_group WHERE name=?")
                            ->execute($data['group']);
                if ($group->numRows > 0) {
                    $groupId = $group->id;
                } else {
                    $db->prepare("INSERT INTO tl_member_group (tstamp, name) VALUES (?, ?)")
                       ->execute(time(), $data['group']);
                    $groupId = $db->insertId;
                }
            }

            // Insert new member
            $db->prepare("
                INSERT INTO tl_member
                (tstamp, firstname, lastname, email, dateOfBirth, street, postal, city, country, username, password, groups)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute(
                time(),
                $data['firstname'] ?? '',
                $data['lastname'] ?? '',
                $data['email'],
                $data['dateOfBirth'] ?? '',
                $data['street'] ?? '',
                $data['postal'] ?? '',
                $data['city'] ?? '',
                $data['country'] ?? '',
                $data['username'] ?? '',
                $data['password'] ?? '',
                $groupId ? serialize([$groupId]) : ''
            );

            $imported[] = $data['email'];
        }

        fclose($handle);

        // Show results
        if (!empty($imported)) {
            Message::addConfirmation('Imported members: ' . implode(', ', $imported));
        }
        if (!empty($skipped)) {
            foreach ($skipped as $s) {
                Message::addError('Skipped: ' . ($s['row']['email'] ?? '[no email]') . ' (' . $s['reason'] . ')');
            }
        }
    }
}
