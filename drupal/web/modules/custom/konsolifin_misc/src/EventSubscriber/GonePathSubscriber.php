<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Catches requests to legacy paths and returns HTTP 410 Gone immediately.
 *
 * Runs before RouterListener (priority 32) so that legacy URLs (e.g. /bbs/,
 * /konsolifin.php, /arviolista.php) are intercepted without database lookup,
 * routing overhead, or 404 watchdog log spam.
 */
class GonePathSubscriber implements EventSubscriberInterface {

  /**
   * Default path prefixes that return 410 Gone when no config is stored.
   */
  public const array DEFAULT_PREFIXES = [
    '/bbs/',
    '/konsolifin.php',
    '/arviolista.php',
  ];

  /**
   * Constructs a GonePathSubscriber object.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority 50 executes before Symfony/Drupal RouterListener (priority 32).
    return [
      KernelEvents::REQUEST => [['onKernelRequest', 50]],
    ];
  }

  /**
   * Intercepts the request and returns 410 if the path matches a gone prefix.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();

    $prefixes = $this->getPrefixes();
    foreach ($prefixes as $prefix) {
      if (self::matchesPrefix($path, $prefix)) {
        $event->setResponse($this->buildGoneResponse());
        return;
      }
    }
  }

  /**
   * Checks whether a request path matches a configured prefix.
   *
   * @param string $path
   *   The incoming request path (e.g. '/bbs/viewtopic.php').
   * @param string $prefix
   *   The configured prefix (e.g. '/bbs/' or '/konsolifin.php').
   *
   * @return bool
   *   TRUE if the path matches the prefix, FALSE otherwise.
   */
  public static function matchesPrefix(string $path, string $prefix): bool {
    $cleanPrefix = trim($prefix);
    if ($cleanPrefix === '' || $cleanPrefix === '/') {
      return FALSE;
    }
    if (!str_starts_with($cleanPrefix, '/')) {
      $cleanPrefix = '/' . $cleanPrefix;
    }

    $lowerPath = mb_strtolower($path);
    $basePrefix = mb_strtolower(rtrim($cleanPrefix, '/'));

    // Exact match (e.g. path is '/bbs' or '/konsolifin.php').
    if ($lowerPath === $basePrefix) {
      return TRUE;
    }

    // Subpath match (e.g. '/bbs/...' or '/konsolifin.php/...').
    if (str_starts_with($lowerPath, $basePrefix . '/')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Retrieves the configured gone prefixes, falling back to defaults.
   *
   * @return array<string>
   */
  public function getPrefixes(): array {
    $config = $this->configFactory->get('konsolifin_misc.gone_paths');
    $prefixes = $config->get('prefixes');
    if ($prefixes === NULL) {
      return self::DEFAULT_PREFIXES;
    }
    return is_array($prefixes) ? $prefixes : [];
  }

  /**
   * Builds the standalone HTTP 410 HTML response.
   */
  protected function buildGoneResponse(): Response {
    return new Response(
      $this->getGoneHtml(),
      Response::HTTP_GONE,
      [
        'Content-Type' => 'text/html; charset=utf-8',
        'Cache-Control' => 'public, max-age=86400',
      ],
    );
  }

  /**
   * Returns the standalone HTML page styled according to KonsoliFIN branding.
   */
  public function getGoneHtml(): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="fi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>410 Gone – Sivua ei ole enää olemassa | KonsoliFIN</title>
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #0d0d0f radial-gradient(circle at 50% 25%, #181822 0%, #0d0d0f 75%);
      color: #e8e8ec;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .card {
      background: #151518;
      border: 1px solid #2a2a32;
      border-radius: 16px;
      padding: 2.5rem 2rem;
      max-width: 520px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
    }
    .logo {
      margin-bottom: 1.75rem;
    }
    .badge {
      display: inline-block;
      background: rgba(223, 85, 0, 0.15);
      color: #f06b1a;
      border: 1px solid rgba(223, 85, 0, 0.35);
      padding: 0.35rem 0.85rem;
      border-radius: 9999px;
      font-size: 0.8125rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 1.25rem;
    }
    h1 {
      font-size: 1.75rem;
      font-weight: 800;
      line-height: 1.25;
      color: #f8fafc;
      margin-bottom: 1rem;
      letter-spacing: -0.02em;
    }
    p {
      color: #9a9aaa;
      font-size: 1rem;
      line-height: 1.6;
      margin-bottom: 1.75rem;
    }
    .actions {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      align-items: center;
    }
    .btn-primary {
      display: inline-block;
      background: #df5500;
      color: #ffffff;
      text-decoration: none;
      padding: 0.85rem 1.75rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.9375rem;
      transition: background-color 0.15s ease, transform 0.15s ease;
      box-shadow: 0 4px 14px rgba(223, 85, 0, 0.35);
    }
    .btn-primary:hover {
      background: #f06b1a;
      transform: translateY(-1px);
    }
    .links {
      display: flex;
      gap: 1.25rem;
      margin-top: 1rem;
      font-size: 0.875rem;
    }
    .links a {
      color: #9a9aaa;
      text-decoration: none;
      transition: color 0.15s ease;
    }
    .links a:hover {
      color: #e8e8ec;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40" fill="none" width="180" height="36" aria-label="KonsoliFIN">
        <text x="0" y="30" font-family="system-ui, sans-serif" font-size="28" font-weight="800" fill="#e8e8ec" letter-spacing="-0.02em">
          Konsoli<tspan fill="#df5500">FIN</tspan>
        </text>
      </svg>
    </div>
    <div class="badge">410 Gone</div>
    <h1>Sivua ei ole enää olemassa</h1>
    <p>Etsimäsi sivu tai sisältö on poistettu pysyvästi käytöstä eikä se ole enää saatavilla sivustollamme.</p>
    <div class="actions">
      <a href="/" class="btn-primary">Palaa etusivulle</a>
      <div class="links">
        <a href="/uutiset">Uutiset</a>
        <a href="/peliarvostelut">Arvostelut</a>
        <a href="https://forum.konsolifin.net">Foorumi</a>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
  }

}
