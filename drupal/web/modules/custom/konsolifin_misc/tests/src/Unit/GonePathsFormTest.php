<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\konsolifin_misc\EventSubscriber\GonePathSubscriber;
use Drupal\konsolifin_misc\Form\GonePathsForm;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GonePathsForm.
 */
#[AllowMockObjectsWithoutExpectations]
class GonePathsFormTest extends TestCase {

  /**
   * Mock config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Mock typed config manager.
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * Mock config object.
   */
  protected Config $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->typedConfigManager = $this->createMock(TypedConfigManagerInterface::class);
    $this->config = new Config(
      'konsolifin_misc.gone_paths',
      $this->createMock(StorageInterface::class),
      $this->createMock(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class),
      $this->typedConfigManager
    );

    $this->configFactory->method('get')
      ->with('konsolifin_misc.gone_paths')
      ->willReturn($this->config);
    $this->configFactory->method('getEditable')
      ->with('konsolifin_misc.gone_paths')
      ->willReturn($this->config);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')
      ->willReturnCallback(fn($translatable) => $translatable->getUntranslatedString());

    $messenger = $this->createMock(\Drupal\Core\Messenger\MessengerInterface::class);

    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('config.factory', $this->configFactory);
    $container->set('config.typed', $this->typedConfigManager);
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);
  }

  /**
   * Helper to create form instance.
   */
  protected function createForm(): GonePathsForm {
    return new GonePathsForm($this->configFactory, $this->typedConfigManager);
  }

  /**
   * Tests getFormId.
   */
  public function testGetFormId(): void {
    $form = $this->createForm();
    $this->assertSame('konsolifin_misc_gone_paths', $form->getFormId());
  }

  /**
   * Tests buildForm with defaults when no configuration is saved.
   */
  public function testBuildFormWithDefaults(): void {
    $formObject = $this->createForm();
    $formState = new FormState();

    $form = $formObject->buildForm([], $formState);

    $this->assertArrayHasKey('prefixes', $form);
    $this->assertSame(
      implode("\n", GonePathSubscriber::DEFAULT_PREFIXES),
      $form['prefixes']['#default_value']
    );
  }

  /**
   * Tests validateForm rejects root path.
   */
  public function testValidateFormRejectsRoot(): void {
    $formObject = $this->createForm();
    $formState = new FormState();
    $formState->setValue('prefixes', "/bbs/\n/\n/konsolifin.php");

    $form = [];
    $formObject->validateForm($form, $formState);

    $errors = $formState->getErrors();
    $this->assertArrayHasKey('prefixes', $errors);
  }

  /**
   * Tests validateForm rejects reserved Drupal core paths.
   */
  public function testValidateFormRejectsReservedPaths(): void {
    $formObject = $this->createForm();
    $formState = new FormState();
    $formState->setValue('prefixes', "/bbs/\n/admin\n/konsolifin.php");

    $form = [];
    $formObject->validateForm($form, $formState);

    $errors = $formState->getErrors();
    $this->assertArrayHasKey('prefixes', $errors);
  }

  /**
   * Tests submitForm normalizes, deduplicates, and saves prefixes.
   */
  public function testSubmitFormNormalizesAndSaves(): void {
    $formObject = $this->createForm();
    $formState = new FormState();
    $formState->setValue('prefixes', "  bbs/ \n\n/konsolifin.php\n  /bbs/\n/arviolista.php  \n");

    $form = [];
    $formObject->submitForm($form, $formState);

    $saved = $this->config->get('prefixes');
    $this->assertSame(['/bbs/', '/konsolifin.php', '/arviolista.php'], $saved);
  }

}
