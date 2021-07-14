<?php

namespace Dashifen\MediaByAspectRatio;

use Exception;
use Dashifen\Repository\RepositoryException;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Traits\OptionsManagementTrait;
use Dashifen\WPHandler\Traits\PostMetaManagementTrait;
use Dashifen\MediaByAspectRatio\Agents\CommandLineAgent;
use Dashifen\MediaByAspectRatio\Repositories\AspectRatio;
use Dashifen\WPHandler\Handlers\Plugins\AbstractPluginHandler;

class MediaByAspectRatio extends AbstractPluginHandler
{
  use PostMetaManagementTrait;
  use OptionsManagementTrait;
  
  public const SLUG = 'media-by-aspect-ratio';
  
  /**
   * initialize
   *
   * Uses addAction and/or addFilter to attach protected methods of this object
   * to the ecosystem of WordPress action and filter hooks.
   *
   * @return void
   * @throws HandlerException
   */
  public function initialize(): void
  {
    if (!$this->isInitialized()) {
      $this->registerActivationHook('activation');
      $this->addAction('init', 'initializeAgents', 1);
    }
  }
  
  /**
   * activation
   *
   * This method is fired when our plugin is activated to load up three
   * common aspect ratios.
   *
   * @return void
   * @throws HandlerException
   * @throws RepositoryException
   * @throws TransformerException
   */
  protected function activation(): void
  {
    if (empty($this->getOption('aspect-ratios', []))) {
      $square = new AspectRatio(1, 1);        // uhm ... square
      $full = new AspectRatio(4, 3);          // older monitors, TVs
      $wide1 = new AspectRatio(16, 9);        // HD video and wide screens
      $wide2 = new AspectRatio(16, 10);       // some tablets and wide screens
      
      $this->updateOption('aspect-ratios', [
        (string) $square->ratio => $square,
        (string) $full->ratio   => $full,
        (string) $wide1->ratio  => $wide1,
        (string) $wide2->ratio  => $wide2,
      ]);
    }
  }
  
  /**
   * initializeAgents
   *
   * We override our parent's method so that we can limit which agents are
   * initialized rather than always doing them all at once.
   *
   * @return void
   * @throws Exception
   */
  protected function initializeAgents(): void
  {
    foreach ($this->agentCollection as $agent) {
      
      // we always want to initialize the CommandLineAgent.  this is because
      // the CLI runs with no capabilities, so if we limited all agents to only
      // the uses with permission to upload files, we'd never have access to
      // our commands.  otherwise, only if the current user can upload do we
      // call an agent's initialize method.
      
      if ($agent instanceof CommandLineAgent || current_user_can('upload_files')) {
        $agent->initialize();
      }
    }
  }
  
  /**
   * getOptionNames
   *
   * Inherited from the OptionsManagementTrait, this method returns an array of
   * this plugins options which is used to make sure that we only alter the
   * information for this plugin and not other ones.
   *
   * @return array
   */
  protected function getOptionNames(): array
  {
    return ['aspect-ratios'];
  }
  
  /**
   * getOptionNamePrefix
   *
   * Inherited from the OptionsManagementTrait, this method returns a unique
   * string that is used as a prefix for this plugin's options in the database.
   * This happens automatically; we can avoid writing this prefix in this and
   * other plugin files and the trait will handle making sure that it's used
   * on the way into and out of the database.
   *
   * @return string
   */
  public function getOptionNamePrefix(): string
  {
    return self::SLUG . '-';
  }
  
  /**
   * getPostMetaNames
   *
   * Returns an array of valid post meta names for use within the
   * isPostMetaValid method.
   *
   * @return array
   */
  protected function getPostMetaNames(): array
  {
    return ['aspect-ratio'];
  }
  
  /**
   * getPostMetaNamePrefix
   *
   * Inherited from our PostMetaManagementTrait, this method returns a unique
   * string that is used as a prefix for a post's meta data managed by this
   * plugin.
   *
   * @return string
   */
  public function getPostMetaNamePrefix(): string
  {
    return $this->getOptionNamePrefix();
  }
}
