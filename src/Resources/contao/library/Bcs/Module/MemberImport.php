<?php

namespace Bcs\MemberImport\Module;

use Contao\BackendModule;
use Contao\Template;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        $this->Template->headline = 'BCS Member Import';
        $this->Template->message  = 'This is a placeholder. CSV import logic will be added here.';
    }
}
