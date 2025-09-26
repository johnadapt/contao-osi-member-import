<?php

namespace Bcs\Module;

use Contao\BackendTemplate;
use Contao\Database;

class MemberImport
{
    /**
     * Show preview of CSV data.
     */
    public function showPreview(array $csvRows): string
    {
        $previewRows = [];

        foreach ($csvRows as $row) {
            $status = 'import';

            // Fail if missing required fields
            if (empty($row['email']) || empty($row['groups'])) {
                $status = 'fail';
            }
            // Skip if member already exists
            elseif ($this->memberExists($row['email'])) {
                $status = 'skip';
            }

            $previewRows[] = [
                'data'   => $row,
                'status' => $status,
            ];
        }

        // Load the backend template
        $template = new BackendTemplate('be_member_import_preview');
        $template->rows = $previewRows;

        return $template->parse();
    }

    /**
     * Check if a member already exists by email.
     */
    protected function memberExists(string $email): bool
    {
        $db = Database::getInstance()
            ->prepare("SELECT id FROM tl_member WHERE email=?")
            ->execute($email);

        return $db->numRows > 0;
    }
}
