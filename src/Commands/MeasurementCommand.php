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
  // time limit, too.
  
  public const DEFAULT_TIME_LIMIT = 30;
  
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
    $this->addArgument(new AssociativeArgument('time-limit', 'The length of time in seconds to work  (default 30, 0 for unlimited)'));
    $this->setShortDesc('Measures aspect ratios for images in the media library');
    
    $longDesc = <<< DESC
      ## EXAMPLES
      
        # Measures images in media library
        $ wp media-by-aspect-ratio measure
        Success: Images measured.
        
        # Measures some images in media library before timing out
        $ wp media-by-aspect-ratio measure
        Warning: Some images measured; run again to proceed.
        
        # Specifies length of time in seconds for measurement (0 for no limit)
        $ wp media-by-aspect-ratio measure --time-limit=120
        Success: Images measured.
DESC;
    
    $this->setLongDesc($longDesc);
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
    
    $images = $this->getImages();
    if (($imageCount = sizeof($images)) === 0) {
      
      // if we didn't select any images, it's likely that either (a) there are
      // none in the media library or (b) all of the images in the library have
      // already been measured.  either way, we'll let the executor know and
      // then return.
      
      WP_CLI::warning('No unmeasured images found.');
      return;
    }
  
    // for our time limit, we get what the visitor specified (or our default)
    // and then we reduce it by three seconds to give us a buffer between when
    // our work needs to be done and when (presumably) PHP will cut the process
    // off more abruptly.
    
    $timeLimit = $this->getTimeLimit($flags) - 3;
    $start = time();
    
    $complete = true;
    foreach ($images as $i => $imageId) {
      $this->measureImage($imageId);
      if ((time() - $start) > $timeLimit) {
        
        // it's possible that, despite exceeding our time limit, we've also
        // processed all of our images.  so, we'll see if our loop index is the
        // same as the size of our array (modified to avoid a possible off-by-
        // one error) and then break out of the loop.
        
        $complete = $i === $imageCount - 1;
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
   * getTimeLimit
   *
   * Extracts the value for our time limit parameter from the parameter
   *
   * @param array $flags
   *
   * @return int
   */
  private function getTimeLimit(array $flags): int
  {
    $timeLimit = $flags['time-limit'] ?? self::DEFAULT_TIME_LIMIT;
    
    // in case someone gets cute and sends a string or floating point time
    // limit, we'll be sure we return an int here.  if we have a number, we
    // pass it through floor to remove any fractional parts; otherwise, we just
    // return our default again if it was a string or some other value.
    
    return is_numeric($timeLimit) ? floor($timeLimit) : self::DEFAULT_TIME_LIMIT;
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
    
    // if we have a width and a height, we calculate the ratio between them
    // to the precision specified by our AspectRatio object.  if those data are
    // missing for some reason, we just default to zero.  we have to store
    // something in the database or this image would be re-selected if the
    // measure command is executed again.
    
    $ratio = isset($imageData['width']) && isset($imageData['height'])
      ? round($imageData['width'] / $imageData['height'], AspectRatio::PRECISION)
      : 0;
  
    $this->handler->updatePostMeta($imageId, 'aspect-ratio', $ratio);
  }
}

