<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\konsolifin_misc\EventSubscriber\GonePathSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Unit tests for GonePathSubscriber.
 */
#[AllowMockObjectsWithoutExpectations]
class GonePathSubscriberTest extends TestCase {

  /**
   * Mock config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Mock immutable config for gone paths.
   */
  protected ImmutableConfig $goneConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->goneConfig = $this->createMock(ImmutableConfig::class);

    $this->configFactory->method('get')
      ->with('konsolifin_misc.gone_paths')
      ->willReturn($this->goneConfig);
  }

  /**
   * Tests that the subscriber listens to KernelEvents::REQUEST with priority 50.
   */
  public function testSubscribedEvents(): void {
    $events = GonePathSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertSame('onKernelRequest', $events[KernelEvents::REQUEST][0][0]);
    $this->assertSame(50, $events[KernelEvents::REQUEST][0][1]);
  }

  /**
   * Tests prefix matching logic with various paths and prefixes.
   */
  #[DataProvider('prefixMatchingDataProvider')]
  public function testMatchesPrefix(string $path, string $prefix, bool $expected): void {
    $this->assertSame($expected, GonePathSubscriber::matchesPrefix($path, $prefix));
  }

  /**
   * Data provider for testMatchesPrefix.
   *
   * @return array<string, array{string, string, bool}>
   */
  public static function prefixMatchingDataProvider(): array {
    return [
      'bbs directory exact without slash' => ['/bbs', '/bbs/', TRUE],
      'bbs directory exact with slash' => ['/bbs/', '/bbs/', TRUE],
      'bbs viewtopic subpath' => ['/bbs/viewtopic.php', '/bbs/', TRUE],
      'bbs case insensitive' => ['/BBS/Index.php', '/bbs/', TRUE],
      'bbs prefix without trailing slash matches subpath' => ['/bbs/threads/1', '/bbs', TRUE],
      'bbs prefix without trailing slash matches exact' => ['/bbs', '/bbs', TRUE],
      'bbs prefix does not match unrelated prefix' => ['/bbs-discussion', '/bbs/', FALSE],
      'bbs prefix without slash does not match hyphenated' => ['/bbs-forum', '/bbs', FALSE],
      'konsolifin.php exact' => ['/konsolifin.php', '/konsolifin.php', TRUE],
      'konsolifin.php case insensitive' => ['/KONSOLIFIN.PHP', '/konsolifin.php', TRUE],
      'konsolifin.php subpath' => ['/konsolifin.php/action', '/konsolifin.php', TRUE],
      'konsolifin.php does not match other script' => ['/konsolifin-other.php', '/konsolifin.php', FALSE],
      'arviolista.php exact' => ['/arviolista.php', '/arviolista.php', TRUE],
      'arviolista.php subpath' => ['/arviolista.php/list', '/arviolista.php', TRUE],
      'regular page does not match' => ['/uutiset', '/bbs/', FALSE],
      'root path does not match' => ['/', '/bbs/', FALSE],
      'empty prefix does not match' => ['/bbs', '', FALSE],
      'root prefix does not match' => ['/bbs', '/', FALSE],
      'prefix without leading slash works' => ['/bbs/topic', 'bbs/', TRUE],
    ];
  }

  /**
   * Tests that a matching path returns HTTP 410 Gone with branded HTML.
   */
  public function testOnKernelRequestWithMatchingPath(): void {
    $this->goneConfig->method('get')
      ->with('prefixes')
      ->willReturn(['/bbs/', '/konsolifin.php', '/arviolista.php']);

    $subscriber = new GonePathSubscriber($this->configFactory);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/bbs/viewtopic.php?t=12345');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onKernelRequest($event);

    $response = $event->getResponse();
    $this->assertInstanceOf(Response::class, $response);
    $this->assertSame(410, $response->getStatusCode());
    $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    $this->assertStringContainsString('410 Gone', $response->getContent());
    $this->assertStringContainsString('Sivua ei ole enää olemassa', $response->getContent());
    $this->assertStringContainsString('Konsoli<tspan fill="#df5500">FIN</tspan>', $response->getContent());
    $this->assertStringContainsString('href="/"', $response->getContent());
  }

  /**
   * Tests that a non-matching path does not set a response.
   */
  public function testOnKernelRequestWithNonMatchingPath(): void {
    $this->goneConfig->method('get')
      ->with('prefixes')
      ->willReturn(['/bbs/', '/konsolifin.php', '/arviolista.php']);

    $subscriber = new GonePathSubscriber($this->configFactory);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/uutiset/uusi-peli-julkaistu');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onKernelRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * Tests that sub-requests are ignored even if the path matches.
   */
  public function testOnKernelRequestIgnoredForSubRequest(): void {
    $this->goneConfig->method('get')
      ->with('prefixes')
      ->willReturn(['/bbs/']);

    $subscriber = new GonePathSubscriber($this->configFactory);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/bbs/index.php');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

    $subscriber->onKernelRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * Tests that default prefixes are used when config is not set (NULL).
   */
  public function testOnKernelRequestUsesDefaultsWhenConfigNull(): void {
    $this->goneConfig->method('get')
      ->with('prefixes')
      ->willReturn(NULL);

    $subscriber = new GonePathSubscriber($this->configFactory);

    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create('/konsolifin.php');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

    $subscriber->onKernelRequest($event);

    $response = $event->getResponse();
    $this->assertInstanceOf(Response::class, $response);
    $this->assertSame(410, $response->getStatusCode());
  }

  /**
   * Tests custom prefixes from config.
   */
  public function testOnKernelRequestUsesCustomPrefixes(): void {
    $this->goneConfig->method('get')
      ->with('prefixes')
      ->willReturn(['/vanha-osio/', '/legacy.php']);

    $subscriber = new GonePathSubscriber($this->configFactory);

    $kernel = $this->createMock(HttpKernelInterface::class);

    // Custom path should match.
    $request1 = Request::create('/vanha-osio/sivu');
    $event1 = new RequestEvent($kernel, $request1, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequest($event1);
    $this->assertNotNull($event1->getResponse());
    $this->assertSame(410, $event1->getResponse()->getStatusCode());

    // Old default path should not match anymore since custom list is set.
    $request2 = Request::create('/bbs/index.php');
    $event2 = new RequestEvent($kernel, $request2, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->onKernelRequest($event2);
    $this->assertNull($event2->getResponse());
  }

}
