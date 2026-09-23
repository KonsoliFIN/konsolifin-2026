<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/konsolifin_misc.module';

/**
 * Unit tests for konsolifin_misc_form_user_form_alter().
 */
#[AllowMockObjectsWithoutExpectations]
class UserFormAlterTest extends TestCase
{

  /**
   * Tests that field_slack_id is hidden when user does not have toimitus role.
   */
  public function testSlackIdHiddenWithoutToimitusRole(): void
  {
    $user = $this->createMock(UserInterface::class);
    $user->method('hasRole')->with('toimitus')->willReturn(FALSE);

    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($user);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $form = [
      'field_slack_id' => [
        '#type' => 'textfield',
      ],
    ];

    konsolifin_misc_form_user_form_alter($form, $formState, 'user_form');

    $this->assertArrayHasKey('#access', $form['field_slack_id']);
    $this->assertFalse($form['field_slack_id']['#access']);
  }

  /**
   * Tests that field_slack_id remains accessible when user has toimitus role.
   */
  public function testSlackIdVisibleWithToimitusRole(): void
  {
    $user = $this->createMock(UserInterface::class);
    $user->method('hasRole')->with('toimitus')->willReturn(TRUE);

    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($user);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $form = [
      'field_slack_id' => [
        '#type' => 'textfield',
      ],
    ];

    konsolifin_misc_form_user_form_alter($form, $formState, 'user_form');

    $this->assertArrayNotHasKey('#access', $form['field_slack_id']);
  }

  /**
   * Tests that when field_slack_id is absent from form, nothing fails.
   */
  public function testSlackIdAbsent(): void
  {
    $user = $this->createMock(UserInterface::class);
    $user->method('hasRole')->with('toimitus')->willReturn(FALSE);

    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($user);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $form = [
      'other_field' => [
        '#type' => 'textfield',
      ],
    ];

    konsolifin_misc_form_user_form_alter($form, $formState, 'user_form');

    $this->assertArrayNotHasKey('field_slack_id', $form);
  }

  /**
   * Tests non-entity form objects gracefully return without errors.
   */
  public function testNonEntityFormObject(): void
  {
    $formObject = new \stdClass();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $form = [
      'field_slack_id' => [
        '#type' => 'textfield',
      ],
    ];

    konsolifin_misc_form_user_form_alter($form, $formState, 'user_form');

    $this->assertArrayNotHasKey('#access', $form['field_slack_id']);
  }
}
