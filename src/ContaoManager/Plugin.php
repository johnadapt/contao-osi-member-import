<?php

namespace Bcs\MemberImport\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Bcs\MemberImport\BcsMemberImportBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(BcsMemberImportBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }
}
