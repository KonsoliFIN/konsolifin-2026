<?php

namespace Drupal\konsolifin_misc\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Create Content Links block.
 *
 * @Block(
 *   id = "konsolifin_create_content_links",
 *   admin_label = @Translation("Luo uutta sisältöä -linkit (Create Content Links)"),
 *   category = @Translation("KonsoliFIN")
 * )
 */
#[Block(
  id: "konsolifin_create_content_links",
  admin_label: new TranslatableMarkup("Luo uutta sisältöä -linkit (Create Content Links)"),
  category: new TranslatableMarkup("KonsoliFIN"),
)]
class CreateContentLinksBlock extends BlockBase implements ContainerFactoryPluginInterface
{

  /**
   * Constructs a new CreateContentLinksBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array
  {
    $accessControlHandler = $this->entityTypeManager->getAccessControlHandler('node');
    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $allowedTypes = [];
    foreach ($nodeTypes as $type) {
      if ($accessControlHandler->createAccess($type->id(), $this->currentUser)) {
        $allowedTypes[] = [
          'id' => $type->id(),
          'label' => $type->label(),
          'description' => $type->getDescription(),
          'url' => Url::fromRoute('node.add', ['node_type' => $type->id()])->toString(),
        ];
      }
    }

    // Sort alphabetically by label.
    usort($allowedTypes, function ($a, $b) {
      return strcoll((string) $a['label'], (string) $b['label']);
    });

    return [
      '#theme' => 'konsolifin_create_content_links',
      '#content_types' => $allowedTypes,
      '#attached' => [
        'library' => [
          'konsolifin_misc/create-content-links',
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['config:node_type_list'],
      ],
    ];
  }

}
