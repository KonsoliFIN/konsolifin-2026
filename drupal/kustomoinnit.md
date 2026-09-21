# KonsoliFIN-kohtaiset lisäominaisuudet

Tähän on dokumentoitu kaikki erityisesti KonsoliFINiä varten luodut custom-modulit ja teemat sekä mitä ominaisuuksia ja teknisiä ratkaisuja ne toteuttavat.

---

## Modulit

1. [Date-ish (`date_ish`)](#date-ish-date_ish)
2. [KonsoliFIN Ads (`konsolifin_ads`)](#konsolifin-ads-konsolifin_ads)
3. [KonsoliFIN Misc (`konsolifin_misc`)](#konsolifin-misc-konsolifin_misc)
4. [KonsoliFIN Review Score (`konsolifin_review_score`)](#konsolifin-review-score-konsolifin_review_score)
5. [KonsoliFIN SoMe Links (`konsolifin_some_links`)](#konsolifin-some-links-konsolifin_some_links)
6. [KonsoliFIN Term Page (`konsolifin_term_page`)](#konsolifin-term-page-konsolifin_term_page)
7. [Migrate KonsoliFIN (`migrate_konsolifin`)](#migrate-konsolifin-migrate_konsolifin)
8. [Migrate KonsoliFIN Testidata (`migrate_konsolifin_testdata`)](#migrate-konsolifin-testidata-migrate_konsolifin_testdata)
9. [KonsoliFIN Workflows (`konsolifin_workflows`)](#konsolifin-workflows-konsolifin_workflows)

---

### Date-ish (`date_ish`)

**Sijainti:** `web/modules/custom/date_ish`  
**Tarkoitus:** Toteuttaa epätarkan tai sumean ajankohdan valinnan ja tallennuksen. Mahdollistaa esimerkiksi tulevien pelien julkaisuaikojen määrittämisen tarkan päivämäärän sijaan kuukauden, kvartaalin, vuosipuoliskon tai pelkän vuoden tarkkuudella.

#### Ominaisuudet ja arkkitehtuuri:
- **Kenttätyyppi (`DateIshItem`, id: `date_ish`):**
  - Tallentaa tietokantaan kaksi saraketta:
    - `accuracy_level` (`varchar(16)`): Tarkkuustaso (`exact`, `month`, `quarter`, `year_half`, `year`).
    - `stored_date` (`date` / `varchar(10)`): ISO 8601 -muotoinen päivämäärä (`YYYY-MM-DD`).
- **Aikavälien normalisointi (`DateIshHelper`):**
  - Jotta epätarkat päivämäärät voidaan järjestää tietokantakyselyissä loogisesti ja vertailla suoraan (esim. `stored_date >= today`), epätarkat ajankohdat tallennetaan aina kyseisen ajanjakson **viimeiselle päivälle**:
    - `exact`: syötetty tarkka päivämäärä (esim. `2026-05-15`).
    - `month`: kuukauden viimeinen päivä (esim. toukokuu 2026 &rarr; `2026-05-31`).
    - `quarter`: kvartaalin viimeinen päivä (Q1 &rarr; `03-31`, Q2 &rarr; `06-30`, Q3 &rarr; `09-30`, Q4 &rarr; `12-31`).
    - `year_half`: puolivuotiskauden viimeinen päivä (H1 &rarr; `06-30`, H2 &rarr; `12-31`).
    - `year`: vuoden viimeinen päivä (esim. 2026 &rarr; `2026-12-31`).
- **Lomakewidget (`DateIshWidget`, id: `date_ish_default`):**
  - Tarjoaa pudotusvalikon tarkkuustasolle sekä eri syöttökentät kullekin tasolle (päivämäärävalitsin, kuukausi-select, kvartaali-select, vuosipuolisko-select ja vuosi-numerokenttä).
  - JavaScript-kirjasto (`js/date-ish-widget.js`) vaihtaa näkyvissä olevat syöttökentät dynaamisesti valitun tarkkuuden mukaan.
  - Sisältää kattavan syötteiden validoinnin (`validateElement`) ja muuntamisen tallennusmuotoon (`massageFormValues`).
- **Kenttäformatteri (`DateIshFormatter`, id: `date_ish_default`):**
  - Muuntaa tallennetun päivämäärän ja tarkkuustason käyttäjälle esitettäväksi ihmisluettavaksi tekstiksi hyödyntäen Drupalin käännösjärjestelmää (`TranslatableMarkup`):
    - Tarkka: esim. *15. toukokuuta 2026*
    - Kuukausi: esim. *toukokuu 2026*
    - Kvartaali: esim. *Q2 2026*
    - Vuosipuolisko: esim. *H1 2026*
    - Vuosi: esim. *2026*
- **Form API -apuluokka (`DateIshElement`):**
  - Tarjoaa staattiset metodit `DateIshElement::build()` ja `DateIshElement::extractValue()`, joiden avulla date_ish-kenttäkokonaisuus voidaan lisätä mihin tahansa Drupalin mukautettuun Form API -lomakkeeseen (kuten `AddJulkaisuModalForm`) ilman varsinaista kenttäinstanssia.
- **Yksikkötestit:**
  - Kattaa PHPUnit-testit: `DateIshHelperTest`, `DateIshItemTest`, `DateIshFormatterTest` ja `DateIshWidgetValidationTest`.

---

### KonsoliFIN Ads (`konsolifin_ads`)

**Sijainti:** `web/modules/custom/konsolifin_ads`  
**Tarkoitus:** Vastaa sivuston mainosratkaisuista, suostumustenhallinnasta (CMP) sekä dynaamisesta mainospaikkojen sijoittelusta artikkeleihin, listausnäkymiin ja teeman alueille.

#### Ominaisuudet ja arkkitehtuuri:
- **Mainosverkko ja suostumustenhallinta:**
  - `konsolifin_ads_page_attachments` liittää sivujen `<head>`-osioon InMobi Choice CMP -suostumuskirjaston (`js/inmobi-cmp.js`) ja ulkoisen Livewrapped Header Bidding -skriptin (`https://lwadm.com/lw/pbjs?pid=...`).
- **Automaattinen mainosten upotus (`Render\AdInjector`):**
  - **Artikkelit (`postRenderNodeBody`):** Etsii artikkelin leipätekstin kolmannen kappaleen (`</p>`) ja injektoi välimainoksen (`content`) sen alapuolelle. Mikäli kappaleita on vähemmän, mainos lisätään tekstin loppuun.
  - **Listausnäkymät (`postRenderView`):** Injektoi mainoksen listausnäkymissä (`uutiset`, `artikkelit_blogit_ja_laitearviot`, `frontpage`, `peliarvostelut`, `podcastit`) joka 10. rivin jälkeen (kaavalla `(rivi + 1) % 10 === 6`). Etsii täsmälliset HTML `<div>`-sulkeumat sisäkkäisyydet huomioiden.
- **Ankkurimainos:**
  - `konsolifin_ads_page_bottom` lisää sivun alalaitaan kelluvan mobiili-/ankkurimainoksen (`anchor`).
- **Twig-laajennus (`TwigExtension\AdExtension`):**
  - Tarjoaa Twig-funktion `konsolifin_ad(base_id)`.
  - Tukee mainospaikkatunnisteita: `top` (sivun yläbanneri), `content` (sisältömainokset) ja `anchor` (alalaidan ankkurimainos).
  - Generoi yksilölliset ID-tunnisteet (`_1`, `_2`, jne.) samalla sivulla esiintyville useille mainoksille.
  - **Kehitysympäristötuki:** Mikäli Drupalin asetuksissa on `Settings::get('dev_environment', FALSE)`, oikeiden mainoskutsujen sijaan renderöidään selkeät visualisoidut placeholder-laatikot.
  - **Kampanjakohtainen logiikka:** Mahdollistaa määräaikaisten erikoiskampanjoiden (kuten kesän 2026 Rockstar Games / GTA Online -kampanja) ajamisen suoraan koodista responsiivisilla kuvituskuvilla (`<picture>`, WebP/JPG eri resoluutioilla) ja Matomo-klikkausseurannalla (`data-track-content`).
- **Responsiivinen teemapohja (`templates/konsolifin-ad.html.twig`):**
  - Sisältää erilliset säiliöt työpöytä- ja mobiilimainoksille (`konsolifin-ad-desktop` ja `konsolifin-ad-mobile`).
  - Skripti tarkistaa selaimen leveyden (raja 1000px) ja kutsuu Livewrapped-latausta vain aktiiviselle koon mukaiselle mainospaikalle.
- **Käyttö teemoissa:**
  - Ylälaidan pääbanneri sijoitetaan teeman TWIG-templateen manuaalisesti:
    ```twig
    {{ konsolifin_ad('top') }}
    ```

---

### KonsoliFIN Misc (`konsolifin_misc`)

**Sijainti:** `web/modules/custom/konsolifin_misc`  
**Tarkoitus:** Kokoelma KonsoliFINin erikoissivuja, RSS- ja podcast-syötteitä, reittimuokkauksia sekä sisältökäsittelyn apufunktioita ja hookeja, jotka korvaavat vanhan Drupal 7 -sivuston `konsolifin.module`-koodin.

#### 1. Erikoissivut ja reitit (`src/Controller/KonsolifinController.php`):

| Sivu | Reitti / URL | Kuvaus ja toteutus |
|------|--------------|-------------------|
| **Toimitus** | `/toimitus` | Listaa toimituskunnan jäsenet ryhmiteltynä rooleittain: *Johtoryhmä* (`johtoryhma`), *Toimitus* (`toimitus`) ja *Yhteisösisällöntuottajat* (`yhteisosisallontuottaja`). Näyttää nimen, profiilikuvan ja esittelyn. |
| **Arvosteluyhteenvedot** | `/review_summaries` | Englanninkielinen katsaus tuoreimmista peliarvosteluista (`peliarvostelu`), joissa on `field_summary_in_english`. Muuntaa KonsoliFINin 0–400 arvosanan 0–5 tähden asteikolle Metacritic- ja OpenCritic-aggregaattoreille. |
| **Yleinen RSS** | `/feed/feed.php` | Sivuston pääasiallinen RSS 2.0 -syöte. Sisältää 25 tuoreinta nostettua uutista ja artikkelia; leipätekstistä otetaan vain ensimmäinen kappale. |
| **Foorumin RSS** | `/feed/forforum.php` | Foorumille suunnattu RSS 2.0 -syöte, joka sisältää artikkeleiden täyden leipätekstin. |
| **Podcast RSS** | `/podcast/podcast.rss` | Täysi Apple Podcasts / iTunes -yhteensopiva XML-syöte. Hakee MP3-mediatiedoston URL:n, tiedostokoon ja keston, kuvan (`field_hero`) sekä iTunes-spesifit metatiedot (`<itunes:duration>`, `<itunes:image>`, `<itunes:author>`). |
| **403 Kielletty** | `/403_not_allowed` | Kustomoitu pääsy estetty -sivu. Kirjautuneelle käyttäjälle näytetään virheilmoitus, käyttäjätunnus, UID ja roolit ylläpidolle raportointia varten; anonyymille ohjaus kirjautumiseen ja foorumin rekisteröintiin. |
| **404 Ei löydy** | `/404_not_found` | Kustomoitu sivua ei löydy -ilmoitus linkkeineen etusivulle ja navigointiin. |
| **Testilinkit** | `/kfintest/linkit` | Ylläpidon kehityssivu (vaatii `access administration pages` -oikeuden), joka hakee yhden tuoreimman solmun jokaisesta sisältötyypistä ja renderöi sen `teaser`-näkymässä teeman tyylien testausta varten. |
| **Oma profiili** | `editMyProfile()` | Apufunktio, joka ohjaa kirjautuneen käyttäjän suoraan omaan profiilinmuokkauslomakkeeseensa (`/user/{uid}/edit`). |

#### 2. Palvelut ja tapahtumatilaajat:
- **Salasanan palautuksen ohjaus (`RouteSubscriber`):**
  - Korvaa Drupalin sisäänrakennetun `/user/password`-reitin ja palauttaa HTTP 410 Gone -vastauksen, joka ohjaa käyttäjän ulkoisen foorumin salasananpalautukseen (`https://forum.konsolifin.net/lost-password/`).
- **Syötepalvelu (`RssFeedService`):**
  - Vastaa XML-syötteiden luonnista, polku-aliasten selvittämisestä, välimuistituksesta, UTM-seurantaparametrien lisäämisestä (`?utm_medium=rss`) sekä MP3-keston automaattisesta käsittelystä.
- **Toimituksen julkaisukalenteri (`EditorialCalendarService`, lohko: `TwoWeekCalendarBlock`):**
  - Renderöi toimitukselle 2 viikon dynaamisen julkaisukalenterin ja julkaisemattoman sisällön listausnäkymän (`templates/konsolifin-two-week-calendar.html.twig`).
  - **Työnkulkujen ajastukset:** Hakee `workflow_scheduled_transition`-entiteetit, joiden kohdetilana on julkaistu tila (`yleinen_julkaisuputki_julkaistu` tai `uutisputki_julkaistu`), ja sijoittaa sisällöt kalenteriin ajastusajankohdan mukaan. Tukee myös `publish_on`-kenttää työnkuluttomille sisällöille.
  - **Työnkulun tilan näyttäminen:** Julkaisemattoman sisällön taulukkolistauksessa näytetään solmun nykyinen työnkulun tila ihmisluettavana värikoodattuna laatikkomerkintänä (`.kf-workflow-badge`).
  - **Hylättyjen sisältöjen suodatus:** Kaikki tilassa *Hylätty* (`yleinen_julkaisuputki_hylatty` tai `uutisputki_hylatty`) olevat sisällöt jätetään kokonaan pois julkaisemattomien listauksesta.

#### 3. Sisältö- ja teemahookit:
- **`konsolifin_misc_preprocess_node`:**
  - Rikastaa artikkelinäkymän tekijätiedoilla: lisää kirjoittajan kuvan (`author_headshot`, kuvaformaatti `teaser_thumbnail`), esittelyn ensimmäisen kappaleen (`author_bio`) ja sosiaalisen median linkit (`author_social`).
- **`konsolifin_misc_preprocess_username`:**
  - Hakee käyttäjän todellisen nimen `realname`-moduulilta tai `getDisplayName()`-metodista ja estää Drupalia lyhentämästä nimeä (`truncated = FALSE`).
- **`konsolifin_misc_pathauto_alias_alter`:**
  - Räätälöi automaattisia URL-aliaksia: lisää osoitteeseen sarjan nimen (`field_sarja`), pelitaksonomian nimen (`field_pelit`) tai vapaamuotoisen pelin nimen (`field_pelin_nimi`).

---

### KonsoliFIN Review Score (`konsolifin_review_score`)

**Sijainti:** `web/modules/custom/konsolifin_review_score`  
**Tarkoitus:** Peliarvostelujen viiden tähden arvosanakenttä, syöttöwidget ja esteetön esitysmuotoilu.

#### Ominaisuudet ja arkkitehtuuri:
- **Kenttätyyppi (`ReviewScoreItem`, id: `review_score`):**
  - Tallentaa kokonaisluvun väliltä `0–400` (`smallint unsigned`).
  - Asteikko perustuu 400 pisteeseen:
    - 1 tähti = 80 pistettä
    - Puolikas tähti = 40 pistettä
    - 5 tähteä = 400 pistettä
  - Validointi varmistaa, että syötetty arvo on sallitulla välillä 0–400.
- **Interaktiivinen syöttöwidget (`StarWidget`, id: `star_widget`):**
  - Tarjoaa arvostelijalle visuaalisen tähtivalitsimen viidellä tähdellä.
  - JavaScript-ohjaus (`js/star-widget.js`) tukee hiirellä klikkaamista, raahaamista sekä kosketusnäyttöjä.
  - **Saavutettavuus (A11y):** Säiliö toimii WAI-ARIA -liukusäätimenä (`role="slider"`, `tabindex="0"`, `aria-valuemin="0"`, `aria-valuemax="400"`, `aria-valuenow="X"`). Arvosanaa voi säätää suoraan näppäimistön nuolinäppäimillä (vasen/alas vähentää, oikea/ylös kasvattaa 40 pistettä eli puolikkaan tähden kerrallaan).
  - Sisältää nollauspainikkeen (`×`), jolla arvosana voidaan tyhjentää kokonaan.
  - Widgetin leveys pikseleinä on määritettävissä kenttäasetuksista (oletus 200px).
- **Kenttäformatteri (`StarFormatter`, id: `star_formatter`):**
  - Renderöi arvosanan viitenä tähtenä hyödyntäen moduulin kultaisia (`star_gold.png`) ja harmaita pohjatähtiä (`star_dim.png`).
  - Tukee puolikkaita ja osittaisia tähtiä CSS `clip-path: inset(0 X% 0 0)` -leikkauksella.
  - Generoi saavutettavan tekstivastineen ruudunlukuohjelmille (esim. `aria-label="Rating: 4 out of 5 stars"`).
  - Formatterin leveys on säädettävissä näkymäasetuksissa pikseleinä (CSS-muuttuja `--review-score-width`).
- **Testaus:**
  - PHPUnit-yksikkötestit: `StarFormatterTest`, `StarFormatterFillPropertyTest`, `StarFormatterAriaPropertyTest`, `StarWidgetTest`, `StarWidgetAriaPropertyTest`, `ReviewScoreItemTest`, `ReviewScoreRangePropertyTest`.
  - JavaScript-testit (Jest): `tests/js/adjustScore.test.js` ja `tests/js/positionToScore.test.js`.

---

### KonsoliFIN SoMe Links (`konsolifin_some_links`)

**Sijainti:** `web/modules/custom/konsolifin_some_links`  
**Tarkoitus:** Hallitsee ja validoi kirjoittajien sosiaalisen median profiililinkkejä (`field_some_linkit`) käyttäjäprofiileissa sekä esittää ne tyylikkäästi brändi-ikonein varustettuina.

#### Ominaisuudet ja arkkitehtuuri:
- **Tuetut sosiaalisen median palvelut:**
  - **BlueSky** (`bsky.app/profile/...`)
  - **Instagram** (`instagram.com/...`)
  - **LinkedIn** (`linkedin.com/in/...`)
  - **Threads** (`threads.net/...`)
- **Automaattinen URL-korjaus ja validointi:**
  - `konsolifin_some_links_field_widget_single_element_form_alter`: Lisää lomakkeen validointiin callbackin, joka lisää syötettyyn osoitteeseen automaattisesti `https://`-etuliitteen, jos käyttäjä syöttää vain verkkotunnuksen tai polun ilman protokollaa.
  - `SoMeLinkConstraint` ja `SoMeLinkConstraintValidator`: Entity-tason validointisääntö käyttäjän `field_some_linkit`-kentälle. Tarkistaa säännöllisillä lausekkeilla, että syötetty URL osoittaa nimenomaan johonkin neljästä tuetusta palvelusta, ja antaa käyttäjälle selkeän suomenkielisen virheilmoituksen, jos linkki osoittaa muualle.
- **Formatteri (`SoMeLinkFormatter`, id: `konsolifin_some_link_formatter`):**
  - Korvaa linkin oletusmuotoilun.
  - Tunnistaa palvelun URL-osoitteesta säännöllisellä lausekkeella ja eristää käyttäjätunnuksen (esim. `@kayttajatunnus` tai BlueSky-handle).
  - Renderöi linkin tekstiksi palvelun virallisen brändi-ikonin (`images/bluesky.png`, `images/instagram.png`, `images/linkedin.png` tai `images/threads.png`) yhdistettynä käyttäjänimeen.
  - Liittää mukaan moduulin CSS-tyylit (`css/konsolifin_some_links.css`).

---

### KonsoliFIN Term Page (`konsolifin_term_page`)

**Sijainti:** `web/modules/custom/konsolifin_term_page`  
**Tarkoitus:** Räätälöi taksonomiatermisivuja peli- ja sarjasanastoille, integroi XenForo-foorumin keskusteluketjut, tarjoaa sisällöntuottajille julkaisujen pikalisäysdialogin sekä toteuttaa sivuston `/pelit`-keskussivun analytiikkakytkentöineen.

#### 1. Sanastokohtaiset käsittelijät (`VocabularyHandlerInterface`):

- **Peli-sanasto (`PeliHandler`, sanasto: `peli`):**
  - Hakee peliin liittyvät `julkaisu`-sisältötyypin solmut (`field_pelit`).
  - Ryhmittelee julkaisut tyypeittäin (`field_tyyppi`: *Ensijulkaisu*, *Early access*, *Remaster*, *Remake*, *DLC*, *Bundle*) ja esittää alustat sekä julkaisuajankohdan käyttäen `date_ish`-moduulin muotoilua.
  - Piilottaa `julkaisu`-solmut pelitermin tavallisesta artikkelilistauksesta (`hook_views_query_alter`), jotta ne eivät sekoitu uutisten ja arvostelujen sekaan.
  - **Pikalisäyslomake (`AddJulkaisuModalForm`):** Jos käyttäjällä on `create julkaisu content` -oikeus, pelisivulle renderöidään painike, joka avaa AJAX-modaalidialogin (`/konsolifin/term/{taxonomy_term}/add-julkaisu`). Lomakkeella voi nopeasti lisätä pelille uuden julkaisun (nimi, alustat, julkaisutyyppi ja `DateIshElement`-päivämäärä) poistumatta sivulta.
- **Franchise / Sarja -sanasto (`FranchiseHandler`, sanasto: `franchise`):**
  - Listaa kaikki kyseiseen sarjaan kuuluvat peli-termit (`field_kuuluu_pelisarjaan`).
  - Lajittelee pelit kronologiseen järjestykseen julkaisuajan (`field_julkaisu_pvm`) mukaan huomioiden tarkkuustason (tarkat päivämäärät ensin, sitten kuukaudet, kvartaalit ja vuodet).

#### 2. XenForo-foorumikytkentä (`ForumThreadsController`):
- Kytkeytyy Drupalin ulkoiseen `xenforo`-tietokantayhteyteen.
- **Automaattitäydennys (`/forum_data/threads`):** Tarjoaa AJAX-autocompleten pelitermin muokkauslomakkeelle (`taxonomy_term_peli_form`), jolloin ylläpitäjä voi etsiä ketjuja foorumin "Pelit"-alueelta (node 6) nimen perusteella.
- Tallentaa ketjun ID:n kenttään `field_forum_ketju` ja esittää termisivulla suoran linkin foorumin vastaavaan keskusteluketjuun ketjun oikealla otsikolla (`get_thread_title_by_id`).

#### 3. Pelisivusto `/pelit` (`GamesPageController` ja `templates/games-page.html.twig`):
- **Pinnalla juuri nyt (Top Games):** Ylläpidon valitsemat 3 nostopeliä suurine kuvituskuvineen (`field_hero_kuva`, kuvaformaatti `large`).
- **Pelien haku (`GamesPageSearchForm`):** Autocomplete-hakukenttä `peli`-sanastoon, joka ohjaa käyttäjän suoraan valitun pelin sivulle.
- **Tulevat julkaisut:** 10 seuraavaksi julkaistavaa peliä aikajärjestyksessä (`julkaisuajankohta.stored_date >= tänään`).
- **Puhutuimmat pelit (`MatomoService`):**
  - Hakee Matomo Analytics API:n kautta eniten näyttökertoja saaneet foorumiketjut.
  - Eristää ketju-ID:n URL-tunnisteesta ja yhdistää sen Drupalin `peli`-termeihin `field_forum_ketju`-kentän kautta.
  - Esittää listan peleistä, joista foorumilla keskustellaan eniten.
- **Ylläpitoasetukset (`GamesPageSettingsForm`):**
  - Hallintasivu polussa `/admin/config/konsolifin/games-page` (valikossa *KonsoliFIN*).
  - Mahdollistaa nostopelien (Top game 1–3) valinnan sekä Matomo API -osoitteen ja autentikointitokenin konfiguroinnin.
- **Yksikkötestit:** `FranchiseHandlerTest`, `GamesPageControllerTest`, `MatomoServiceTest`.

---

### Migrate KonsoliFIN (`migrate_konsolifin`)

**Sijainti:** `web/modules/custom/migrate_konsolifin`  
**Tarkoitus:** Hallitsee koko tuotantosivuston tietojen ja tiedostojen migraatiota vanhasta Drupal 7 -järjestelmästä moderniin Drupal 11 -arkkitehtuuriin Drupal Migrate API:n avulla.

#### Ominaisuudet ja arkkitehtuuri:
- **Lähdeplugin (`D7NodeWithAlias`, id: `d7_node_with_alias`):**
  - Laajentaa Drupalin sisäänrakennettua `d7_node`-lähdepluginia.
  - Hakee solmua siirrettäessä vastaavan vanhan URL-aliaksen suoraan Drupal 7:n `url_alias`-taulusta ja liittää sen lähdedataan, jolloin sivuston vanhat ja SEO-arvokkaat osoitteet säilyvät sellaisinaan ilman rikkinäisiä linkkejä.
- **Prosessointiplugin (`MediaWysiwygToEmbed`, id: `media_wysiwyg_to_embed`):**
  - Ratkaisee kriittisen migraatio-ongelman: Drupal 7 Media WYSIWYG tallensi leipätekstin sekaan JSON-muotoisia tokeneita, kuten:
    ```html
    [[{"fid":"1234","view_mode":"default","type":"media","attributes":{"class":"media-wysiwyg-align-right"}}]]
    ```
  - Plugin jäsentää JSON-rakenteen säännöllisellä lausekkeella, etsii vastaavan uuden Drupal 11 Media -entiteetin UUID:n migraatiotauluista (`migrate.lookup`) ja muuntaa tokenin moderniksi Drupal 11 Media Embed -HTML-tagiksi:
    ```html
    <drupal-media data-entity-type="media" data-entity-uuid="b1a2c3d4-..." data-view-mode="full" data-align="right"></drupal-media>
    ```
  - Säilyttää kuvien tasaukset (*left*, *right*, *center*) ja tekee automaattisen näkymätilan mäppäyksen.
- **Matematiikka- ja kenttämuunnosapu (`MigrateMath`):**
  - Tarjoaa staattisia apumetodeita kenttäarvojen muuntamiseen migraatiossa (esim. arvostelupisteiden ja offsettien laskenta).
- **Kattavat migraatiomäärittelyt (`migrations/*.yml`):**
  - **Käyttäjät:** `konsolifin_users`, `konsolifin_user_pictures`.
  - **Tiedostot ja media:** `konsolifin_files`, `konsolifin_media_images`, `konsolifin_media_audio`, `konsolifin_media_video`.
  - **Taksonomiat:** `konsolifin_taxonomy_alustat`, `konsolifin_taxonomy_alustatarkenne`, `konsolifin_taxonomy_ihmiset`, `konsolifin_taxonomy_pelijulkaisijat`, `konsolifin_taxonomy_pelistudiot`, `konsolifin_taxonomy_pelit`, `konsolifin_taxonomy_sarja`.
  - **Sisältötyypit (solmut):** `artikkeli`, `blog`, `julkaisu`, `laitearvio`, `media_arvostelu`, `page`, `peliarvostelu`, `podcast`, `uutinen`, `video`, `vierailija_arvostelu`.
  - **Muut:** `konsolifin_comments` ja `konsolifin_url_alias`.

---

### Migrate KonsoliFIN Testidata (`migrate_konsolifin_testdata`)

**Sijainti:** `web/modules/custom/migrate_konsolifin_testdata`  
**Tarkoitus:** Mahdollistaa paikallisen kehitysympäristön nopean pystyttämisen ja testaamisen ilman pääsyä tuotantotietokantaan tarjoamalla valmiin, laadukkaan ja realistisen suomenkielisen peli-, uutis- ja käyttäjäaineiston.

#### Ominaisuudet ja arkkitehtuuri:
- **Lähdeplugin (`JsonFixture`, id: `json_fixture`):**
  - Mukautettu Migrate-lähdeplugin, joka lukee staattisia JSON-tiedostoja moduulin `data/`-hakemistosta ja syöttää rivit migraatioputkelle.
- **Valmiit testiaineistot (`data/`):**
  - **Käyttäjät (`users.json`):** Testikäyttäjät toimituksen eri rooleilla.
  - **Taksonomiat (`taxonomy/`):** `alustat.json`, `alustatarkenne.json`, `franchise.json`, `ihminen.json`, `peli.json`, `pelijulkaisijat.json`, `pelistudiot.json`, `sarja.json`.
  - **Sisällöt (`nodes/`):** Realistiset artikkelit, uutiset, blogit, laitearviot, podcastit, julkaisut, videot sekä peliarvostelut täydellisine 0–400 tähtiarvosanoineen ja englanninkielisine yhteenvetoineen.
  - **Mediat ja tiedostot (`media/`, `files/`):** Sisältää oikeat generoidut teemalliset kuvituskuvat (`.png`) eri peligenreistä sekä podcast-placeholder-äänitiedoston (`podcast-placeholder.mp3`).
- **Automaatio ja ajo (`testdata.sh`):**
  - Shell-skripti, joka ajaa testidatamigraation oikeassa järjestyksessä (käyttäjät &rarr; tiedostot/media &rarr; taksonomiat &rarr; solmut) 100 kohteen erissä tarkistaen siirron tilan Drushilla.
- **Laatu ja eheystestaus (`tests/src/Unit`):**
  - Sisältää laajan testipatteriston, joka varmistaa kehitysdynaamisuuden ja datan eheyden ennen migraatioajoja:
    - `JsonFixtureParsingTest`: JSON-syntaksin ja rakenteen validointi.
    - `FixtureIdUniquenessTest`: Yksilöllisten ID-tunnusten varmistus.
    - `CrossFixtureReferentialIntegrityTest`: Viite-eheys (esim. solmut viittaavat olemassa oleviin käyttäjiin, medioihin ja taksonomioihin).
    - `UserFixtureDataSafetyTest`: Varmistaa, ettei testidatassa ole oikeita salasanoja tai arkaluonteisia henkilötietoja.
    - `ReviewScoreValidityTest`: Arvostelupisteiden oikeellisuus (0–400 välillä ja 40 jaollinen).
    - `TimestampFormatValidityTest`: Aikaleimojen oikeellisuus.
    - `PlaceholderImageSizeTest`: Kuvakokojen validointi.
    - `ImportRollbackReadinessTest`: Tarkistaa, että migraatiot voidaan ajaa alas ja uudelleen ilman ristiriitoja.
- **Datan noutotyökalu (`retriever/`):**
  - Sisältää Python-pohjaisen apuskriptin (`retrieve.py`), jolla voidaan tarvittaessa hakea ja anonymisoida otos tuotantosivuston aineistosta testikäyttöön.

---

### KonsoliFIN Workflows (`konsolifin_workflows`)

**Sijainti:** `web/modules/custom/konsolifin_workflows`  
**Tarkoitus:** Täydentää Drupalin `workflow`-kontribuutiomoduulia hallitsemalla solmujen (node) julkaisutilaa (`status`) automaattisesti työnkulun tilan perusteella sekä piilottamalla manuaalisen julkaisutilan valintalaatikon työnkulkua käyttäviltä sisältötyypeiltä.

#### Ominaisuudet ja arkkitehtuuri:
- **Julkaisutilan automaattinen synkronointi (`WorkflowPublicationManager`):**
  - **Yleinen julkaisuputki (`field_tyonkulku`):** Solmu on julkaistu (`status = 1`) ainoastaan tilassa `yleinen_julkaisuputki_julkaistu`. Kaikissa muissa tiloissa (ja kentän ollessa tyhjä) solmu asetetaan automaattisesti julkaisemattomaksi (`status = 0`).
  - **Uutisputki (`field_tyonkulku_uutinen`):** Solmu on julkaistu (`status = 1`) ainoastaan tilassa `uutisputki_julkaistu`. Kaikissa muissa tiloissa (ja kentän ollessa tyhjä) solmu asetetaan automaattisesti julkaisemattomaksi (`status = 0`).
  - Sisältötyypit, joilla ei ole kumpaakaan kenttää, säilyttävät manuaalisen julkaisutilansa ilman puuttumista.
- **Julkaisuajankohdan automaattinen päivitys (`syncPublishingTimestamp`):**
  - Jotta julkaistujen sisältöjen järjestys sivustolla ja syötteissä vastaa niiden todellista julkaisuajankohtaa, solmun luontiaikaleima (`created`) päivitetään tilasiirtymän todelliseen ajankohtaan **ainoastaan seuraavissa kahdessa tilanteessa**:
    1. Yleinen julkaisuputki: `yleinen_julkaisuputki_julkaisematta` &rarr; `yleinen_julkaisuputki_julkaistu`
    2. Uutisputki: `uutisputki_tyon_alla` &rarr; `uutisputki_julkaistu`
  - Kaikissa muissa tilanteissa (kuten jo julkaistun solmun muokkaaminen ja tallentaminen, kymmenien tuhansien vanhojen aineistojen migraatiot ja massamuokkaukset sekä luonnostilojen väliset siirtymät) aikaleima säilytetään täysin koskemattomana.
- **Oikolukumerkintöjen automaattinen siivous (`stripProofreadingComments`):**
  - Käy läpi julkaistavan artikkelin leipätekstin ja ingressin HTML:n ja poistaa automaattisesti kaikki toimituksen sisäiset oikolukumerkinnät (`<span class="sisalto-oikoluku">...</span>`) DOMDocument/XPath-jäsentimellä, jotta toimituksen sisäiset kommentit eivät päädy julkiselle sivustolle.
  - Koska siivous ajetaan `konsolifin_workflows_node_presave`-hookissa julkaisutilan synkronoinnin (`syncPublishingStatus`) jälkeen, merkinnät poistetaan luotettavasti myös silloin, kun sisältö julkaistaan **ajastetun työnkulkusiirtymän** (cron) kautta.
- **Entiteetin tallennushook (`konsolifin_workflows_node_presave`):**
  - Kutsuu `syncPublishingStatus($node)`, `syncPublishingTimestamp($node)` ja `stripProofreadingComments($node)` -metodeja varmistaen julkaisutilan, julkaisuaikaleiman sekä sisällön siisteyden kaikissa tallennustilanteissa (lomakemuokkaus, cronin ajastetut siirtymät, ohjelmalliset tallennukset).
- **Lomakemuokkaukset (`konsolifin_workflows_form_node_form_alter`):**
  - Piilottaa Drupalin oletusarvoisen "Julkaistu" -valintaruudun (`$form['status']['#access'] = FALSE`) kaikilta sisältötyypeiltä, joissa on työnkulkukenttä, jotta käyttäjät eivät vahingossa ohita työnkulun tilaan perustuvaa julkaisulogiikkaa.
- **Slack-ilmoituspalvelu ja triggerit (`SlackNotificationService`, `WorkflowTransitionSubscriber`):**
  - Kuuntelee työnkulun tapahtumaa `WorkflowEvents::POST_TRANSITION`.
  - **Oikolukutriggeri:** Kun sisältö siirtyy tilaan *Oikoluettavana* (`yleinen_julkaisuputki_oikoluettavana`), lähetetään ilmoitus määritetylle oikoluvun Slack-kanavalle (`channel_oikoluku`) linkkeineen ja kirjoittajatietoineen (täggää kirjoittajan käyttäjätililtä löytyvän `field_slack_id`-tunnuksen muodossa `<@ID>`).
  - **Julkaisutriggeri:** Kun sisältö siirtyy tilaan *Julkaistu* (`yleinen_julkaisuputki_julkaistu` tai `uutisputki_julkaistu`):
    - Uutisille (`uutinen` / `uutisputki`) ilmoitus reititetään uutiskanavalle (`channel_news_published`).
    - Muille sisältötyypeille (artikkelit, arviot ym.) ilmoitus reititetään toiselle kanavalle (`channel_other_published`).
  - Tukee sekä Slack Bot User OAuth Tokeneita (`xoxb-...` `chat.postMessage`-rajapinnalla) että suoria Incoming Webhook -osoitteita. Virheet ja aikakatkaisut käsitellään siististi lokiin keskeyttämättä julkaisuprosessia.
- **Hallintalomake ja asetukset (`KonsolifinWorkflowsSettingsForm`):**
  - Reitti `/admin/config/workflow/konsolifin-workflows` (oikeus: `administer konsolifin workflows`).
  - Mahdollistaa Slack-ilmoitusten kytkemisen päälle/pois, API-avaimen/tokenin syöttämisen sekä oikoluku-, uutis- ja julkaisukanavien määrittämisen erikseen.
  - Sisältää painikkeen testi-ilmoituksen lähettämiseen suoraan hallintakäyttöliittymästä.
- **Yksikkötestit (`tests/src/Unit`):**
  - `WorkflowPublicationManagerTest`: Kattaa julkaisutilojen logiikan, julkaisuaikaleiman päivityksen vain sallituissa tilasiirtymissä, vanhojen solmujen aikaleiman koskemattomuuden, ajastettujen siirtymien aikaleimojen selvityksen sekä oikolukukommenttien siivouksen ja UTF-8-eheyden (44 testiä).
  - `SlackNotificationServiceTest`: Kattaa viestin lähetyksen Bot Tokenilla, webhoookeilla, virhetilanteet, author-täggäykset ja kanavareititykset (10 testiä).
  - `WorkflowTransitionSubscriberTest`: Kattaa Oikoluku- ja julkaisutriggerit, ajastettujen tilojen ja samojen tilojen ohitukset (8 testiä).

---

## Teemat

### KonsoliFIN 2026 (`konsolifin2026`)

**Sijainti:** `web/themes/custom/konsolifin2026`  
**Tarkoitus:** KonsoliFINin moderni, responsiivinen ja mobiili-ensin -suunniteltu pääteema.

- **Pohjateema:** Drupal 11 `stable9`.
- **Riippuvuudet:** Integroitu suoraan `konsolifin_ads`-moduuliin mainospaikkojen osalta.
- **Alueet (Regions):**
  - `header`, `primary_menu`, `content`, `content_bottom`, `footer_first`, `footer_second`, `footer_third`, `footer`.
- **Ulkoasu ja komponentit:**
  - Moderni tummasävyinen pelisivustoilme (dark mode).
  - Tyylitelty korttiasettelu artikkeleille (`frontpage-featured-card`, `frontpage-duo-card`, `frontpage-standard-card`, `frontpage-compact-card`).
  - Täysi tuki CKEditor 5 -editorin muotoiluille (`css/base/editor-styles.css`).
  - Responsiivinen mobiilivalikko ja navigointi.
