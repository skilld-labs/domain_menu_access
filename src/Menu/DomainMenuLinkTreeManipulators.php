<?php

namespace Drupal\domain_menu_access\Menu;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\InaccessibleMenuLink;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\domain\DomainNegotiator;
use Drupal\domain_entity\DomainEntityMapper;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Provides a couple of menu link tree manipulators.
 *
 * This class provides menu link tree manipulators to:
 * - apply unmatching domain restriction.
 */
class DomainMenuLinkTreeManipulators {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $domainNegotiator;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Array of the entity IDs.
   *
   * @var array
   */
  protected static $entityIdsToLoad = array();

  /**
   * Creates DomainMenuLinkTreeManipulators object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\domain\DomainNegotiator $domain_negotiator
   *   The domain negotiator.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DomainNegotiator $domain_negotiator, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->domainNegotiator = $domain_negotiator;
    $this->languageManager = $language_manager;
  }

  /**
   * Performs access checking for menu link content in an optimized way.
   *
   * This manipulator should be added after the generic ::checkAccess().
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function checkDomain(array $tree) {
    $current_language = $this->languageManager->getCurrentLanguage();

    foreach ($tree as $key => $element) {
      // Other menu tree manipulators may already have calculated access, do not
      // overwrite the existing value in that case if already forbidden.
      if (!isset($element->access) || $tree[$key]->access->isAllowed()) {
        if ($access = $this->menuLinkCheckAccess($element->link, $current_language)) {
          if ($access->isForbidden()) {
            $tree[$key]->access = $access;
            // Secure unavailable menu link.
            $tree[$key]->link = new InaccessibleMenuLink($tree[$key]->link);
            $tree[$key]->subtree = [];
          }
          elseif ($tree[$key]->subtree) {
            $tree[$key]->subtree = $this->checkDomain($tree[$key]->subtree);
          }
        }
      }

      if (isset($tree[$key]->access)) {
        $tree[$key]->access->addCacheContexts(array('url.site'));
      }
    }

    return $tree;
  }

  /**
   * Checks access for one menu link instance.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu link instance.
   * @param \Drupal\Core\Language\LanguageInterface $current_language
   *   The current language.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|null
   *   The access result.
   */
  protected function menuLinkCheckAccess(MenuLinkInterface $instance, LanguageInterface $current_language) {
    // Default access.
    $access_result = AccessResult::allowed();

    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
    if ($entity = $this->loadMenuLinkContentEntity($instance)) {
      if ($entity->hasTranslation($current_language->getId())) {
        $entity = $entity->getTranslation($current_language->getId());
      }

      if (!$this->isAvailableOnAllAffiliates($entity)) {
        $domain_access = [];
        foreach ($entity->get(DomainEntityMapper::FIELD_NAME)->getValue() as $reference) {
          $domain_access[] = $reference['target_id'];
        }

        if (!in_array($this->domainNegotiator->getActiveDomain()->getOriginalId(), $domain_access)) {
          $access_result = AccessResult::forbidden();
        }
      }
    }

    return $access_result->cachePerPermissions();
  }

  /**
   * Check if menu link content is allowed on all affiliates.
   *
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent $entity
   *   Menu link content.
   *
   * @return bool
   *   Returns TRUE when no limits on domains, otherwise FALSE.
   */
  protected function isAvailableOnAllAffiliates(MenuLinkContent $entity) {
    return $entity->get(DomainEntityMapper::FIELD_NAME)->isEmpty();
  }

  /**
   * Load menu link content from menu link entry.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu link instance.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The menu link entity.
   */
  protected function loadMenuLinkContentEntity(MenuLinkInterface $instance) {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $entity = NULL;

    if (!empty($instance->getPluginDefinition()['metadata']['entity_id'])) {
      $entity_id = $instance->getPluginDefinition()['metadata']['entity_id'];
      // Make sure the current ID is in the list, since each plugin empties
      // the list after calling loadMultiple(). Note that the list may include
      // multiple IDs added earlier in each plugin's constructor.
      static::$entityIdsToLoad[$entity_id] = $entity_id;
      $entities = $storage->loadMultiple(array_values(static::$entityIdsToLoad));
      $entity = isset($entities[$entity_id]) ? $entities[$entity_id] : NULL;
      static::$entityIdsToLoad = array();
    }

    if (!$entity) {
      // Fallback to the loading by the UUID.
      if ($uuid = $instance->getDerivativeId()) {
        $loaded_entities = $storage->loadByProperties(array('uuid' => $uuid));
        $entity = reset($loaded_entities);
      }
    }

    return $entity;
  }

}
