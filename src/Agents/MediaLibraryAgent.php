<?php

namespace Dashifen\MediaByAspectRatio\Agents;

use Dashifen\WPHandler\Agents\AbstractPluginAgent;

class MediaLibraryAgent extends AbstractPluginAgent
{
  /**
   * initialize
   *
   * Uses addAction and/or addFilter to attach protected methods of this object
   * to the ecosystem of WordPress action and filter hooks.
   *
   * @return void
   */
  public function initialize(): void
  {
    if (!$this->isInitialized()) {
    
    }
  }
}
