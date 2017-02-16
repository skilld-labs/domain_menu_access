<?php

namespace Drupal\domain_menu_access\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class for build Domain menu access module settings.
 */
class DomainMenuAccessSettingsForm extends FormBase {

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManager
   */
  protected $blockManager;

  /**
   * Menu entities storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $menuStorage;

  /**
   * DomainMenuAccessSettingsForm constructor.
   *
   * @param BlockManagerInterface $block_manager
   *   The block manager.
   * @param ConfigEntityStorageInterface $menu_storage
   *   The storage of menu entities.
   */
  public function __construct(BlockManagerInterface $block_manager, ConfigEntityStorageInterface $menu_storage) {
    $this->blockManager = $block_manager;
    $this->menuStorage = $menu_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager')->getStorage('menu')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_menu_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (empty($this->menuStorage->loadMultiple())) {
      $form['markup'] = [
        '#markup' => $this->t('Your menu list is empty. Please, try add the menu and return here.'),
      ];
    }
    else {
      $form['description'] = [
        '#markup' => $this->t('Please, select menu for enable control by domain records.'),
      ];
      /** @var \Drupal\system\Entity\Menu $item */
      foreach ($this->menuStorage->loadMultiple() as $key => $item) {
        $form[$key] = array(
          '#type' => 'checkbox',
          '#title' => $item->label(),
          '#default_value' => $item->getThirdPartySetting('domain_menu_access', 'access_enabled'),
          '#description' => $item->getDescription(),
        );
      }
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $clear_cache = FALSE;
    $values = $form_state->getValues();
    /** @var \Drupal\system\Entity\Menu $item */
    foreach ($this->menuStorage->loadMultiple() as $key => $item) {
      if ($values[$key]) {
        if (boolval($values[$key]) !== $item->getThirdPartySetting('domain_menu_access', 'access_enabled')) {
          $item->setThirdPartySetting('domain_menu_access', 'access_enabled', boolval($values[$key]));
          $item->save();
          $clear_cache = TRUE;
        }
      }
    }

    if ($clear_cache) {
      $this->blockManager->clearCachedDefinitions();
    }

    drupal_set_message($this->t('The configuration options have been saved.'));
  }

}
