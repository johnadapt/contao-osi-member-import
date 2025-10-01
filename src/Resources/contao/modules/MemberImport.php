<?php

namespace Bcs\MemberImport\Module;

use Contao\BackendModule;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Contao\File;
use Contao\Database;
use Contao\Config;
use Contao\Email;
use Contao\PageModel;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Contao\CoreBundle\OptIn\OptIn;
use Contao\MemberModel;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        // Always render Contao messages inline for this module
        $this->Template->messages = Message::generate();
        $this->Template->content  = '';

        /** @var CsrfTokenManagerInterface $tokenManager */
        $tokenManager = System::getContainer()->get('contao.csrf.token_manager');
        $tokenValue   = $tokenManager->getDefaultTokenValue();

        $db = Database::getInstance();

        // // Helper: render upload form (reusable)
        // $renderUploadForm = function () use ($tokenValue) {
        //     return '<form action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getUri() . '" method="post" enctype="multipart/form-data">
        //         <div class="tl_formbody_edit">
        //             <input type="hidden" name="FORM_SUBMIT" value="tl_member_import">
        //             <input type="hidden" name="REQUEST_TOKEN" value="' . $tokenValue . '">
        //             <input type="file" name="csv_file" accept=".csv" style="margin-inline: 15px;">
        //         </div>
        //         <div class="tl_formbody_submit" style="border-top: 0; margin-top: 30px; margin-inline: 15px;">
        //             <button type="submit" class="tl_submit">Upload CSV/Preview Import</button>
        //         </div>
        //     </form>';
        // };
        // Helper: render upload form (reusable)
$renderUploadForm = function () use ($tokenValue) {
    return '<h3 style="margin:15px 0 10px;font-size:18px;">Upload CSV File</h3>
    <p>Upload a CSV file with the members that you with to import</p>
    <form action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getUri() . '" method="post" enctype="multipart/form-data" style="margin:20px 0;padding:15px;">
        <div class="tl_formbody_edit" style="margin-bottom:20px;">
            <input type="hidden" name="FORM_SUBMIT" value="tl_member_import">
            <input type="hidden" name="REQUEST_TOKEN" value="' . $tokenValue . '">
            <label for="csv_file" style="display:block;margin-bottom:8px;">Select CSV File</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv" style="display:block;padding:6px;border:1px solid #ccc;border-radius:3px;width:100%;max-width:400px;background:#fff;">
        </div>
        <div class="tl_formbody_submit" style="margin-top:15px; border-top:0;">
            <button type="submit" class="tl_submit" style="padding:8px 16px;background:#2a77d4;color:#fff;border:1px solid #1e5aa8;border-radius:3px;cursor:pointer;font-size:14px;">Upload CSV / Preview Import</button>
        </div>
    </form>';
};


        // Step 1: Upload CSV
        if (Input::post('FORM_SUBMIT') === 'tl_member_import') {
            // Message::addInfo('CSV Uploaded. Please review before importing.');
            $file = $_FILES['csv_file'] ?? null;

            if ($file && $file['tmp_name']) {
                $csv = new File($file['tmp_name']);
                $rows = [];
                if (($handle = fopen($csv->path, 'r')) !== false) {
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }

                if ($rows) {
                    $headers  = array_map('trim', $rows[0] ?? []);
                    $dataRows = array_slice($rows, 1);
                    $required = ['email', 'groups'];
                    $missing  = array_diff($required, $headers);

                    if ($missing) {
                        Message::addError('Missing required columns: ' . implode(', ', $missing) . '. Please fix your CSV file and try again.');
                        $this->Template->content .= $renderUploadForm();
                        $this->Template->messages = Message::generate();
                        return;
                    }

                    $members = [];
                    foreach ($dataRows as $row) {
                        if (count($row) === count($headers)) {
                            $assoc     = array_combine($headers, $row);
                            $members[] = $assoc;
                        }
                    }
                    $_SESSION['bcs_member_import']         = $members;
                    $_SESSION['bcs_member_import_headers'] = $headers;

                    // $html = '<h3>CSV Preview</h3><table class="tl_listing"><thead><tr>';
                    // foreach ($headers as $head) {
                    //     $html .= '<th>' . htmlspecialchars($head) . '</th>';
                    // }
                    // $html .= '</tr></thead><tbody>';
                    // foreach ($members as $member) {
                    //     $html .= '<tr>';
                    //     foreach ($headers as $head) {
                    //         $html .= '<td>' . htmlspecialchars($member[$head] ?? '') . '</td>';
                    //     }
                    //     $html .= '</tr>';
                    // }
                    // $html .= '</tbody></table>';
                    // $html .= '<form action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getUri() . '" method="post">
                    //     <input type="hidden" name="FORM_SUBMIT" value="tl_member_import_execute">
                    //     <input type="hidden" name="REQUEST_TOKEN" value="' . $tokenValue . '">
                    //     <div class="tl_formbody_submit">
                    //         <button type="submit" class="tl_submit">Import Now</button>
                    //     </div></form>';
                    // $this->Template->content .= $html;
                    
                    $html  = '<h3 style="margin:15px 0 10px;font-size:18px;">CSV Preview</h3>';
$html .= '<table class="tl_listing" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:14px;border:1px solid #ddd;margin-bottom:20px;">';
$html .= '<thead><tr style="background-color:#f5f5f5;text-align:left;">';

foreach ($headers as $head) {
    $html .= '<th style="padding:8px 10px;border:1px solid #ddd;">' . htmlspecialchars($head) . '</th>';
}

$html .= '</tr></thead><tbody>';

$i = 0;
foreach ($members as $member) {
    $rowBg = ($i++ % 2 === 0) ? '#fff' : '#fafafa';
    $html .= '<tr style="background-color:' . $rowBg . ';transition:background 0.2s;">';
    foreach ($headers as $head) {
        $html .= '<td style="padding:8px 10px;border:1px solid #ddd;">' . htmlspecialchars($member[$head] ?? '') . '</td>';
    }
    $html .= '</tr>';
}

$html .= '</tbody></table>';

$html .= '<form action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getUri() . '" method="post" style="margin-top:15px;">'
       . '<input type="hidden" name="FORM_SUBMIT" value="tl_member_import_execute">'
       . '<input type="hidden" name="REQUEST_TOKEN" value="' . $tokenValue . '">'
       . '<div class="tl_formbody_submit" style="border-top: 0;">'
       . '<button type="submit" class="tl_submit" style="padding:6px 15px;background:#2a77d4;color:#fff;border:1px solid #1e5aa8;border-radius:3px;cursor:pointer;font-size:14px;">Import Now</button>'
       . '</div></form>';

$this->Template->content .= $html;

                    
                } else {
                    Message::addError('The uploaded CSV file appears to be empty.');
                    $this->Template->content .= $renderUploadForm();
                }
            } else {
                Message::addError('Please upload a valid CSV file.');
                $this->Template->content .= $renderUploadForm();
            }

            $this->Template->messages = Message::generate();
        }

        // Step 2: Execute Import
        elseif (Input::post('FORM_SUBMIT') === 'tl_member_import_execute' && !empty($_SESSION['bcs_member_import'])) {
            // Message::addInfo('Import Executed');
            $members = $_SESSION['bcs_member_import'];
            unset($_SESSION['bcs_member_import'], $_SESSION['bcs_member_import_headers']);
            $imported = [];
            $skipped  = [];
            $failed   = [];

            // PASS 1: Create all groups
            $allGroupNames = [];
            foreach ($members as $row) {
                $rowGroups = array_map('trim', explode(',', $row['groups'] ?? ''));
                foreach ($rowGroups as $g) {
                    if ($g !== '') {
                        $allGroupNames[] = $g;
                    }
                }
            }
            $groupNames = array_unique($allGroupNames);
            $groupIds   = [];

            foreach ($groupNames as $gName) {
                $objGroup = $db->prepare("SELECT id FROM tl_member_group WHERE name=?")->execute($gName);
                if ($objGroup->numRows < 1) {
                    $db->prepare("INSERT INTO tl_member_group (tstamp, name) VALUES (?, ?)")
                        ->execute(time(), $gName);
                    $groupIds[$gName] = (int) $db->insertId;
                    // Message::addInfo("Created new group: $gName (ID: {$groupIds[$gName]})");
                } else {
                    $groupIds[$gName] = (int) $objGroup->id;
                    // Message::addInfo("Found existing group: $gName (ID: {$groupIds[$gName]})");
                }
            }

            // PASS 2: Create members
            foreach ($members as $row) {
                $email = $row['email'] ?? '';
                if (empty($email)) {
                    $failed[] = ['firstname' => $row['firstname'] ?? '', 'lastname' => $row['lastname'] ?? '', 'email' => '(no email)', 'reason' => 'missing required field email'];
                    continue;
                }

                $exists = $db->prepare("SELECT id FROM tl_member WHERE email=?")->execute($email);
                if ($exists->numRows > 0) {
                    $skipped[] = ['firstname' => $row['firstname'] ?? '', 'lastname' => $row['lastname'] ?? '', 'email' => $email, 'reason' => 'already exists'];
                    continue;
                }

                $rowGroups      = array_map('trim', explode(',', $row['groups'] ?? ''));
                $memberGroupIds = [];
                
                foreach ($rowGroups as $gName) {
                    if ($gName === '') {
                        continue;
                    }
                
                    // ✅ Explicitly re-fetch group ID from DB to ensure it exists
                    $objGroup = $db->prepare("SELECT id FROM tl_member_group WHERE name=?")->execute($gName);
                    if ($objGroup->numRows > 0) {
                        $memberGroupIds[] = (int) $objGroup->id;
                    }
                }
                
                // If no valid groups found, skip this member
                if (empty($memberGroupIds)) {
                    $failed[] = [
                        'firstname' => $row['firstname'] ?? '',
                        'lastname'  => $row['lastname'] ?? '',
                        'email'     => $email,
                        'reason'    => 'no valid groups found'
                    ];
                    continue;
                }
 
                 static $memberFields = null;
                if ($memberFields === null) {
                    $memberFields = $db->getFieldNames('tl_member');
                }
                
                // ✅ Assign serialized group IDs to member

                $insert = [
                    'tstamp'    => time(),
                    'dateAdded' => time(),
                    'email'     => $email,
                    'username'  => !empty($row['username']) ? $row['username'] : $email,
                    'login'     => 1,
                    'disable'   => 0,
                    'start'     => '',
                    'stop'      => '',
                    'groups'    => serialize($memberGroupIds),
                ];

                // Loop over CSV columns to add additional tl_member fields
                foreach ($row as $col => $val) {
                    // ✅ Skip "groups" column so it does NOT overwrite the serialized group IDs
                    if ($col === 'groups') {
                        continue;
                    }
                
                    if (in_array($col, $memberFields, true) && !isset($insert[$col])) {
                        $insert[$col] = $val;
                    }
                }





                $db->prepare("INSERT INTO tl_member %s")->set($insert)->execute();

                $memberId = $db->insertId;
                if (!$memberId) {
                    $memberId = $db->prepare("SELECT id FROM tl_member WHERE email=? ORDER BY id DESC")
                                   ->limit(1)->execute($email)->id;
                }

                $imported[] = [
                    'id'        => $memberId,
                    'firstname' => $row['firstname'] ?? '',
                    'lastname'  => $row['lastname'] ?? '',
                    'email'     => $email,
                    'status'    => 'Imported'
                ];
                // Message::addInfo("Imported member: $email (ID: $memberId) with groups: " . implode(',', $memberGroupIds));
            }

            // Results summary
            $this->Template->content .= '<h3 style="margin-bottom: 10px;">Import Results</h3>';
            $this->Template->content .= '<p style="margin-bottom: 30px;padding-left:20px">Imported: ' . count($imported) . ', Skipped: ' . count($skipped) . ', Failed: ' . count($failed) . '</p>';

            // // Render results tables
            // $renderTable = function ($rows, $title, $color) {
            //     if (!$rows) return '';
            //     $html = "<h4>$title</h4><table class=\"tl_listing\"><thead><tr>
            //         <th>Firstname</th><th>Lastname</th><th>Email</th><th>Status</th>
            //     </tr></thead><tbody>";
            //     foreach ($rows as $r) {
            //         $status = $r['status'] ?? ($r['reason'] ?? '');
            //         $html .= '<tr>';
            //         $html .= '<td>' . htmlspecialchars($r['firstname'] ?? '') . '</td>';
            //         $html .= '<td>' . htmlspecialchars($r['lastname'] ?? '') . '</td>';
            //         $html .= '<td>' . htmlspecialchars($r['email'] ?? '') . '</td>';
            //         $html .= '<td style="color:' . $color . ';">' . htmlspecialchars($status) . '</td>';
            //         $html .= '</tr>';
            //     }
            //     $html .= '</tbody></table>';
            //     return $html;
            // };
// Render results tables
$renderTable = function (array $rows, string $title, string $color): string {
    if (!$rows) {
        return '';
    }

    $html  = '<h4 style="margin:15px 0 5px;font-size:16px;">' . htmlspecialchars($title) . '</h4>';
    $html .= '<table class="tl_listing" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:14px;border:1px solid #ddd;margin-bottom:20px;">';
    $html .= '<thead>
                <tr style="background-color:#f5f5f5;text-align:left;">
                    <th style="padding:8px 10px;border:1px solid #ddd;">Firstname</th>
                    <th style="padding:8px 10px;border:1px solid #ddd;">Lastname</th>
                    <th style="padding:8px 10px;border:1px solid #ddd;">Email</th>
                    <th style="padding:8px 10px;border:1px solid #ddd;">Status</th>
                </tr>
              </thead>
              <tbody>';

    $i = 0;
    foreach ($rows as $r) {
        $status = $r['status'] ?? ($r['reason'] ?? '');
        $rowBg  = ($i++ % 2 === 0) ? '#fff' : '#fafafa';

        $html .= '<tr style="background-color:' . $rowBg . ';transition:background 0.2s;">';
        $html .= '<td style="padding:8px 10px;border:1px solid #ddd;">' . htmlspecialchars($r['firstname'] ?? '') . '</td>';
        $html .= '<td style="padding:8px 10px;border:1px solid #ddd;">' . htmlspecialchars($r['lastname'] ?? '') . '</td>';
        $html .= '<td style="padding:8px 10px;border:1px solid #ddd;">' . htmlspecialchars($r['email'] ?? '') . '</td>';
        $html .= '<td style="padding:8px 10px;border:1px solid #ddd;color:' . htmlspecialchars($color) . ';font-weight:bold;">' . htmlspecialchars($status) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
};


            $this->Template->content .= $renderTable($imported, 'Imported Members', 'green');
            $this->Template->content .= $renderTable($skipped, 'Skipped Members', 'orange');
            $this->Template->content .= $renderTable($failed, 'Failed Members', 'red');

            // Reset email form
            if ($imported) {
                $this->Template->content .= '
                    <h3 style="margin-block: 30px 10px;">Send Password Reset Emails</h3>
                    <form action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getUri() . '" method="post" style="padding-left: 20px;">
                        <input type="hidden" name="FORM_SUBMIT" value="tl_member_import_reset">
                        <input type="hidden" name="REQUEST_TOKEN" value="' . $tokenValue . '">
                        <label><input type="checkbox" id="select_all"> <strong>Select All</strong></label><br><br>
                ';
                foreach ($imported as $mem) {
                    $this->Template->content .= '<label><input type="checkbox" name="members[]" value="' . $mem['id'] . '"> ' . $mem['email'] . '</label><br>';
                }
                $this->Template->content .= '
                        <br><div class="tl_formbody_submit" style="border-top:0;margin-top:15px;">
                            <button type="submit" class="tl_submit" style="padding:8px 16px;background:#2a77d4;color:#fff;border:1px solid #1e5aa8;border-radius:3px;cursor:pointer;font-size:14px;">Send Password Reset Emails</button>
                        </div>
                    </form>
                    <script>
                        document.getElementById("select_all").addEventListener("change", function(e) {
                            var checkboxes = document.querySelectorAll("input[name=\'members[]\']");
                            for (var i = 0; i < checkboxes.length; i++) {
                                checkboxes[i].checked = e.target.checked;
                            }
                        });
                    </script>';
            }
        }

        // Step 3: Send Reset Emails
        elseif (Input::post('FORM_SUBMIT') === 'tl_member_import_reset') {
            Message::addInfo('Password Reset Email(s) Sent');
            $ids = Input::post('members');
            $results = [];
            $successCount = 0;
            $failCount = 0;

            if (!empty($ids) && is_array($ids)) {
                $resetPage = Config::get('bcs_import_resetPage');

                foreach ($ids as $id) {
                    $memberModel = MemberModel::findByPk($id);

                    if ($memberModel !== null && $memberModel->email) {
                        $email = (string) $memberModel->email;

                        try {
                            $optIn = System::getContainer()->get('contao.opt_in');
                            $token = $optIn->create('pw', $email, ['tl_member' => [$id]]);
                            $tokenValue = $token->getIdentifier();

                            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $base   = $scheme . '://' . $host;

                            if ($resetPage && ($page = PageModel::findByPk($resetPage)) !== null) {
                                $resetLink = $base . '/' . $page->alias . '?token=' . $tokenValue;
                            } else {
                                $resetLink = $base . '/contao/password?token=' . $tokenValue;
                            }

                            $subject = Config::get('bcs_import_email_subject') ?: 'Reset your password';
                            $body    = Config::get('bcs_import_email_body') ?: "Hello ##firstname##,<br><br>Please reset your password:<br><a href=\"##reset_link##\">Reset</a>";

                            $replacements = [
                                '##firstname##'  => $memberModel->firstname ?? '',
                                '##lastname##'   => $memberModel->lastname ?? '',
                                '##username##'   => $memberModel->username ?? '',
                                '##reset_link##' => $resetLink,
                            ];

                            $subject = strtr($subject, $replacements);
                            $body    = strtr($body, $replacements);

                            $sender   = Config::get('bcs_import_email_from') ?: (Config::get('adminEmail') ?: 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                            $fromName = Config::get('bcs_import_email_fromName') ?: ($GLOBALS['TL_ADMIN_NAME'] ?? 'Website Admin');

                            $mail = new Email();
                            $mail->from     = $sender;
                            $mail->fromName = $fromName;
                            if ($reply = Config::get('bcs_import_email_replyTo')) {
                                $mail->replyTo($reply);
                            }
                            $mail->subject = $subject;
                            $mail->html    = $body;
                            $mail->text    = strip_tags($body);

                            $mail->sendTo($email);

                            $results[] = '<p style="color:green;">✅ Password reset email sent to ' . $email . '</p>';
                            $successCount++;
                        } catch (\Throwable $e) {
                            $results[] = '<p style="color:red;">❌ Failed to send reset for ' . $email . ' – ' . $e->getMessage() . '</p>';
                            $failCount++;
                        }
                    } else {
                        $results[] = '<p style="color:red;">❌ Member not found or missing email for ID=' . $id . '</p>';
                        $failCount++;
                    }
                }
            } else {
                $results[] = '<p>No members selected.</p>';
            }

            // Summary line
            $this->Template->content .= '<h3 style="margin-block: 30px 10px;">Email Summary</h3>';
            $this->Template->content .= '<div style="padding-left:20px;margin-bottom: 45px;"><p style="color:green;">Successful: ' . $successCount . '</p>';
            $this->Template->content .= '<p style="color:red;">Failed: ' . $failCount . '</p>';
            $this->Template->content .= implode('', $results);
            $this->Template->content .= '</div><a style="padding: 8px 16px;background: #2a77d4;color: #fff;border: 1px solid #1e5aa8;border-radius: 3px;cursor: pointer;font-size: 14px;" href="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getBaseUrl() . '/contao?do=bcs_member_import">Back to Member Import</a>';

            // // Back button
            // $this->Template->content .= '<br><form method="get" action="' . System::getContainer()->get('request_stack')->getCurrentRequest()->getBaseUrl() . '/contao?do=bcs_member_import">
            //     <button type="submit" class="tl_submit">Back to Member Import</button>
            // </form>';

            // Render inline messages
            $this->Template->messages = Message::generate();
        }

        // Default view
        else {
            $this->Template->content .= $renderUploadForm();
            $this->Template->messages = Message::generate();
        }
    }
}
