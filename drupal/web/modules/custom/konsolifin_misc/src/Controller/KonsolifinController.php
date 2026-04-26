<?php

namespace Drupal\konsolifin_misc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for all KonsoliFIN custom pages.
 *
 * D7 equivalent: the page-callback functions in konsolifin.module
 *   - konsolifin_edit_my_profile()   → editMyProfile()
 *   - konsolifin_list_games()        → gamesList()
 *   - konsolifin_list_staff()        → staffList()
 *   - konsolifin_video_content()     → videoContent()
 *   - konsolifin_review_summaries()  → reviewSummaries()
 *   - konsolifin_content_rss()       → rssFeed() / rssFeedForum()
 *   - konsolifin_test_links()        → testLinks()
 *   - konsolifin_legacy_path_redirect()  → legacyRedirect()
 *   - konsolifin_legacy_forum_path()     → legacyForumRedirect()
 *   - konsolifin_reset_password_redirect() → handled by RouteSubscriber
 *   - _konsolifin_not_allowed_page() → accessDenied()
 *   - _konsolifin_not_found_page()   → notFound()
 *
 * Key D7 → D11 API changes applied throughout:
 *   - global $user              → $this->currentUser()
 *   - user_load($uid)           → \Drupal\user\Entity\User::load($uid)
 *   - node_load($nid)           → \Drupal\node\Entity\Node::load($nid)
 *   - taxonomy_term_load($tid)  → \Drupal\taxonomy\Entity\Term::load($tid)
 *   - taxonomy_vocabulary_machine_name_load() → entityTypeManager storage
 *   - db_select()               → \Drupal::database()->select()
 *   - drupal_goto()             → new RedirectResponse(Url::…->toString())
 *   - header('Location: …')     → new RedirectResponse(…)
 *   - drupal_get_path_alias()   → \Drupal::service('path_alias.manager')
 *   - pager_find_page() / pager_default_initialize() → PagerManager service
 *   - theme('pager')            → ['#type' => 'pager']
 *   - field_view_field()        → $entity->field_name->view()
 *   - node_view($node, $mode)   → \Drupal::entityTypeManager()→getViewBuilder()
 */
class KonsolifinController extends ControllerBase {

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->pagerManager = $container->get('pager.manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  // ---------------------------------------------------------------------------
  // editMyProfile()
  //
  // D7: konsolifin_edit_my_profile()
  //   global $user; drupal_goto('user/'.$user->uid.'/edit');
  //
  // D11: Build a Url from the route 'entity.user.edit_form' and return a
  //   RedirectResponse. No need for global $user — use currentUser().
  // ---------------------------------------------------------------------------

  /**
   * Redirects the current user to their own profile edit page.
   */
  public function editMyProfile(): RedirectResponse {
    $uid = $this->currentUser()->id();
    $url = Url::fromRoute('entity.user.edit_form', ['user' => $uid]);
    return new RedirectResponse($url->toString());
  }

  // ---------------------------------------------------------------------------
  // gamesList()
  //
  // D7: konsolifin_list_games()
  //
  // Changes:
  //   - $_GET access → $request->query->get()
  //   - taxonomy_vocabulary_machine_name_load() → entityTypeManager
  //   - db_select() on taxonomy_term_data → entityTypeManager term storage
  //     with a name-condition query (using the Entity Query API)
  //   - pager_find_page() / pager_default_initialize() → PagerManager service
  //   - header('Location:…') for single result → RedirectResponse
  //   - drupal_get_path_alias() → path_alias.manager service
  //   - theme('pager') → ['#type' => 'pager']
  //   - The inline search form is now referenced by form_builder via
  //     \Drupal::formBuilder()->getForm(). The actual form class is defined
  //     separately in src/Form/GamesSearchForm.php (see Phase 5 task).
  // ---------------------------------------------------------------------------

  /**
   * Games listing page (/pelit).
   */
  public function gamesList(): array {
    $request = $this->requestStack->getCurrentRequest();
    $search_term = $request->query->get('gamename', '');
    $first_letter = $request->query->get('f', '');

    // Build the alphabet filter links.
    $start_letter = '<h3>Listaa tai etsi pelejä</h3><p><b>Alkukirjain:</b> <a href="?">#</a> ';
    foreach (range('a', 'z') as $l) {
      $start_letter .= '<a href="?f=' . $l . '">' . $l . '</a> ';
    }
    $start_letter .= '</p>';

    $output['list_by_first_letter'] = ['#markup' => $start_letter];

    // Query taxonomy terms from the 'peli' vocabulary.
    // D7 used db_select('taxonomy_term_data') directly.  In D11 we use the
    // Entity Query API which is storage-backend agnostic.
    $storage = $this->entityTypeManager()->getStorage('taxonomy_vocabulary');
    $vocabs = $storage->loadByProperties(['vid' => 'peli']);
    if (empty($vocabs)) {
      $output['error'] = ['#markup' => '<p>Sanastoa "peli" ei löydy.</p>'];
      return $output;
    }

    $term_query = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'peli');

    if ($first_letter !== '') {
      $term_query->condition('name', $first_letter . '%', 'STARTS_WITH');
    }
    elseif ($search_term !== '') {
      $term_query->condition('name', $search_term, 'CONTAINS');
    }

    $tids = $term_query->execute();

    // Inline the search form (form class to be created in Phase 5).
    // $output['search_form'] = \Drupal::formBuilder()->getForm(
    //   'Drupal\konsolifin_misc\Form\GamesSearchForm'
    // );

    $games_per_page = 20;
    $total = count($tids);

    // Single match → redirect straight to the term page.
    if ($total === 1) {
      $tid = reset($tids);
      $url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $tid]);
      return new RedirectResponse($url->toString());
    }

    // Initialise pager.
    $pager = $this->pagerManager->createPager($total, $games_per_page);
    $page  = $pager->getCurrentPage();

    $output['page_header'] = ['#markup' => '<h2>' . $this->t('Game list') . '</h2>'];
    $output['list_start']  = ['#markup' => '<div class="row"><div class="col-lg-10 col-lg-offset-1">'];

    if ($total === 0) {
      $output['empty'] = ['#markup' => '<p>Pelejä ei löytynyt!</p>'];
    }
    else {
      $paged_tids = array_slice(array_values($tids), $page * $games_per_page, $games_per_page);
      $terms = $this->entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadMultiple($paged_tids);

      $alias_manager = \Drupal::service('path_alias.manager');

      foreach ($terms as $k => $term) {
        $alias = $alias_manager->getAliasByPath('/taxonomy/term/' . $term->id());

        $output['term_' . $k] = [
          '#type'   => 'container',
          '#attributes' => ['class' => ['row']],
          'image_col' => [
            '#markup' => '<div class="col-xs-1"><a href="' . $alias . '">',
          ],
          'image' => $term->get('field_nostokuva')->view([
            'label'    => 'hidden',
            'settings' => ['image_style' => 'uutisvirta'],
          ]),
          'image_col_end' => ['#markup' => '</a></div>'],
          'title_col' => [
            '#markup' => '<div class="col-xs-11"><h3><a href="' . $alias . '">'
              . $term->label() . '</a></h3></div>',
          ],
        ];
      }
    }

    $output['list_end'] = ['#markup' => '</div></div>'];
    $output['pager']    = ['#type' => 'pager'];

    return $output;
  }

  // ---------------------------------------------------------------------------
  // staffList()
  //
  // D7: konsolifin_list_staff()
  //
  // Changes:
  //   - user_role_load_by_name() → user_role_load_by_name() still exists but
  //     the preferred D11 way is Role storage: loadByProperties(['label' => …])
  //   - db_select('users_roles') → entityTypeManager User storage + role query
  //   - user_load($uid) → User::load($uid)
  //   - field_view_field('user', …) → $user->field_name->view()
  // ---------------------------------------------------------------------------

  /**
   * Staff listing page (/toimitus).
   */
  public function staffList(): array {
    $output = [];

    $role_labels = [
      'Board'             => 'johtoryhmä',
      'Editorial Staff'   => 'toimitus',
      'Community Writers' => 'yhteisosisallontuottaja',
    ];

    // Default profile picture source (admin user uid=1).
    // NOTE: In D11 uid=1 may not be a reliable fallback; adapt as needed.
    $default_user = \Drupal\user\Entity\User::load(1);

    $shown_people = [];
    $alias_manager = \Drupal::service('path_alias.manager');

    foreach ($role_labels as $group_label => $role_machine_name) {
      // Load UIDs that have this role.
      $uids = $this->entityTypeManager()
        ->getStorage('user')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('roles', $role_machine_name)
        ->execute();

      if (empty($uids)) {
        continue;
      }

      $output[$group_label . '_block'] = [
        '#type'       => 'fieldset',
        '#title'      => $this->t($group_label),
        '#attributes' => ['class' => ['bg-info', 'text-info']],
      ];

      foreach ($uids as $uid) {
        if (isset($shown_people[$uid])) {
          continue;
        }
        $shown_people[$uid] = TRUE;

        /** @var \Drupal\user\Entity\User $profile */
        $profile = \Drupal\user\Entity\User::load($uid);
        if (!$profile) {
          continue;
        }

        $url  = $alias_manager->getAliasByPath('/user/' . $uid);
        $name = (!$profile->get('field_oikea_nimi')->isEmpty())
          ? $profile->get('field_oikea_nimi')->value
          : $profile->getAccountName();

        // Profile picture: use user's own image or fall back to default user.
        $has_pic = !$profile->get('field_nostokuva')->isEmpty();
        $pic_entity = $has_pic ? $profile : $default_user;
        $pic = $pic_entity->get('field_nostokuva')->view([
          'label'    => 'hidden',
          'settings' => ['image_style' => 'uutisvirta'],
        ]);

        $desc = $profile->get('field_esittely')->view(['label' => 'hidden']);

        $output[$group_label . '_block'][] = [
          '#markup' =>
            '<div class="row">'
            . '<div class="col-xs-3"><a href="' . $url . '">',
          'pic' => $pic,
          'pic_end' => ['#markup' => '</a></div>'],
          'desc_col' => [
            '#markup' => '<div class="col-xs-9">'
              . '<a href="' . $url . '"><h3>' . $name . '</h3></a>',
          ],
          'desc' => $desc,
          'desc_end' => ['#markup' => '</div></div>'],
        ];
      }
    }

    return $output;
  }

  // ---------------------------------------------------------------------------
  // videoContent()
  //
  // D7: konsolifin_video_content()
  //
  // Changes:
  //   - pager_find_page() / pager_default_initialize() → PagerManager service
  //   - db_select() → entityTypeManager node storage query
  //   - node_view($node, 'highlight') → entity view builder
  //   - theme('pager') → ['#type' => 'pager']
  // ---------------------------------------------------------------------------

  /**
   * Video content listing page (/videot).
   */
  public function videoContent(): array {
    $videos_per_page = 12;

    $count_query = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'video')
      ->count();
    $count = (int) $count_query->execute();

    $pager = $this->pagerManager->createPager($count, $videos_per_page);
    $page  = $pager->getCurrentPage();

    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'video')
      ->sort('created', 'DESC')
      ->range($page * $videos_per_page, $videos_per_page)
      ->execute();

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    $fields = ['div_open' => ['#markup' => '<div class="row">']];

    foreach ($nodes as $node) {
      $nid = $node->id();
      $fields['open_node_' . $nid] = [
        '#markup' => '<div class="video-node-teasers col-md-6 col-xs-12" '
          . 'style="height: 0px; padding-bottom: 33%;">',
      ];
      $fields['node_' . $nid]  = $view_builder->view($node, 'highlight');
      $fields['close_node_' . $nid] = ['#markup' => '</div>'];
    }

    $fields['div_close'] = ['#markup' => '</div>'];
    $fields['pager']     = ['#type'   => 'pager'];

    return $fields;
  }

  // ---------------------------------------------------------------------------
  // reviewSummaries()
  //
  // D7: konsolifin_review_summaries()
  //
  // Changes:
  //   - Raw PHP/HTML output → proper render array / Response
  //   - node_load() → Node::load()
  //   - taxonomy_term_load() → Term::load()
  //   - format_date() → \Drupal::service('date.formatter')->format()
  //   - The original function used inline PHP/HTML which is not D11 style.
  //     Here we build a render array and let Drupal theme it.
  // ---------------------------------------------------------------------------

  /**
   * Review summaries page (/review_summaries).
   */
  public function reviewSummaries(): array {
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'arvostelu')
      ->sort('created', 'DESC')
      ->range(0, 25)
      ->execute();

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    $date_formatter = \Drupal::service('date.formatter');
    $output = [];

    foreach ($nodes as $node) {
      // Determine game name.
      if (!$node->get('field_pelin_nimi')->isEmpty()
          && $node->get('field_pelin_nimi')->value !== '') {
        $gamename = $node->get('field_pelin_nimi')->value;
      }
      else {
        $game_tid = $node->get('field_pelit')->target_id;
        $game = \Drupal\taxonomy\Entity\Term::load($game_tid);
        $gamename = $game ? $game->label() : '(tuntematon peli)';
      }

      $platform_tid = $node->get('field_arvosteltu_versio')->target_id;
      $platform = \Drupal\taxonomy\Entity\Term::load($platform_tid);
      $platform_name = $platform ? $platform->label() : '';

      // Only show items that have an English summary.
      if ($node->get('field_summary_in_english')->isEmpty()) {
        continue;
      }

      $score_raw  = $node->get('field_score')->review_score ?? 0;
      $score      = ($score_raw / 25) + 1;
      $created    = $date_formatter->format($node->getCreatedTime(), 'custom', 'Y-m-d H:i');
      $url        = 'https://www.konsolifin.net/node/' . $node->id();
      $summary    = $node->get('field_summary_in_english')->value;

      $output['node_' . $node->id()] = [
        '#markup' =>
          '<h2>' . htmlspecialchars($gamename) . ' - '
          . htmlspecialchars($platform_name) . ' - ' . $score . '/5</h2>'
          . '<p>' . $created . '</p>'
          . '<p><a href="' . $url . '">' . $url . '</a></p>'
          . '<p>' . $summary . '</p>',
      ];
    }

    return $output;
  }

  // ---------------------------------------------------------------------------
  // rssFeed() / rssFeedForum()
  //
  // D7: konsolifin_content_rss($arg = 'rss')
  //
  // Changes:
  //   - header("content-type:…") + print → return a Symfony Response with
  //     the correct Content-Type header.
  //   - global $base_url → \Drupal::request()->getSchemeAndHttpHost()
  //   - node_load(), field access → Entity API
  //   - drupal_get_path_alias() → path_alias.manager
  //   - image_style_url() → ImageStyle::load()->buildUrl()
  // ---------------------------------------------------------------------------

  /**
   * RSS feed (/feed/feed.php).
   */
  public function rssFeed(): Response {
    return $this->buildRssResponse('rss');
  }

  /**
   * RSS feed for forum (/feed/forforum.php).
   */
  public function rssFeedForum(): Response {
    return $this->buildRssResponse('forum');
  }

  /**
   * Builds the RSS XML response.
   */
  protected function buildRssResponse(string $arg): Response {
    $base_url     = \Drupal::request()->getSchemeAndHttpHost();
    $alias_manager = \Drupal::service('path_alias.manager');

    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('promote', 1)
      ->sort('created', 'DESC')
      ->range(0, 25)
      ->execute();

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($nids);

    $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
      . '<rss version="2.0" xml:base="' . $base_url . '/uusimmat"'
      . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
      . ' xmlns:atom="http://www.w3.org/2005/Atom"'
      . ' xmlns:content="http://purl.org/rss/1.0/modules/content/">'
      . "\n<channel>\n"
      . '<title>KonsoliFIN.netin uusin sisältö</title>' . "\n"
      . '<ttl>5</ttl>' . "\n"
      . '<link>' . $base_url . "</link>\n"
      . '<description>KonsoliFINin uusin julkaistu sisältö</description>' . "\n"
      . '<language>fi</language>' . "\n"
      . '<atom:link href="' . $base_url . '/feed/feed.php" rel="self" type="application/rss+xml" />' . "\n";

    foreach ($nodes as $node) {
      $title = htmlspecialchars($node->label());

      // Append game name for reviews.
      if ($node->bundle() === 'arvostelu' && !$node->get('field_pelit')->isEmpty()) {
        if (!$node->get('field_pelin_nimi')->isEmpty()
            && $node->get('field_pelin_nimi')->value !== '') {
          $gamename = $node->get('field_pelin_nimi')->value;
        }
        else {
          $game_tid = $node->get('field_pelit')->target_id;
          $game = \Drupal\taxonomy\Entity\Term::load($game_tid);
          $gamename = $game ? $game->label() : '';
        }
        $title .= htmlspecialchars(' - Arvostelussa ' . $gamename);
      }

      // Prepend series name.
      if (!$node->get('field_sarja')->isEmpty()) {
        $series = \Drupal\taxonomy\Entity\Term::load($node->get('field_sarja')->target_id);
        if ($series) {
          $title = htmlspecialchars($series->label()) . ': ' . $title;
        }
      }

      $body_field = $node->get('body');
      if (!$body_field->isEmpty()) {
        if ($arg === 'forum') {
          $body_text = $body_field->processed;
        }
        else {
          [$first] = explode("\r", strip_tags($body_field->value), 2);
          $body_text = $first;
        }
      }
      else {
        $body_text = '';
      }

      // Featured image for description.
      $img_markup = '';
      if (!$node->get('field_nostokuva')->isEmpty()) {
        $file = $node->get('field_nostokuva')->entity;
        if ($file) {
          $style = \Drupal\image\Entity\ImageStyle::load('banneri');
          $img_url = $style
            ? $style->buildUrl($file->getFileUri())
            : \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          $img_markup = '<img src="' . $img_url . '"><br />';
        }
      }
      $description = $img_markup . $body_text;

      $alias   = $alias_manager->getAliasByPath('/node/' . $node->id());
      $link    = $base_url . $alias . '?utm_medium=rss';
      $author  = $node->getOwner()->getAccountName();
      $pubdate = date('r', $node->getCreatedTime());

      $xml .= "\t<item>\n"
        . "\t\t<title>" . $title . "</title>\n"
        . "\t\t<link>" . $link . "</link>\n"
        . "\t\t<description>" . htmlspecialchars($description) . "</description>\n"
        . "\t\t<pubDate>" . $pubdate . "</pubDate>\n"
        . "\t\t<dc:creator>" . htmlspecialchars($author) . "</dc:creator>\n"
        . "\t\t<guid isPermaLink=\"false\">" . $node->id() . " at http://www.konsolifin.net</guid>\n"
        . "\t\t<comments>" . $base_url . $alias . "?utm_medium=rss#comments</comments>\n"
        . "\t</item>\n";
    }

    $xml .= "</channel>\n</rss>";

    return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
  }

  // ---------------------------------------------------------------------------
  // testLinks()
  //
  // D7: konsolifin_test_links()
  //
  // Changes:
  //   - db_select('node_type') → entityTypeManager node_type storage
  //   - db_select('node') → entity query
  //   - node_view($node, 'teaser') → view builder
  // ---------------------------------------------------------------------------

  /**
   * Test page listing one teaser per content type (/kfintest/linkit).
   */
  public function testLinks(): array {
    $output = [];

    /** @var \Drupal\node\NodeTypeInterface[] $node_types */
    $node_types = $this->entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    foreach ($node_types as $type => $_type_entity) {
      $nids = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $type)
        ->condition('status', 1)
        ->sort('changed', 'DESC')
        ->range(0, 1)
        ->execute();

      if (!empty($nids)) {
        $node = $this->entityTypeManager()
          ->getStorage('node')
          ->load(reset($nids));
        if ($node) {
          $output['node_' . $node->id()] = $view_builder->view($node, 'teaser');
        }
      }
    }

    return $output;
  }

  // ---------------------------------------------------------------------------
  // Legacy redirects
  //
  // D7 used raw header() calls. D11 uses RedirectResponse.
  // ---------------------------------------------------------------------------

  /**
   * 301 redirect to site root for old legacy paths.
   *
   * D7: konsolifin_legacy_path_redirect() — header("HTTP/1.1 301 …"); header("Location: /");
   */
  public function legacyRedirect(): RedirectResponse {
    return new RedirectResponse('/', 301);
  }

  /**
   * 301 redirect to the external forum.
   *
   * D7: konsolifin_legacy_forum_path() — header("HTTP/1.1 301 …"); header("Location: //forum.konsolifin.net");
   */
  public function legacyForumRedirect(): RedirectResponse {
    return new RedirectResponse('//forum.konsolifin.net', 301);
  }

  // ---------------------------------------------------------------------------
  // resetPasswordRedirect()
  //
  // D7: konsolifin_reset_password_redirect()
  //   header("HTTP/1.1 410 Gone");
  //   header("Location: https://forum.konsolifin.net/lost-password/");
  //
  // Called by RouteSubscriber which replaces the 'user.pass' route controller.
  // HTTP 410 (Gone) is preserved so search engines know the endpoint is
  // permanently removed from this site.
  // ---------------------------------------------------------------------------

  /**
   * Redirects the password-reset path to the external forum (HTTP 410).
   */
  public function resetPasswordRedirect(): Response {
    // A 410 Gone with a Location header is non-standard but matches the D7
    // behaviour.  Alternatively, return a 301 or a plain 410 without redirect.
    $response = new Response('', 410);
    $response->headers->set('Location', 'https://forum.konsolifin.net/lost-password/');
    return $response;
  }

  // ---------------------------------------------------------------------------
  // accessDenied() / notFound()
  //
  // D7: _konsolifin_not_allowed_page() / _konsolifin_not_found_page()
  //
  // Changes:
  //   - header("HTTP/1.1 403/404 …") → set via response (D11 handles the HTTP
  //     status code through the exception subscriber when you configure these
  //     routes as the 403/404 handlers in site settings, or you can return a
  //     Response with the status code directly).
  //   - global $user → currentUser()
  //   - user_is_logged_in() → currentUser()->isAuthenticated()
  //   - request_uri() → $request->getRequestUri()
  //   - drupal_get_form("user_login") → the login form is now at the route
  //     'user.login'; link there instead of embedding the form.
  //   - menu_build_tree() / menu_tree_output() → MenuLinkTree service
  //     (simplified here for brevity; a full nav tree can be added later).
  // ---------------------------------------------------------------------------

  /**
   * Custom 403 page (/403_not_allowed).
   */
  public function accessDenied(): array {
    $request = $this->requestStack->getCurrentRequest();
    $account = $this->currentUser();
    $output  = [];

    if ($account->isAuthenticated()) {
      $roles_raw = $account->getRoles();
      $output[] = ['#markup' => '<h2>Ei käyttöoikeuksia</h2>'];
      $output[] = ['#markup' => '<p>Moi, ' . $account->getAccountName() . '</p>'];
      $output[] = ['#markup' =>
        '<p>olet yrittänyt toimintoa, johon sinulla ei tunnu olevan oikeuksia. Mikäli '
        . 'epäilet tämän johtuvan teknisestä virheestä, ota yhteyttä '
        . '<a href="mailto:toimitus@konsolifin.net">KonsoliFINin toimitukseen</a>. '
        . 'Kopioi seuraavat tiedot sähköpostiin.</p>',
      ];
      $output[] = ['#markup' =>
        '<pre>'
        . 'Käyttäjätunnus : ' . $account->getAccountName() . '<br />'
        . 'UID            : ' . $account->id() . '<br />'
        . 'Käyttäjäroolit : ' . implode(', ', $roles_raw) . '<br />'
        . 'Sivun polku    : ' . htmlspecialchars($request->getRequestUri()) . '<br />'
        . '</pre>',
      ];
    }
    else {
      $output[] = ['#markup' => '<h2>Kirjaudu sisään!</h2>'];
      $output[] = ['#markup' =>
        '<p>Moi,</p><p>olet yrittänyt toimintoa, jota varten täytyy olla kirjautuneena sisään. '
        . 'Jatka syöttämällä käyttäjätunnus ja salasana.</p>'
        . '<p>Mikäli et ole vielä rekisteröitynyt KonsoliFIN-sivustolle, '
        . '<a href="//forum.konsolifin.net/login/">rekisteröidy foorumillamme</a>.</p>',
      ];
      // In D11 the login form lives at its own route; link to it.
      $login_url = Url::fromRoute('user.login')->toString();
      $output[] = ['#markup' => '<p><a href="' . $login_url . '">Kirjaudu sisään</a></p>'];
    }

    return $output;
  }

  /**
   * Custom 404 page (/404_not_found).
   */
  public function notFound(): array {
    $request = $this->requestStack->getCurrentRequest();
    $path    = htmlspecialchars($request->getRequestUri());

    $output[] = ['#markup' => '<h2>404-sivu</h2>'];
    $output[] = ['#markup' => "<p>Sivua, jota etsit ($path), ei löydy.</p>"];
    $output[] = ['#markup' => '<p>Haluaisitko sen sijaan vierailla jollain seuraavista:</p>'];
    $output[] = ['#markup' => '<p><b><a href="/">Etusivu</a></b></p>'];
    // A full nav tree could be rendered here with the menu.link_tree service.

    return $output;
  }

}
