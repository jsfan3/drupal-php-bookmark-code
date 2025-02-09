<?php

namespace Drupal\php_bookmark_code\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to manage PHP bookmark code blocks.
 */
class PhpBookmarkCodeSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'php_bookmark_code_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Determine the number of bookmark blocks to display.
    $num_blocks = $form_state->get('num_blocks');
    if ($num_blocks === NULL) {
      // Load existing bookmarks from the database.
      $query = \Drupal::database()->select('php_bookmark_code', 'p')
        ->fields('p', ['id', 'bookmark', 'title', 'code', 'enabled']);
      $results = $query->execute()->fetchAll();
      $existing_blocks = [];
      foreach ($results as $result) {
        $existing_blocks[] = $result;
      }
      $num_blocks = count($existing_blocks);
      if ($num_blocks < 1) {
        $num_blocks = 1;
      }
      $form_state->set('existing_blocks', $existing_blocks);
      $form_state->set('num_blocks', $num_blocks);
    }
    else {
      $existing_blocks = $form_state->get('existing_blocks');
    }

    $form['blocks'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="blocks-wrapper">',
      '#suffix' => '</div>',
    ];

    // Build each bookmark block.
    for ($i = 0; $i < $num_blocks; $i++) {
      $block = isset($existing_blocks[$i]) ? $existing_blocks[$i] : NULL;
      $form['blocks'][$i] = [
        '#type' => 'fieldset',
        '#title' => $block ? $block->title : $this->t('New PHP Bookmark Code Block'),
      ];
      if ($block && !empty($block->id)) {
        $form['blocks'][$i]['id'] = [
          '#type' => 'hidden',
          '#value' => $block->id,
        ];
      }
      $form['blocks'][$i]['bookmark'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Bookmark Identifier'),
        '#default_value' => $block ? $block->bookmark : '',
        '#description' => $this->t('Unique identifier to be used in content as [bookmark:identifier].'),
        '#required' => TRUE,
      ];
      $form['blocks'][$i]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $block ? $block->title : '',
        '#required' => TRUE,
      ];
      $form['blocks'][$i]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#default_value' => $block ? $block->enabled : 0,
      ];
      $form['blocks'][$i]['code'] = [
        '#type' => 'textarea',
        '#title' => $this->t('PHP Code'),
        '#default_value' => $block ? $block->code : '',
        '#description' => $this->t('Enter PHP code to be executed when the bookmark is encountered. DO NOT include the <?php ?> tags.'),
        '#rows' => 10,
      ];
      // Add a checkbox to allow deletion of this block.
      $form['blocks'][$i]['remove'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Delete this block'),
        '#default_value' => 0,
      ];
    }

    $form['add_block'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another PHP Bookmark Code Block'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'blocks-wrapper',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * AJAX callback for adding a new block.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['blocks'];
  }

  /**
   * Submit handler for the "Add another block" button.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $num_blocks = $form_state->get('num_blocks');
    $num_blocks++;
    $form_state->set('num_blocks', $num_blocks);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $blocks = $form_state->getValue('blocks');
    $connection = \Drupal::database();

    // Process each bookmark block.
    foreach ($blocks as $block) {
      // If the block is marked for removal, delete it (if it exists) and skip further processing.
      if (!empty($block['remove'])) {
        if (!empty($block['id'])) {
          $connection->delete('php_bookmark_code')
            ->condition('id', $block['id'])
            ->execute();
        }
        continue;
      }

      // Prepare data to save/update.
      $data = [
        'bookmark' => $block['bookmark'],
        'title' => $block['title'],
        'code' => $block['code'],
        'enabled' => $block['enabled'] ? 1 : 0,
        'changed' => \Drupal::time()->getRequestTime(),
      ];

      if (!empty($block['id'])) {
        // Update existing bookmark.
        $connection->update('php_bookmark_code')
          ->fields($data)
          ->condition('id', $block['id'])
          ->execute();
      }
      else {
        // Insert new bookmark.
        $data['created'] = \Drupal::time()->getRequestTime();
        $connection->insert('php_bookmark_code')
          ->fields($data)
          ->execute();
      }
    }

    $this->messenger()->addStatus($this->t('PHP Bookmark Code Blocks configuration saved.'));
  }
}

