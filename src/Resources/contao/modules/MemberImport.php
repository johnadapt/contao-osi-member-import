<?php

namespace Bcs\MemberImport\Module;

use Contao\BackendModule;

class MemberImport extends BackendModule
{
    protected $strTemplate = 'be_member_import';

    protected function compile(): void
    {
        $this->Template->content = 'Member Import module placeholder.';
    }
}
