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
      $this->addFilter('ajax_query_attachments_args', 'ajaxFilterGridView');
      $this->addAction('restrict_manage_posts', 'addAspectRatioFilter');
      $this->addAction('parse_query', 'filterListView');
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
    ) {
      if (($_GET['mode'] ?? 'grid') === 'grid') {
        $ratios = json_encode($this->getRatios());
        $handle = $this->enqueue('assets/scripts/admin-grid-modifications.js');
        wp_add_inline_script($handle, 'const mbarAspectRatios = ' . $ratios, 'before');
      } else {
        $this->enqueue('assets/scripts/admin-list-modifications.js');
      }
    }
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
  
  protected function ajaxFilterGridView(array $query): array
  {
    if (isset($_REQUEST['query']['media-attachment-ratio-filters'])) {
      if ($_REQUEST['query']['media-attachment-ratio-filters'] !== 'all') {
        $query['meta_key'] = $this->handler->getPostMetaNamePrefix() . 'aspect-ratio';
        $query['meta_value'] = $_REQUEST['query']['media-attachment-ratio-filters'];
        $query['post_mime_type'] = 'image';
      }
      
      $dateQuery = $_REQUEST['query']['media-attachment-date-filters'] ?? 'All dates';
      
      if ($dateQuery !== 'All dates') {
        [$month, $year] = explode(' ', $dateQuery);
        $query['m'] = $year . $this->transformMonthToNumber($month, $year);
      }
    }
    
    return $query;
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
      'current' => $_GET['media-attachment-ratio-filters'] ?? '',
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
  protected function filterListView(WP_Query $query): void
  {
    if (
      $query->get('post_type') === 'attachment'             // if we're querying attachments
      && ($_GET['mode'] ?? 'grid') === 'list'               // and we're displaying list mode
    ) {
      $ratio = $_GET['media-attachment-ratio-filters'] ?? 'all';
      if ($ratio !== 'all') {
  
        // as long as our conditions are met, all we need to do here is make
        // sure that our query knows to make sure that our aspect-ratio meta
        // data matches the ratio in our query string.  conveniently, this
        // doesn't require a meta query, though more care might be needed to
        // avoid obliterating other meta queries already present.
  
        $query->set('meta_key', $this->handler->getPostMetaNamePrefix() . 'aspect-ratio');
        $query->set('meta_value', $ratio);
      }
    }
  }
}
