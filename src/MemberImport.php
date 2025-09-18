<?php

namespace BrightCloudStudio\MemberImport;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\Config;
use Contao\System;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Csrf\CsrfToken;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        $this->Template->message = Message::generate();
        $this->Template->report = null;
        $this->Template->emailSubject = Config::get('bcs_member_import_email_subject') ?: 'Reset your account password';
        $this->Template->emailBody = Config::get('bcs_member_import_email_body') ?: "Hi {{firstname}},\n\nPlease set your password using this link:\n\n{{reset_link}}\n\nThis link will expire soon.\n\nBest regards,\nYour Team";

        if (Input::post('bcs_import') !== null) {
            if (!$this->isValidCsrf((string) Input::post('_token'))) {
                Message::addError('Invalid request token.');
                $this->Template->message = Message::generate();
                return;
            }
            $this->Template->report = $this->handleUploadAndImport();
            $this->Template->message = Message::generate();
        }

        if (Input::post('bcs_send_resets')) {
            if (!$this->isValidCsrf((string) Input::post('_token'))) {
                Message::addError('Invalid request token.');
                $this->Template->message = Message::generate();
                return;
            }

            $ids     = (array) Input::post('member_ids');
            $subject = (string) Input::post('email_subject');
            $body    = (string) Input::post('email_body');
            $saveDef = (bool)   Input::post('save_defaults');

            if ($saveDef) {
                Config::persist('bcs_member_import_email_subject', $subject);
                Config::persist('bcs_member_import_email_body', $body);
            }

            $this->sendPasswordResets($ids, $subject, $body);
            $this->Template->message = Message::generate();
        }
    }

    private function isValidCsrf(string $submitted): bool
    {
        $mgr = System::getContainer()->get('contao.csrf.token_manager');
        return $mgr->isTokenValid(new CsrfToken('contao_backend', $submitted));
    }

    protected function handleUploadAndImport(): array
    {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            Message::addError('Please choose a CSV file.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[],'newlyCreated'=>[]];
        }

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) {
            Message::addError('Unable to read CSV file.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[],'newlyCreated'=>[]];
        }

        $headers = fgetcsv($fh);
        if (!$headers) {
            fclose($fh);
            Message::addError('CSV missing header row.');
            return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[],'newlyCreated'=>[]];
        }

        // Clean headers and create index
        $headers = array_map('trim', $headers);
        $index = array_flip($headers);
        
        // Check required columns
        $required = ['firstname','lastname','email','username','member_group'];
        foreach ($required as $col) {
            if (!isset($index[$col])) {
                fclose($fh);
                Message::addError('Missing required header: '.$col);
                return ['total'=>0,'created'=>0,'groupsCreated'=>0,'failures'=>[],'newlyCreated'=>[]];
            }
        }

        $total = 0;
        $created = 0;
        $groupsCreated = 0;
        $failures = [];
        $newlyCreated = [];
        $groupCache = [];
        $rowNum = 1; // Header row

        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;
            $total++;

            // Map row data to headers
            $data = [];
            foreach ($headers as $i => $name) {
                $data[$name] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            try {
                $email = $data['email'] ?? '';
                $username = $data['username'] ?? '';
                $firstname = $data['firstname'] ?? '';
                $lastname = $data['lastname'] ?? '';
                $memberGroup = $data['member_group'] ?? '';
                $password = $data['password'] ?? '';

                // Validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email address.');
                }
                if (empty($username)) {
                    throw new \RuntimeException('Username is required.');
                }
                if (empty($firstname)) {
                    throw new \RuntimeException('First name is required.');
                }
                if (empty($lastname)) {
                    throw new \RuntimeException('Last name is required.');
                }
                if (empty($memberGroup)) {
                    throw new \RuntimeException('Member group is required.');
                }

                // Check if member already exists (by email or username)
                $existingByEmail = MemberModel::findOneBy('email', $email);
                $existingByUsername = MemberModel::findOneBy('username', $username);
                
                if ($existingByEmail || $existingByUsername) {
                    throw new \RuntimeException('Member already exists with this email or username.');
                }

                // Handle member group creation/retrieval
                $groupId = $groupCache[$memberGroup] ?? null;
                if (!$groupId) {
                    $existingGroup = MemberGroupModel::findOneBy('name', $memberGroup);
                    if ($existingGroup) {
                        $groupId = $existingGroup->id;
                    } else {
                        // Create new group
                        $newGroup = new MemberGroupModel();
                        $newGroup->tstamp = time();
                        $newGroup->name = $memberGroup;
                        $newGroup->save();
                        $groupId = $newGroup->id;
                        $groupsCreated++;
                    }
                    $groupCache[$memberGroup] = $groupId;
                }

                // Create new member
                $member = new MemberModel();
                $member->tstamp = time();
                $member->dateAdded = time();
                $member->firstname = $firstname;
                $member->lastname = $lastname;
                $member->email = $email;
                $member->username = $username;
                
                // Handle password - if empty, generate random one
                if (empty($password)) {
                    $password = bin2hex(random_bytes(8)); // Generate 16 character random password
                }
                $member->password = password_hash($password, PASSWORD_DEFAULT);
                
                $member->login = 1;
                $member->disable = '';
                $member->groups = StringUtil::serialize([$groupId]);

                // Add other optional fields if they exist in CSV
                $optionalFields = ['company', 'street', 'postal', 'city', 'state', 'country', 'phone', 'mobile', 'fax', 'website', 'language', 'gender', 'dateOfBirth'];
                foreach ($optionalFields as $field) {
                    if (isset($data[$field]) && !empty($data[$field])) {
                        // Handle special date field
                        if ($field === 'dateOfBirth' && !empty($data[$field])) {
                            $dateValue = strtotime($data[$field]);
                            if ($dateValue !== false) {
                                $member->$field = $dateValue;
                            }
                        } else {
                            $member->$field = $data[$field];
                        }
                    }
                }

                $member->save();
                $created++;
                
                $newlyCreated[] = [
                    'id' => $member->id,
                    'firstname' => $member->firstname,
                    'lastname' => $member->lastname,
                    'email' => $member->email,
                    'username' => $member->username,
                    'member_group' => $memberGroup
                ];

            } catch (\Throwable $e) {
                $failures[] = [
                    'row' => $rowNum,
                    'email' => $data['email'] ?? 'N/A',
                    'username' => $data['username'] ?? 'N/A',
                    'reason' => $e->getMessage()
                ];
            }
        }
        
        fclose($fh);

        if ($created > 0) {
            Message::addConfirmation("Successfully processed $total rows. Created $created members and $groupsCreated new groups.");
        }
        
        if (!empty($failures)) {
            Message::addError(count($failures) . " rows failed to import. See details below.");
        }

        return [
            'total' => $total,
            'created' => $created,
            'groupsCreated' => $groupsCreated,
            'failures' => $failures,
            'newlyCreated' => $newlyCreated
        ];
    }

    protected function sendPasswordResets(array $memberIds, string $subject, string $body): void
    {
        if (empty($memberIds)) {
            Message::addError('No members selected for password reset.');
            return;
        }

        $memberIds = array_map('intval', $memberIds);
        $mailer = System::getContainer()->get('mailer');
        $siteUrl = rtrim(Environment::get('url'), '/');
        $resetPage = '/reset-password.html'; // This should match your frontend page

        $sent = 0;
        $failed = 0;

        foreach ($memberIds as $id) {
            $member = MemberModel::findByPk($id);
            if (!$member || !$member->email) {
                $failed++;
                continue;
            }

            // Generate reset token
            $token = bin2hex(random_bytes(16));
            $member->activation = $token;
            $member->save();

            // Create reset link
            $resetLink = $siteUrl . $resetPage . '?token=' . $token;

            // Replace placeholders in email body
            $renderedBody = strtr($body, [
                '{{firstname}}' => $member->firstname,
                '{{lastname}}' => $member->lastname,
                '{{email}}' => $member->email,
                '{{username}}' => $member->username,
                '{{reset_link}}' => $resetLink
            ]);

            try {
                $email = (new Email())
                    ->to($member->email)
                    ->subject($subject)
                    ->text($renderedBody);
                
                $mailer->send($email);
                $sent++;
            } catch (\Throwable $e) {
                Message::addError('Failed to send email to ' . $member->email . ': ' . $e->getMessage());
                $failed++;
            }
        }

        if ($sent > 0) {
            Message::addConfirmation("Successfully sent $sent password reset email(s).");
        }
        if ($failed > 0) {
            Message::addError("$failed email(s) failed to send.");
        }
    }
}