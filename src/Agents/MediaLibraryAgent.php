<?php

namespace Dashifen\MediaByAspectRatio\Agents;

use WP_Query;
use Timber\Timber;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;
use Dashifen\MediaByAspectRatio\MediaByAspectRatio;
use Dashifen\MediaByAspectRatio\Repositories\AspectRatio;

/**
 * Class MediaLibraryAgent
 *
 * @property MediaByAspectRatio $handler
 *
 * @package Dashifen\MediaByAspectRatio\Agents
 */
class MediaLibraryAgent extends AbstractPluginAgent
{
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
      $this->addAction('restrict_manage_posts', 'addAspectRatioFilter');
      $this->addAction('parse_query', 'filterByAspectRatio');
    }
  }
  
  /**
   * addAspectRatioFilter
   *
   * Adds a <select> element to filter the media library by aspect ratio when
   * the library is displayed in list view.
   *
   * @param string $postType
   *
   * @return void
   * @throws HandlerException
   * @throws TransformerException
   */
  protected function addAspectRatioFilter(string $postType): void
  {
    if ($postType !== 'attachment') {
      
      // if this isn't our media library, we can leave; we don't want to do
      // anything in that case.
      
      return;
    }
    
    $ratios = $this->handler->getOption('aspect-ratios', []);
    array_walk($ratios, fn(AspectRatio &$ratio) => $ratio = $ratio->name);
    if (sizeof($ratios) === 0) {
      
      // if we don't have any ratios in the database, then we can also leave
      // because we've nothing to use for our filter even though this is the
      // media library.
      
      return;
    }
    
    // finally, we can produce the <select> element for our ratios using
    // Timber to keep the concerns of HTML and PHP separate as follows.
    
    Timber::render('aspect-ratio-filter.twig', [
      'current' => $_GET['ratio'] ?? '',
      'ratios'  => $ratios,
    ]);
  }
  
  /**
   * filterByAspectRatio
   *
   * When the media library is presented in list view and a ratio has been
   * selected as a filter, this method adds a meta key limitation to the query
   * used to display that list.
   *
   * @param WP_Query $query
   *
   * @return void
   */
  protected function filterByAspectRatio(WP_Query $query): void
  {
    if (
      $query->get('post_type') === 'attachment' // if we're querying attachments
      && ($_GET['mode'] ?? '') === 'list'       // and we're displaying list mode
      && (!empty($_GET['ratio']))               // and the ratio isn't empty
    ) {
      
      // as long as our conditions are met, all we need to do here is make
      // sure that our query knows to make sure that our aspect-ratio meta
      // data matches the ratio in our query string.  conveniently, this
      // doesn't require a meta query, though more care might be needed to
      // avoid obliterating other meta queries already present.
      
      $query->set('meta_key', $this->handler->getPostMetaNamePrefix() . 'aspect-ratio');
      $query->set('meta_value', $_GET['ratio']);
    }
  }
}
