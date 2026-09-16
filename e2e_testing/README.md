# KonsoliFIN E2E -testaus (Playwright)

Tämä hakemisto sisältää KonsoliFINin End-to-End- (E2E) regressio- ja savutestit, jotka on toteutettu [Playwright](https://playwright.dev/)-testauskehyksellä.

Testit varmistavat sivuston kriittiset toiminnot, kuten julkisten sivujen navigaation, evästebannerin kuittaamisen, sisällön selauksen, kirjautumisen (`/user/login?showcore`) sekä ylläpidon hallintanäkymät Drupal-coren ja moduulipäivitysten jälkeen.

---

## Sisällysluettelo / Table of Contents

- [Suomeksi (Finnish)](#suomeksi)
  - [1. Esivaatimukset](#1-esivaatimukset)
  - [2. Alkuasennus ja konfigurointi](#2-alkuasennus-ja-konfigurointi)
  - [3. Testien ajaminen](#3-testien-ajaminen)
  - [4. Testiraportit ja virheenjäljitys](#4-testiraportit-ja-virheenjäljitys)
  - [5. Uusien testien nauhoitus ja kirjoittaminen](#5-uusien-testien-nauhoitus-ja-kirjoittaminen)
- [In English](#in-english)
  - [1. Prerequisites](#1-prerequisites)
  - [2. Initial Setup and Configuration](#2-initial-setup-and-configuration)
  - [3. Running the Tests](#3-running-the-tests)
  - [4. Reports and Troubleshooting](#4-reports-and-troubleshooting)
  - [5. Recording and Writing New Tests](#5-recording-and-writing-new-tests)

---

<a name="suomeksi"></a>
## Suomeksi

### 1. Esivaatimukset

Ennen testien ajamista varmista, että:

1. **Node.js ja npm** on asennettu kehityskoneelle (suositus: Node.js LTS v18 tai uudempi).
2. **KonsoliFINin paikallinen kehitysympäristö pyörii**:
   ```bash
   # Projektin juurihakemistossa
   make start
   ```
3. **Testidata on tuotu kantaan**:
   Osa testeistä (kuten `konsolifin.spec.js`) olettaa, että sivustolla on testidatan mukaiset artikkelit ja testikäyttäjät:
   ```bash
   # Projektin juurihakemistossa
   docker exec konsolifin_web ./vendor/bin/drush pm:install migrate_konsolifin_testdata
   ./testdata.sh
   docker exec konsolifin_web ./vendor/bin/drush pm:uninstall migrate_konsolifin_testdata
   ```
   *Huom:* Testikäyttäjien salasana on `password`.

---

### 2. Alkuasennus ja konfigurointi

Siirry `e2e_testing`-alihakemistoon:

```bash
cd e2e_testing
```

#### 2.1 Asenna Node.js-riippuvuudet

```bash
npm install
```

#### 2.2 Asenna Playwrightin selainbinäärit

Testikonfiguraatio käyttää oletuksena Chromium-selainta:

```bash
npx playwright install chromium
```

*(Mikäli haluat asentaa kaikki Playwrightin selaimet tai ajaa testejä Linux-ympäristössä, käytä komentoa `npx playwright install --with-deps`)*.

#### 2.3 Kohdeosoitteen (`BASE_URL`) määrittäminen

Oletusosoite tiedostossa `playwright.config.js` on:
```text
https://web.konsolifin.orb.local/
```

Jos käytät toista osoitetta (esim. Docker Desktopin `http://localhost:8080` tai toista OrbStack-osoitetta `https://web.konsolifin-2026.orb.local/`), voit määrittää sen ympäristömuuttujalla `BASE_URL`:

- **Kertaluonteisesti testiajon yhteydessä:**
  ```bash
  BASE_URL=http://localhost:8080 npm test
  # tai
  BASE_URL=http://localhost:8080 npx playwright test
  ```

- **Pysyvämmin komentorivisessiossa:**
  ```bash
  export BASE_URL=http://localhost:8080
  npm test
  ```

- **Tiedostossa `playwright.config.js`:**
  Voit myös muokata `baseURL`-kenttää suoraan konfiguraatiotiedostossa.

#### 2.4 Itseallekirjoitetut SSL-varmenteet

Jos käytät paikallista HTTPS-osoitetta ja selain valittaa varmenteesta, voit sallia varmenteet lisäämällä `playwright.config.js` -tiedoston `use`-lohkoon:

```javascript
ignoreHTTPSErrors: true,
```

---

### 3. Testien ajaminen

Kaikki komennot suoritetaan `e2e_testing`-hakemistossa.

| Komento | Kuvaus |
|---------|--------|
| `npm test` tai `npx playwright test` | Ajaa kaikki testit taustalla (headless-tila). |
| `npm run test:headed` tai `npx playwright test --headed` | Avaa selaimen näkyviin ja näyttää testin etenemisen reaaliajassa. |
| `npm run test:ui` tai `npx playwright test --ui` | Käynnistää Playwrightin interaktiivisen graafisen käyttöliittymän (suositeltu testien kehitykseen). |
| `npm run test:debug` tai `npx playwright test --debug` | Käynnistää Playwright Inspector -virheenjäljittimen askel askeleelta suoritukseen. |

#### Yksittäisen testitiedoston ajaminen

```bash
npx playwright test tests/konsolifin.spec.js
```

#### Testien rajaaminen nimen perusteella

```bash
npx playwright test -g "Regressiotestit"
```

---

### 4. Testiraportit ja virheenjäljitys

#### HTML-raportin tarkastelu

Testiajon jälkeen tuloksista generoidaan automaattisesti HTML-raportti hakemistoon `playwright-report/`. Avaa raportti selaimeen komennolla:

```bash
npm run report
# tai
npx playwright show-report
```

#### Epäonnistuneen testin tallenteet (Trace Viewer & kuvakaappaukset)

- Konfiguraatiossa on päällä `screenshot: 'only-on-failure'` ja `trace: 'on-first-retry'`.
- Epäonnistuneiden testien ruutukaappaukset ja diagnostiikkatiedot tallentuvat hakemistoon `test-results/`.
- Voit avata nauhoitetun trace-tiedoston komentoriviltä:
  ```bash
  npx playwright show-trace test-results/<testikansion-nimi>/trace.zip
  ```

---

### 5. Uusien testien nauhoitus ja kirjoittaminen

Voit luoda uusia testejä hyödyntämällä Playwright Codegen -työkalua, joka generoi testikoodia samalla kun klikkaat sivustoa selaimessa:

```bash
npx playwright codegen https://web.konsolifin.orb.local/
# tai
npx playwright codegen http://localhost:8080/
```

**Huomioitavaa KonsoliFIN-testejä kirjoittaessa:**
1. **Kirjautumispolku:** KonsoliFIN käyttää SimpleSAMLphp-kertakirjautumista, joten perinteinen käyttäjätunnus/salasana-kirjautumislomake saadaan näkyviin lisäämällä parametrina `?showcore` polkuun: `/user/login?showcore`.
2. **Evästebanneri:** Sivusto käyttää InMobi Choice CMP -suostumushallintaa. Testin alussa on suositeltavaa kuitata banneri, mikäli se on näkyvissä:
   ```javascript
   if (await page.getByRole('button', { name: 'HYVÄKSY' }).isVisible()) {
       await page.getByRole('button', { name: 'HYVÄKSY' }).click();
   }
   ```

---

<a name="in-english"></a>
## In English

### 1. Prerequisites

Before running the tests, make sure:

1. **Node.js & npm** are installed on your machine (Node.js LTS v18+ recommended).
2. **Local KonsoliFIN environment is running**:
   ```bash
   # In project root
   make start
   ```
3. **Test data fixtures are imported**:
   The test suite (e.g. `konsolifin.spec.js`) expects articles and users from the test fixtures:
   ```bash
   # In project root
   docker exec konsolifin_web ./vendor/bin/drush pm:install migrate_konsolifin_testdata
   ./testdata.sh
   docker exec konsolifin_web ./vendor/bin/drush pm:uninstall migrate_konsolifin_testdata
   ```
   *Note:* Default password for test users is `password`.

---

### 2. Initial Setup and Configuration

Navigate to the `e2e_testing` directory:

```bash
cd e2e_testing
```

#### 2.1 Install Node.js dependencies

```bash
npm install
```

#### 2.2 Install Playwright browser binaries

The default test project runs on Chromium:

```bash
npx playwright install chromium
```

*(If you need all browser binaries or are running on Linux/CI, run `npx playwright install --with-deps`)*.

#### 2.3 Configuring the Base URL (`BASE_URL`)

The default base URL in `playwright.config.js` is:
```text
https://web.konsolifin.orb.local/
```

If your local environment runs on a different URL (such as Docker Desktop's `http://localhost:8080` or another OrbStack domain `https://web.konsolifin-2026.orb.local/`), supply it using the `BASE_URL` environment variable:

- **For a single test run:**
  ```bash
  BASE_URL=http://localhost:8080 npm test
  # or
  BASE_URL=http://localhost:8080 npx playwright test
  ```

- **Export for the current shell session:**
  ```bash
  export BASE_URL=http://localhost:8080
  npm test
  ```

- **Directly in `playwright.config.js`:**
  You can also change the `baseURL` property directly in `playwright.config.js`.

#### 2.4 Self-Signed SSL Certificates

If you run local HTTPS with self-signed certificates and Playwright throws SSL certificate errors, enable certificate bypass in `playwright.config.js` under the `use` object:

```javascript
ignoreHTTPSErrors: true,
```

---

### 3. Running the Tests

All commands should be executed inside the `e2e_testing` directory.

| Command | Description |
|---------|-------------|
| `npm test` or `npx playwright test` | Runs all tests in headless mode. |
| `npm run test:headed` or `npx playwright test --headed` | Runs tests in a visible browser window. |
| `npm run test:ui` or `npx playwright test --ui` | Launches Playwright's interactive UI mode (recommended for test development). |
| `npm run test:debug` or `npx playwright test --debug` | Runs tests in Playwright Inspector for step-by-step debugging. |

#### Running a specific test file

```bash
npx playwright test tests/konsolifin.spec.js
```

#### Filtering tests by name

```bash
npx playwright test -g "Regressiotestit"
```

---

### 4. Reports and Troubleshooting

#### Viewing the HTML Report

An HTML report is automatically created in `playwright-report/` after running tests. Open it with:

```bash
npm run report
# or
npx playwright show-report
```

#### Failure Artifacts (Trace Viewer & Screenshots)

- Configured settings: `screenshot: 'only-on-failure'` and `trace: 'on-first-retry'`.
- Failed test artifacts are stored in `test-results/`.
- Open a recorded trace ZIP file with:
  ```bash
  npx playwright show-trace test-results/<test-run-folder>/trace.zip
  ```

---

### 5. Recording and Writing New Tests

Generate test scripts automatically using Playwright Codegen while interacting with the site:

```bash
npx playwright codegen https://web.konsolifin.orb.local/
# or
npx playwright codegen http://localhost:8080/
```

**Things to keep in mind when writing KonsoliFIN tests:**
1. **Login Route:** Due to the SimpleSAMLphp SSO module, standard username/password login requires appending `?showcore` to the URL: `/user/login?showcore`.
2. **Cookie Banner:** The site uses InMobi Choice CMP. It is recommended to dismiss the cookie banner if visible:
   ```javascript
   if (await page.getByRole('button', { name: 'HYVÄKSY' }).isVisible()) {
       await page.getByRole('button', { name: 'HYVÄKSY' }).click();
   }
   ```
