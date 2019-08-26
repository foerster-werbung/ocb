<?php

namespace FoersterWerbung\Bootstrapper\October\Deployment;

use FoersterWerbung\Bootstrapper\October\Util\ManageDirectory;
use FoersterWerbung\Bootstrapper\October\Util\UsesTemplate;

/**
 * Deployment base class
 */
abstract class DeploymentBase
{
    use UsesTemplate, ManageDirectory;
}
