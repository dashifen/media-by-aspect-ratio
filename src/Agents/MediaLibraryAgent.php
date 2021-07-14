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
      $this->addAction('admin_enqueue_scripts', 'addMediaLibraryScripts');
      $this->addAction('restrict_manage_posts', 'addAspectRatioFilter');
      $this->addAction('parse_query', 'filterByAspectRatio');
    }
  }
  
  /**
   * addMediaLibraryScripts
   *
   * Adds necessary JS to the media library page when it's in grid view.
   *
   * @throws TransformerException
   * @throws HandlerException
   */
  protected function addMediaLibraryScripts(): void
  {
    $screen = get_current_screen();
    
    if (
      $screen->base === 'upload'              // if we're uploading
      && $screen->post_type === 'attachment'  // attachments
      && ($_GET['mode'] ?? '') !== 'list'     // in grid view
    ) {
      $ratios = json_encode($this->getRatios());
      $handle = $this->enqueue('assets/scripts/admin-grid-modifications.js');
      wp_add_inline_script($handle, 'const aspectRatios = ' . $ratios, 'before');
    }
  }
  
  /**
   * transformMonthToNumber
   *
   * Given the name of a month, returns a two digit number representing it
   * where 01 is January and 12 is December.
   *
   * @param string $month
   * @param int    $year
   *
   * @return int
   * @link https://stackoverflow.com/a/9941819/360838
   */
  private function transformMonthToNumber(string $month, int $year): int
  {
    // in a perfect world, strtotime($month) would be enough, but this world
    // has leap years.  for more information about why the following works,
    // see stack overflow at the link above.
    
    return date('m', strtotime(strtolower($month) . '-' . $year));
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
    
    $ratios = $this->getRatios();
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
   * getRatios
   *
   * Returns an array mapping the decimal representation of aspect ratios to
   * their names (e.g. 1.778 => 16:9).
   *
   * @return array
   * @throws HandlerException
   * @throws TransformerException
   */
  private function getRatios(): array
  {
    $ratios = $this->handler->getOption('aspect-ratios', []);
    array_walk($ratios, fn(AspectRatio &$ratio) => $ratio = $ratio->name);
    return $ratios;
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
    $ratio = $_REQUEST['media-attachment-ratio-filters'] ?? null;
    if ($query->get('post_type') === 'attachment' && $ratio !== null) {
      if (($_REQUEST['mode'] ?? 'grid') === 'list') {
        $this->addAspectRatioLimitationToQuery($query);
      } else {
        $this->filterGridView($query);
      }
    }
  }
  
  /**
   * addAspectRatioLimitationToQuery
   *
   * Given a WP_Query object, adds a limitation that ensures we only show
   * images with a specific aspect ratio.
   *
   * @param WP_Query $query
   *
   * @return void
   */
  private function addAspectRatioLimitationToQuery(WP_Query $query): void
  {
    $query->set('meta_key', $this->handler->getPostMetaNamePrefix() . 'aspect-ratio');
    $query->set('meta_value', $_REQUEST['media-attachment-date-filters']);
  }
  
  /**
   * filterGridView
   *
   * Typically, the grid view is handled via AJAX, but we've added a filter
   * with JS based refresh capabilities to crib together a way to filter it
   * here.
   *
   * @param WP_Query $query
   *
   * @return void
   */
  private function filterGridView(WP_Query $query): void
  {
    $this->addAspectRatioLimitationToQuery($query);
    
    
    self::debug($query, true);
  }
}
