# KonsoliFIN E2E -testaus (Playwright)

Tämä hakemisto sisältää KonsoliFINin End-to-End- (E2E) regressio- ja savutestit, jotka on toteutettu [Playwright](https://playwright.dev/)-testauskehyksellä.

Testit kattavat sekä paikallisen kehitysympäristön (`tests/konsolifin.spec.js`) että staging-testiympäristön (`tests/staging.spec.js`), joka sijaitsee osoitteessa [https://stage.konsolifin.net](https://stage.konsolifin.net).

---

## Sisällysluettelo / Table of Contents

- [Suomeksi (Finnish)](#suomeksi)
  - [1. Esivaatimukset](#1-esivaatimukset)
  - [2. Alkuasennus ja .env-konfigurointi](#2-alkuasennus-ja-env-konfigurointi)
  - [3. Testisarjat (Local vs Staging)](#3-testisarjat)
  - [4. Testien ajaminen](#4-testien-ajaminen)
  - [5. Testiraportit ja virheenjäljitys](#5-testiraportit-ja-virheenjäljitys)
  - [6. Uusien testien nauhoitus ja kirjoittaminen](#6-uusien-testien-nauhoitus-ja-kirjoittaminen)
- [In English](#in-english)
  - [1. Prerequisites](#1-prerequisites)
  - [2. Initial Setup and .env Configuration](#2-initial-setup-and-env-configuration)
  - [3. Test Suites (Local vs Staging)](#3-test-suites)
  - [4. Running the Tests](#4-running-the-tests)
  - [5. Reports and Troubleshooting](#5-reports-and-troubleshooting)
  - [6. Recording and Writing New Tests](#6-recording-and-writing-new-tests)

---

<a name="suomeksi"></a>
## Suomeksi

### 1. Esivaatimukset

Ennen testien ajamista varmista, että:

1. **Node.js ja npm** on asennettu kehityskoneelle (suositus: Node.js LTS v18 tai uudempi).
2. **Paikallinen kehitysympäristö (paikallisia testejä varten)**:
   ```bash
   # Projektin juurihakemistossa
   make start
   ```
3. **Paikallinen testidata tuotu (paikallisia testejä varten)**:
   Paikallinen testisarja (`konsolifin.spec.js`) olettaa, että kehitysympäristöön on tuotu testidatan mukaiset artikkelit ja testikäyttäjät:
   ```bash
   # Projektin juurihakemistossa
   docker exec konsolifin_web ./vendor/bin/drush pm:install migrate_konsolifin_testdata
   ./testdata.sh
   docker exec konsolifin_web ./vendor/bin/drush pm:uninstall migrate_konsolifin_testdata
   ```

---

### 2. Alkuasennus ja .env-konfigurointi

Siirry `e2e_testing`-alihakemistoon:

```bash
cd e2e_testing
```

#### 2.1 Asenna Node.js-riippuvuudet

```bash
npm install
```

#### 2.2 Asenna Playwrightin selainbinäärit

```bash
npx playwright install chromium
```

*(Linux- ja CI-ympäristöissä käytä tarvittaessa komentoa `npx playwright install --with-deps chromium`)*.

#### 2.3 Ympäristömuuttujat ja `.env`-tiedosto

Kaikki luottamukselliset tiedot (kuten staging-ympäristön testikäyttäjän salasana ja paikalliset kirjautumistiedot) määritellään `.env`-tiedostossa.

Hakemistossa on valmis mallipohja `.env.example`. Luo oma `.env`-tiedosto kopioimalla se:

```bash
cp .env.example .env
```

`.env`-tiedoston sisältö:

```dotenv
# --- Staging-ympäristö (https://stage.konsolifin.net) ---
STAGING_BASE_URL=https://stage.konsolifin.net
STAGING_USERNAME=oma_staging_tunnus
STAGING_PASSWORD=oma_staging_salasana

# --- Paikallinen kehitysympäristö ---
BASE_URL=https://web.konsolifin.orb.local/
LOCAL_USERNAME=admin
LOCAL_PASSWORD=admin
```

> [!NOTE]
> `.env`-tiedosto on lisätty `.gitignore`-tiedostoon, joten salasanat eivät koskaan päädy versionhallintaan.
> Jos `STAGING_USERNAME` ja `STAGING_PASSWORD` jätetään tyhjiksi, staging-testien julkiset savutestit ajetaan normaalisti ja kirjautumista vaativa ylläpitotesti ohitetaan automaattisesti.

#### 2.4 Itseallekirjoitetut SSL-varmenteet

Jos käytät paikallista HTTPS-osoitetta ja selain ilmoittaa varmennevirheestä, voit sallia varmenteet lisäämällä `playwright.config.js` -tiedoston `use`-lohkoon rivin:

```javascript
ignoreHTTPSErrors: true,
```

---

### 3. Testisarjat

Hakemistossa on kaksi erillistä testikokonaisuutta:

1. **Paikalliset testit (`tests/konsolifin.spec.js`)**:
   - Kohdistuu paikalliseen kehitysympäristöön (`BASE_URL` tai `https://web.konsolifin.orb.local/`).
   - Testaa valikoita, testidatan artikkeleita ja kirjautumista lokaalilla `LOCAL_USERNAME`/`LOCAL_PASSWORD` -tunnuksella.
2. **Staging-testit (`tests/staging.spec.js`)**:
   - Kohdistuu staging-palvelimeen (`STAGING_BASE_URL` tai `https://stage.konsolifin.net`).
   - Testaa julkisen sivuston toimivuutta (etusivu, brändäys, Pelit-, Arvostelut-, Uutiset-, Podcastit- ja Jutut-osiot, artikkelin lukunäkymä).
   - Testaa ylläpitäjän kirjautumista (`/user/login?showcore`) ja hallintapaneelia (`STAGING_USERNAME`/`STAGING_PASSWORD`).

---

### 4. Testien ajaminen

Kaikki komennot suoritetaan `e2e_testing`-hakemistossa.

| Komento | Kuvaus |
|---------|--------|
| `npm run test:staging` | Ajaa staging-ympäristön testit (`stage.konsolifin.net`). |
| `npm run test:staging:headed` | Ajaa staging-testit näkyvällä selaimella. |
| `npm run test:staging:ui` | Käynnistää staging-testit Playwrightin interaktiivisessa UI-tilassa. |
| `npm run test:local` | Ajaa paikallisen kehitysympäristön testit. |
| `npm test` | Ajaa molemmat testisarjat (paikallinen + staging). |
| `npm run test:headed` | Ajaa testit näkyvällä selaimella. |
| `npm run test:ui` | Avaa Playwright UI -tilanteen virheenjäljitykseen. |
| `npm run test:debug` | Käynnistää Playwright Inspector -askeltimen. |

#### Yksittäisen testin ajaminen suoraan npx:llä

```bash
# Vain staging-testit:
npx playwright test --project=staging

# Vain paikalliset testit:
npx playwright test --project=chromium
```

---

### 5. Testiraportit ja virheenjäljitys

#### HTML-raportin avaaminen

```bash
npm run report
# tai
npx playwright show-report
```

#### Epäonnistuneen testin tallenteet (Trace Viewer & kuvakaappaukset)

- Epäonnistuneiden testien ruutukaappaukset ja diagnostiikkatiedot tallentuvat hakemistoon `test-results/`.
- Avaa nauhoitettu trace-tiedosto:
  ```bash
  npx playwright show-trace test-results/<testikansion-nimi>/trace.zip
  ```

---

### 6. Uusien testien nauhoitus ja kirjoittaminen

Playwright Codegenilla voit generoida uusia testejä selaintoimintojen pohjalta:

```bash
# Staging-sivustoa vasten:
npx playwright codegen https://stage.konsolifin.net

# Paikallista sivustoa vasten:
npx playwright codegen https://web.konsolifin.orb.local/
```

**Huomioitavaa KonsoliFIN-testejä kirjoittaessa:**
1. **Kirjautumispolku:** KonsoliFIN käyttää SimpleSAMLphp-kertakirjautumista, joten perinteinen käyttäjätunnus/salasana-kirjautumislomake avataan parametrilla `?showcore`: `/user/login?showcore`.
2. **Evästebanneri:** Sivustolla on InMobi Choice CMP -suostumushallinta. Testin alussa banneri kuitataan tarvittaessa:
   ```javascript
   const cookieButton = page.getByRole('button', { name: 'HYVÄKSY' });
   if (await cookieButton.isVisible({ timeout: 3000 }).catch(() => false)) {
       await cookieButton.click();
   }
   ```

---

<a name="in-english"></a>
## In English

### 1. Prerequisites

Before running the tests, make sure:

1. **Node.js & npm** are installed on your machine (Node.js LTS v18+ recommended).
2. **Local KonsoliFIN environment (for local tests)**:
   ```bash
   # In project root
   make start
   ```
3. **Test data fixtures imported (for local tests)**:
   The local test suite (`konsolifin.spec.js`) expects articles and users from the test fixtures:
   ```bash
   # In project root
   docker exec konsolifin_web ./vendor/bin/drush pm:install migrate_konsolifin_testdata
   ./testdata.sh
   docker exec konsolifin_web ./vendor/bin/drush pm:uninstall migrate_konsolifin_testdata
   ```

---

### 2. Initial Setup and .env Configuration

Navigate to the `e2e_testing` directory:

```bash
cd e2e_testing
```

#### 2.1 Install Node.js dependencies

```bash
npm install
```

#### 2.2 Install Playwright browser binaries

```bash
npx playwright install chromium
```

*(On Linux or CI environments, run `npx playwright install --with-deps chromium`)*.

#### 2.3 Environment Variables and `.env` File

All confidential data (such as staging passwords and local credentials) should be stored in a `.env` file.

A template is provided as `.env.example`. Copy it to create your `.env`:

```bash
cp .env.example .env
```

Contents of `.env`:

```dotenv
# --- Staging Environment (https://stage.konsolifin.net) ---
STAGING_BASE_URL=https://stage.konsolifin.net
STAGING_USERNAME=your_staging_username
STAGING_PASSWORD=your_staging_password

# --- Local Environment ---
BASE_URL=https://web.konsolifin.orb.local/
LOCAL_USERNAME=admin
LOCAL_PASSWORD=admin
```

> [!NOTE]
> `.env` is listed in `.gitignore` so your credentials will not be committed.
> If `STAGING_USERNAME` and `STAGING_PASSWORD` are left blank, public staging smoke tests will run and the authenticated administration test will be automatically skipped.

#### 2.4 Self-Signed SSL Certificates

If your local environment uses self-signed HTTPS certificates, you can ignore SSL errors by adding `ignoreHTTPSErrors: true` inside `playwright.config.js` under the `use` block.

---

### 3. Test Suites

The test repository contains two test suites:

1. **Local Suite (`tests/konsolifin.spec.js`)**:
   - Targets the local development instance (`BASE_URL` or `https://web.konsolifin.orb.local/`).
   - Verifies navigation, test fixture content, and login using local credentials.
2. **Staging Suite (`tests/staging.spec.js`)**:
   - Targets the staging environment (`STAGING_BASE_URL` or `https://stage.konsolifin.net`).
   - Verifies public site stability (homepage, branding, Pelit, Arvostelut, Uutiset, Podcastit, Muut jutut, article reader view).
   - Verifies admin authentication (`/user/login?showcore`) and dashboard access when credentials are provided in `.env`.

---

### 4. Running the Tests

All commands are run from within the `e2e_testing` directory.

| Command | Description |
|---------|-------------|
| `npm run test:staging` | Runs staging environment tests (`stage.konsolifin.net`). |
| `npm run test:staging:headed` | Runs staging tests in a visible browser window. |
| `npm run test:staging:ui` | Launches staging tests in Playwright's interactive UI mode. |
| `npm run test:local` | Runs local environment tests. |
| `npm test` | Runs all test suites (local + staging). |
| `npm run test:headed` | Runs all tests in a visible browser window. |
| `npm run test:ui` | Launches Playwright UI mode for interactive debugging. |
| `npm run test:debug` | Launches Playwright Inspector for step-by-step debugging. |

#### Running with npx directly

```bash
# Run staging project only:
npx playwright test --project=staging

# Run local project only:
npx playwright test --project=chromium
```

---

### 5. Reports and Troubleshooting

#### Opening the HTML Report

```bash
npm run report
# or
npx playwright show-report
```

#### Viewing Trace Files

```bash
npx playwright show-trace test-results/<test-directory>/trace.zip
```

---

### 6. Recording and Writing New Tests

Record browser actions using Playwright Codegen:

```bash
# Against staging:
npx playwright codegen https://stage.konsolifin.net

# Against local:
npx playwright codegen https://web.konsolifin.orb.local/
```

**Tips for writing KonsoliFIN tests:**
1. **Login Route:** Due to SimpleSAMLphp SSO, standard login forms require appending `?showcore`: `/user/login?showcore`.
2. **Cookie Banner:** Dismiss the InMobi Choice CMP cookie banner if present:
   ```javascript
   const cookieButton = page.getByRole('button', { name: 'HYVÄKSY' });
   if (await cookieButton.isVisible({ timeout: 3000 }).catch(() => false)) {
       await cookieButton.click();
   }
   ```
