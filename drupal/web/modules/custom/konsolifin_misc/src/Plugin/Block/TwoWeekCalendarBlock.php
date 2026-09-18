<?php

namespace Drupal\konsolifin_misc\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\konsolifin_misc\Service\EditorialCalendarService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Two Week Editorial Calendar block.
 *
 * @Block(
 *   id = "konsolifin_two_week_calendar",
 *   admin_label = @Translation("Two Week Editorial Calendar"),
 *   category = @Translation("KonsoliFIN")
 * )
 */
#[Block(
  id: "konsolifin_two_week_calendar",
  admin_label: new TranslatableMarkup("Two Week Editorial Calendar"),
  category: new TranslatableMarkup("KonsoliFIN"),
)]
class TwoWeekCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a TwoWeekCalendarBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\konsolifin_misc\Service\EditorialCalendarService $calendarService
   *   The editorial calendar service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EditorialCalendarService $calendarService,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('konsolifin_misc.editorial_calendar'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Restrict access to users with editorial publishing/content overview permissions.
    return AccessResult::allowedIfHasPermissions($account, [
      'view scheduled content',
      'access content overview',
      'administer nodes',
      'bypass node access',
    ], 'OR')->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'bundles' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($nodeTypes as $type) {
      $options[$type->id()] = $type->label();
    }

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types to include'),
      '#description' => $this->t('Select the content types to display in the calendar. Leave all unchecked to include all content types.'),
      '#options' => $options,
      '#default_value' => $this->configuration['bundles'] ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $selected = array_filter($form_state->getValue('bundles') ?: []);
    $this->configuration['bundles'] = array_values($selected);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $bundles = !empty($this->configuration['bundles']) ? $this->configuration['bundles'] : NULL;
    $data = $this->calendarService->getCalendarData($bundles);

    return [
      '#theme' => 'konsolifin_two_week_calendar',
      '#weeks' => $data['weeks'],
      '#unscheduled_content' => $data['unscheduled_content'],
      '#later_scheduled_content' => $data['later_scheduled_content'],
      '#overdue_scheduled_content' => $data['overdue_scheduled_content'],
      '#total_scheduled_count' => $data['total_scheduled_count'],
      '#total_unscheduled_count' => $data['total_unscheduled_count'],
      '#current_time_formatted' => $data['current_time_formatted'],
      '#timezone' => $data['timezone'],
      '#attached' => [
        'library' => [
          'konsolifin_misc/two-week-calendar',
        ],
      ],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['user.permissions', 'timezone'],
        'max-age' => 1800,
      ],
    ];
  }

}
