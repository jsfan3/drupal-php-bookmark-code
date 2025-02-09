<?php

namespace Drupal\php_bookmark_code\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a filter to replace bookmark placeholders with PHP code output.
 *
 * @Filter(
 *   id = "filter_php_bookmark",
 *   title = @Translation("PHP Bookmark Filter"),
 *   description = @Translation("Replaces bookmark placeholders in content with the output of associated PHP code."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class PhpBookmarkFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Define the pattern for bookmarks: e.g., [bookmark:identifier].
    $pattern = '/\[bookmark:([a-zA-Z0-9_\-]+)\]/';
    $callback = function ($matches) {
      $bookmark = $matches[1];
      // Retrieve the code for this bookmark from the database.
      $record = \Drupal::database()->select('php_bookmark_code', 'p')
        ->fields('p', ['code', 'enabled'])
        ->condition('bookmark', $bookmark)
        ->execute()
        ->fetchObject();
      if ($record && $record->enabled) {
        ob_start();
        try {
          $code = $record->code;
          if (substr(trim($code), -1) !== ';') {
            $code .= ';';
          }
          eval($code);
        }
        catch (\Throwable $e) {
          \Drupal::logger('php_bookmark_code')->error('Error executing code for bookmark %bookmark: %error', [
            '%bookmark' => $bookmark,
            '%error' => $e->getMessage(),
          ]);
        }
        return ob_get_clean();
      }
      return '';
    };

    $new_text = preg_replace_callback($pattern, $callback, $text);
    return new FilterProcessResult(Markup::create($new_text)); // for CKEditor compatibility
  }

  /**
   * {@inheritdoc}
   */
  public function isFilterHtmlSafe() {
    return TRUE;
  }
}

