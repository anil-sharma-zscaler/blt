<?php

namespace Acquia\Blt\Robo;

use Acquia\Blt\Robo\Common\IO;
use Acquia\Blt\Robo\Config\ConfigAwareTrait;
use Acquia\Blt\Robo\LocalEnvironment\LocalEnvironmentAwareInterface;
use Acquia\Blt\Robo\LocalEnvironment\LocalEnvironmentAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Tasks;

/**
 *
 */
class BltTasks extends Tasks implements ConfigAwareInterface, LocalEnvironmentAwareInterface, LoggerAwareInterface {

  use ConfigAwareTrait;
  use IO;
  use LocalEnvironmentAwareTrait;
  use LoggerAwareTrait;

}
