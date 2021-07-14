<?php
/**
 * Plugin Name: Media by Aspect Ratio
 * Description: A WordPress plugin that provides the capability to show images that match specific aspect ratios.
 * Author URI: mailto:dashifen@dashifen.com
 * Author: David Dashifen Kees
 * Version: 1.0.0
 *
 * @noinspection PhpStatementHasEmptyBodyInspection
 * @noinspection PhpIncludeInspection
 */

namespace Dashifen;

use Dashifen\Exception\Exception;
use Dashifen\MediaByAspectRatio\MediaByAspectRatio;
use Dashifen\MediaByAspectRatio\Agents\CommandLineAgent;
use Dashifen\MediaByAspectRatio\Agents\MediaLibraryAgent;
use Dashifen\WPHandler\Agents\Collection\Factory\AgentCollectionFactory;

// the first autoloader location is the place it can be found for my personal
// work at dashifen.com.  if that's not found, we move onto the location that
// it lives for my professional work.  eventually, I want to make these the
// same, but that day is not this day.  finally, if we can't find any of those
// we go with a vendor folder adjacent to this file.  if none of them can be
// found, then the require_once call will throw a fatal PHP error.

if (file_exists($autoloader = realpath(dirname(ABSPATH) . '/deps/vendor/autoload.php'))) ;
elseif (file_exists($autoloader = realpath(ABSPATH . 'vendor/autoload.php'))) ;
else $autoloader = 'vendor/autoload.php';
require_once $autoloader;

try {
  (function () {
    
    // by instantiating our plugin within this anonymous function, it prevents
    // these variables from being added to the WordPress global scope.
    
    $plugin = new MediaByAspectRatio();
    $agentCollectionFactory = new AgentCollectionFactory();
    
    if (is_admin()) {
      
      // most of what we do in this plugin is restricted to the Dashboard.  in
      // fact, most of it is restricted to only those who can upload files, but
      // we don't yet have access to the current_user_can method when this file
      // is interpreted.  so, we'll limit the agents that are registered here,
      // and then the MediaByAspectRatio object can further limit our work as
      // needed after the rest of the WP Core environment has been loaded.
      
      $agentCollectionFactory->registerAgent(MediaLibraryAgent::class);
    }
    
    $agentCollectionFactory->registerAgent(CommandLineAgent::class);
    $plugin->setAgentCollection($agentCollectionFactory);
    $plugin->initialize();
  })();
} catch (Exception $e) {
  
  // honestly, there's nothing to this plugin that would necessarily break a
  // site if it can't be initialized.  we can let the site continue to operate
  // rather than nuking the site from orbit.  we'll write the error to the log
  // and fire a warning, but otherwise let the site continue.
  
  trigger_error('Unable to initialize MediaByAspectRatio plugin.', E_USER_WARNING);
  MediaByAspectRatio::writeLog($e);
}
