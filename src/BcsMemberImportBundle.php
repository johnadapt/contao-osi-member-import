<?php

namespace Brightcloud\BcsMemberImport;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BcsMemberImportBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}