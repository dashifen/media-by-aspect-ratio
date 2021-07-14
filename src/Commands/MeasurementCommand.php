<?php

namespace Dashifen\MediaByAspectRatio\Commands;

use WP_CLI;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Commands\AbstractCommand;
use Dashifen\WPHandler\Commands\CommandException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\MediaByAspectRatio\MediaByAspectRatio;
use Dashifen\MediaByAspectRatio\Repositories\AspectRatio;
use Dashifen\WPHandler\Commands\Arguments\AssociativeArgument;

/**
 * Class MeasurementCommand
 *
 * @property MediaByAspectRatio $handler
 *
 * @package Dashifen\MediaByAspectRatio\Commands
 */
class MeasurementCommand extends AbstractCommand
{
  // PHP's default max_execution_time is 30, so we'll use that as our default
  // timeout, too.
  
  public const DEFAULT_TIMEOUT = 30;
  
  /**
   * initialize
   *
   * Sets the initial state of the properties that define this command and,
   * rarely, uses addAction and/or addFilter to attach protected methods of
   * this object to the ecosystem of WordPress action and filter hooks.
   *
   * @return void
   * @throws CommandException
   */
  public function initialize(): void
  {
    $this->addArgument(new AssociativeArgument('timeout', 'The length of time in seconds to work before timing out (default 30, 0 for unlimited)'));
    $this->setShortDesc('Measures aspect ratios for images in the media library');
    $this->setLongDesc(<<< DESC
      ## EXAMPLES
      
        # Measures images in media library
        $ wp media-by-aspect-ratio measure
        Success: Images measured.
        
        # Measures some images in media library before timing out
        $ wp media-by-aspect-ratio measure
        Warning: Some images measured; run again to proceed.
        
        # Specifies length of time in seconds for measurement (0 for no limit)
        $ wp media-by-aspect-ratio measure --timeout=120
        Success: Images measured.
DESC
    );
  }
  
  /**
   * execute
   *
   * Performs the behaviors of this command.
   *
   * @param array $args
   * @param array $flags
   *
   * @return void
   * @throws HandlerException
   * @throws TransformerException
   */
  protected function execute(array $args, array $flags): void
  {
    // there actually aren't any (positional) args for this command, so our
    // first parameter is unnecessary.  sadly, there's no way to avoid it being
    // delivered here, so we'll just ignore it.
    
    $start = time();
    $timeout = $this->getTimeout($flags);
    $images = $this->getImages();
    
    $complete = true;
    foreach ($images as $i => $imageId) {
      $this->measureImage($imageId);
      $elapsed = $start - time();
      
      // if we're within three seconds of our time limit, we'll quit early to
      // try and ensure that we don't end up throwing a PHP error.  this should
      // allow us to call WP_CLI::error instead.
      
      if ($elapsed > $timeout - 3) {
        
        // it's possible that we're within three seconds of our time limit and
        // also just measured the last image in the library.  so, we'll check
        // to see if $i is the same as the size of our set of images with the
        // necessary off-by-one modification.
        
        $complete = $i === sizeof($images) - 1;
        break;
      }
    }
    
    if ($complete) {
      WP_CLI::success('Images measured.');
    } else {
      WP_CLI::warning('Some images measured; run again to proceed.');
    }
  }
  
  /**
   * getTimeout
   *
   * Extracts the value for our timeout parameter from the parameter
   *
   * @param array $flags
   *
   * @return int
   */
  private function getTimeout(array $flags): int
  {
    $timeout = $flags['timeout'] ?? self::DEFAULT_TIMEOUT;
    
    // in case someone gets cute and sends a string or floating point timeout,
    // we'll be sure we return an int here.  if we have a number, we pass it
    // through floor to remove any fractional parts; otherwise, we just return
    // our default again if it was a string or some other value.
    
    return is_numeric($timeout) ? floor($timeout) : self::DEFAULT_TIMEOUT;
  }
  
  /**
   * getImages
   *
   * Returns an array of WP_Post objects for the image attachments in this
   * site's media library that have not already been measured.
   *
   * @return int[]
   */
  private function getImages(): array
  {
    // since we need to search based on the complete meta key name, we have
    // to add our meta name prefix by hand.  our handler's trait does that for
    // us for its methods, but we don't have one that, at this time, prepares
    // a meta query for us based on a specific post meta key or value.  so,
    // we'll do it by hand.
    
    $metaKey = $this->handler->getPostMetaNamePrefix() . 'aspect-ratio';
    
    return get_posts([
      'posts_per_page' => -1,               // get all posts
      'orderby'        => 'ID',             // ordered by their ID
      'order'          => 'ASC',            // in ascending order
      'post_type'      => 'attachment',     // where they're attachments
      'post_mime_type' => 'image',          // that are an image
      'post_status'    => 'inherit',        // which inherit their post's status
      'meta_key'       => $metaKey,         // for which this meta key
      'meta_compare'   => 'NOT EXISTS',     // doesn't exist
      'fields'         => 'ids',            // returning only their IDs
    ]);
  }
  
  /**
   * measureImage
   *
   * Determines the aspect ratio for the specified image.
   *
   * @param int $imageId
   *
   * @return void
   * @throws HandlerException
   * @throws TransformerException
   */
  private function measureImage(int $imageId): void
  {
    $imageData = get_post_meta($imageId, '_wp_attachment_metadata', true);
    if (isset($imageData['width']) && isset($imageData['height'])) {
      
      // if we have dimensions for this image, then we want to calculate the
      // ratio between them.  then, we compare that to the ratios that we care
      // about on this site.  if we have a match that's within a certain
      // threshold, we identify this image as having the ratio of that match.
      
      $ratio = round($imageData['width'] / $imageData['height'], AspectRatio::PRECISION);
      $this->handler->updatePostMeta($imageId, 'aspect-ratio', $ratio);
    } else {
      
      // and, finally, if we didn't have image dimensions for some reason, then
      // we can't do anything except store a zero in the database.  we do this
      // for the same reason we store the actual ratio above:  if we don't
      // store something, this image will be re-selected for measurement over
      // and over again.
      
      $this->handler->updatePostMeta($imageId, 'aspect-ratio', 0);
    }
  }
}

