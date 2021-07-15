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
      
      // the grid and list views of the media library are handled differently.
      // grid view is entirely built via JS making it hard to manipulate, but
      // list view responds to more typical actions and filters.  once we add
      // our scripts, the ajax filter is for the grid view JS while the latter
      // two actions handle list view.
      
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
    if ($screen->base === 'upload' && $screen->post_type === 'attachment') {
      $mode = $_GET['mode'] ?? 'grid';      // grid view is the default view
      
      // the JS we want to add is different for our two views.  then, if it's
      // grid view, we also need to make sure that it has access to the list of
      // aspect ratios that this plugin maintains in the database.
      
      $jsFile = 'media-library-' . $mode . '-view-mods.js';
      $handle = $this->enqueue('assets/scripts/' . $jsFile);
      
      if ($mode === 'grid') {
        $ratios = json_encode($this->getRatios());
        $jsObject = 'const mbarAspectRatios = ' . $ratios;
        
        // we tell the WP Core script queue to add our inline script before
        // it includes the above media library modifications.  this ensures
        // that those modifications will have access to the mbarAspectRatios
        // object.
        
        wp_add_inline_script($handle, $jsObject , 'before');
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
    
    // the information in the database is an array of AspectRatio objects.  but
    // what we need from within those data are just the actual ratios and their
    // names.  therefore, we walk the array and replace the objects with just
    // the name that they store within them and return it.
    
    array_walk($ratios, fn(AspectRatio &$ratio) => $ratio = $ratio->name);
    return $ratios;
  }
  
  /**
   * ajaxFilterGridView
   *
   * When the grid view page loads, query string parameters are sent via AJAX
   * to the server to identify the attachments to load on-screen.  We can use
   * this to hack our ratio filter into grid view.  This method performs the
   * work to filter grid view on page load.
   *
   * @param array $query
   *
   * @return array
   */
  protected function ajaxFilterGridView(array $query): array
  {
    if (isset($_REQUEST['query']['media-attachment-ratio-filters'])) {
      
      // if our ratio filter is contained within the request, then we have work
      // to do.  first, if the ratio is not set to "all" (i.e. show all ratios
      // a.k.a don't filter anything at all), we want to add both our meta key
      // and its value to our query as well as specifying that images must be
      // what's returned from the server.
      
      if ($_REQUEST['query']['media-attachment-ratio-filters'] !== 'all') {
        $query['meta_key'] = $this->handler->getPostMetaNamePrefix() . 'aspect-ratio';
        $query['meta_value'] = $_REQUEST['query']['media-attachment-ratio-filters'];
        $query['post_mime_type'] = 'image';
      }
      
      // next, we want to see if the date filters are present or if we want to
      // default them to all dates.  if you check the grid view mods JS, you'll
      // see that we submit not the enumerated value of the date filter element
      // but rather than text of its options.  that means our default has to be
      // "All dates" or we won't match the DOM.
      
      $dateQuery = $_REQUEST['query']['media-attachment-date-filters'] ?? 'All dates';
      
      if ($dateQuery !== 'All dates') {
        
        // the "m" WP_Query parameter expects a date in YYYYMM (e.g. 202107)
        // format.  we can use PHP's date function to produce that format and
        // strtotime can take our date query (e.g. July 2021) and produce the
        // timestamp we need.
        
        $query['m'] = date('Ym', strtotime($dateQuery));;
      }
    }
    
    return $query;
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
    if ($postType !== 'attachment' || sizeof($ratios = $this->getRatios()) === 0) {
      
      // if this isn't our media library, we can leave; we don't want to do
      // anything in that case.  or, if it is the media library, but our we
      // don't have any ratios in the database at this time, we can leave then
      // too cause there's no data for us to use when constructing our filter.
      
      return;
    }
    
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
      $query->get('post_type') === 'attachment'   // if we're querying attachments
      && ($_GET['mode'] ?? 'grid') === 'list'     // and we're displaying list mode
    ) {
      
      // if we're querying for attachments in list view, then we may have work
      // to do.  to confirm that we do, we'll see if we've specified a ratio
      // on which to filter.  if so, we can add that information to our query.
      
      $ratio = $_GET['media-attachment-ratio-filters'] ?? 'all';
      
      if ($ratio !== 'all') {
        $query->set('meta_key', $this->handler->getPostMetaNamePrefix() . 'aspect-ratio');
        $query->set('meta_value', $ratio);
      }
    }
  }
}
