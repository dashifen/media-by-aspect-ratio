<?php

namespace Dashifen\MediaByAspectRatio\Agents;

use Exception;
use Dashifen\WPHandler\Traits\CommandLineTrait;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;
use Dashifen\MediaByAspectRatio\Commands\MeasurementCommand;

class CommandLineAgent extends AbstractPluginAgent
{
  use CommandLineTrait;
  
  /**
   * initialize
   *
   * Initializes our WP CLI commands.
   *
   * @return void
   * @throws Exception
   */
  public function initialize(): void
  {
    if (!$this->isInitialized() && defined('WP_CLI') && WP_CLI) {
      
      // we don't have to register our own command namespace because we're
      // going to add our measure command to the media namespace.  that means
      // we can simply register our command as a subcommand of media and pass
      // our handler to it.
      
      $this->registerSubcommand(new MeasurementCommand('measure', 'media', $this->handler));
      $this->initializeCommands();
    }
  }
}
