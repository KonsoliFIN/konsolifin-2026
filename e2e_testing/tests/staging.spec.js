// @ts-check
const { test, expect } = require('@playwright/test');

// Varmistetaan, että tämän tiedoston testit käyttävät oletuksena staging-ympäristön osoitetta
test.use({
    baseURL: process.env.STAGING_BASE_URL || 'https://stage.konsolifin.net',
});

test.describe('KonsoliFIN Staging - Julkisen sivuston savutestit', () => {

    test.beforeEach(async ({ page }) => {
        // Avataan etusivu ja kuitataan evästekysely tarvittaessa
        await page.goto('/');
        await page.waitForTimeout(1000);
        const cookieButton = page.getByRole('button', { name: 'HYVÄKSY' });
        if (await cookieButton.isVisible({ timeout: 3000 }).catch(() => false)) {
            await cookieButton.click();
        }
    });

    test('Etusivu latautuu ja sisältää brändäyksen', async ({ page }) => {
        await expect(page).toHaveTitle(/(KonsoliFIN|Kehitysympäristö|pelaamisen keskipisteeseen)/i);
        const logo = page.locator('.site-branding__logo, a[rel="home"], a:has(img[alt*="KonsoliFIN"])').first();
        await expect(logo).toBeVisible();
    });

    test('Päänavigaation osiot toimivat', async ({ page }) => {
        // Pelit
        await page.getByRole('link', { name: 'Pelit', exact: true }).first().click();
        await expect(page).toHaveURL(/.*pelit/);
        await expect(page.locator('main')).toBeVisible();

        // Arvostelut
        await page.getByRole('link', { name: 'Arvostelut' }).first().click();
        await expect(page).toHaveURL(/.*peliarvostelut/);
        await expect(page.locator('main')).toBeVisible();

        // Uutiset
        await page.getByRole('link', { name: 'Uutiset' }).first().click();
        await expect(page).toHaveURL(/.*uutiset/);
        await expect(page.locator('main')).toBeVisible();

        // Podcastit
        await page.getByRole('link', { name: 'Podcastit' }).first().click();
        await expect(page).toHaveURL(/.*podcastit/);
        await expect(page.locator('main')).toBeVisible();

        // Muut jutut
        await page.getByRole('link', { name: 'Muut jutut' }).first().click();
        await expect(page).toHaveURL(/.*jutut/);
        await expect(page.locator('main')).toBeVisible();

        // Takaisin etusivulle brändilogosta
        await page.locator('.site-branding__logo, a[rel="home"]').first().click();
        await expect(page).toHaveURL(/\/$/);
    });

    test('Artikkelin lukunäkymä avautuu', async ({ page }) => {
        // Etsitään ensimmäinen artikkelin otsikkolinkki
        const articleLink = page.locator('main article h3 a').first();
        await expect(articleLink).toBeVisible();
        await articleLink.click();

        // Varmistetaan, että siirryttiin artikkelisivulle ja otsikko (h1) on näkyvissä
        await expect(page).toHaveURL(/\/(artikkeli|uutinen|peliarvostelu|blogi)\//);
        await expect(page.locator('h1')).toBeVisible();
        await expect(page.locator('article, .node--type-article, .node--view-mode-full').first()).toBeVisible();
    });

    test('Peliarvostelujen listaussivu latautuu', async ({ page }) => {
        const response = await page.goto('/peliarvostelut');
        expect(response?.status()).toBe(200);
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('article, .views-row').first()).toBeVisible();
    });
});

test.describe('KonsoliFIN Staging - Ylläpito ja kirjautuminen', () => {

    test.beforeEach(async ({ page }) => {
        // Avataan etusivu ja kuitataan evästekysely tarvittaessa
        await page.goto('/');
        await page.waitForTimeout(1000);
        const cookieButton = page.getByRole('button', { name: 'HYVÄKSY' });
        if (await cookieButton.isVisible({ timeout: 3000 }).catch(() => false)) {
            await cookieButton.click();
        }
    });


    test('Kirjautumissivun perusnäkymä ja kentät ovat saatavilla (?showcore)', async ({ page }) => {
        await page.goto('/user/login?showcore');

        // Varmistetaan, että perinteisen kirjautumislomakkeen kentät ovat olemassa
        const usernameField = page.getByRole('textbox', { name: /Käyttäjätunnus/i });
        const passwordField = page.getByRole('textbox', { name: /Salasana/i });
        const loginButton = page.getByRole('button', { name: 'Kirjaudu sisään' });

        await expect(usernameField).toBeVisible();
        await expect(passwordField).toBeVisible();
        await expect(loginButton).toBeVisible();
    });

    test('Staging-tunnuksilla kirjautuminen ja hallintapaneelin tarkistus', async ({ page }) => {
        const stagingUser = process.env.STAGING_USERNAME;
        const stagingPass = process.env.STAGING_PASSWORD;

        // Ohitetaan testi hallitusti, mikäli salaisia kirjautumistietoja ei ole asetettu .env-tiedostoon
        test.skip(
            !stagingUser || !stagingPass,
            'Ohitettu: STAGING_USERNAME ja/tai STAGING_PASSWORD puuttuvat .env-tiedostosta.'
        );

        await page.goto('/user/login?showcore');

        const usernameField = page.getByRole('textbox', { name: /Käyttäjätunnus/i });
        const passwordField = page.getByRole('textbox', { name: /Salasana/i });
        const loginButton = page.getByRole('button', { name: 'Kirjaudu sisään' });

        await usernameField.fill(stagingUser || '');
        await passwordField.fill(stagingPass || '');
        await loginButton.click();

        // Varmistetaan onnistunut sisäänkirjautuminen (ei virheilmoitusta, ja ylläpito-/käyttäjälinkit näkyvissä)
        await expect(page.locator('.messages--error, [data-drupal-messages] .messages--error')).toHaveCount(0);

        // Tarkistetaan pääsy sisältöhallintaan
        await page.goto('/admin/content');
        await expect(page.locator('h1')).toBeVisible();

        // Tarkistetaan pääsy raportteihin
        await page.goto('/admin/reports/status');
        await expect(page.locator('h1')).toBeVisible();
    });

});

test.describe('KonsoliFIN Staging - Forum-tunnuksilla toimiminen', () => {

    test.beforeEach(async ({ page }) => {
        const stagingUser = process.env.FORUM_USERNAME;
        const stagingPass = process.env.FORUM_PASSWORD;

        // Ohitetaan testi hallitusti, mikäli salaisia kirjautumistietoja ei ole asetettu .env-tiedostoon
        test.skip(
            !stagingUser || !stagingPass,
            'Ohitettu: FORUM_USERNAME ja/tai FORUM_PASSWORD puuttuvat .env-tiedostosta.'
        );

        // Avataan etusivu ja kuitataan evästekysely tarvittaessa
        await page.goto('/');
        await page.waitForTimeout(1000);
        const cookieButtonMain = page.getByRole('button', { name: 'HYVÄKSY' });
        if (await cookieButtonMain.isVisible({ timeout: 3000 }).catch(() => false)) {
            await cookieButtonMain.click();
        }

        await page.goto('/');
        await page.getByRole('button', { name: 'Log in with Forum stage' }).click();

        // Foorumilla tulee todennäköisesti uusi evästekysely
        await page.waitForTimeout(1000);
        const cookieButtonForum = page.getByRole('button', { name: 'HYVÄKSY' });
        if (await cookieButtonForum.isVisible({ timeout: 3000 }).catch(() => false)) {
            await cookieButtonForum.click();
        }

        const usernameField = page.getByRole('textbox', { name: /Käyttäjänimi tai sähkö/i });
        const passwordField = page.getByRole('textbox', { name: /Salasana:/i });
        const loginButton = page.getByRole('button', { name: 'Kirjaudu' });

        await usernameField.click();
        await usernameField.fill(stagingUser || '');
        await passwordField.click();
        await passwordField.fill(stagingPass || '');
        await loginButton.click();

        const authorizeButton = page.getByRole('button', { name: 'Authorize' });
        await authorizeButton.click();
    });

    test('Forum-tunnuksilla kirjautuminen, oman profiilin tarkistus ja uloskirjautuminen', async ({ page }) => {
        await page.getByRole('link', { name: 'Etusivu' }).click();
        await page.getByRole('link', { name: 'Oma käyttäjätilini' }).click();
        await page.getByRole('link', { name: 'Kirjaudu ulos' }).click();

    });

    test('Forum-tunnuksilla kirjautuminen ja uuden uutisen kirjoittaminen', async ({ page }) => {
        await page.getByRole('link', { name: 'Työpöytä' }).click();
        await page.getByRole('link', { name: 'Uutinen', exact: true }).click();

        const now = new Date();
        const pad = (/** @type {number} */ n) => String(n).padStart(2, '0');
        const timestamp = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        await page.locator('#edit-title-0-value').fill(`Uutinen ${timestamp}`);

        const bodyEditor = page.locator('#edit-body-wrapper .ck-editor__editable[contenteditable="true"]');
        await bodyEditor.click();
        await bodyEditor.fill('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. .');

        await page.locator('#edit-workflow-uutisputki-tyon-alla').click();

        const statusMessage = page.locator('.messages--status, [data-drupal-messages]').first();
        await expect(statusMessage).toContainText(new RegExp(`Uutinen.*${timestamp}.*on lisätty\\.`));

        const createdLink = statusMessage.getByRole('link', { name: new RegExp(timestamp) });
        await expect(createdLink).toBeVisible();
        await expect(createdLink).toHaveAttribute('href', /\/uutinen\/.+/);

        await page.getByRole('link', { name: 'Muokkaa' }).click();

        await page.locator('#edit-field-hero-open-button').click();
        await page.locator('input[name^="media_library_select_form["]').first().check();
        await page.getByRole('button', { name: 'Insert selected' }).click();
        await page.waitForTimeout(1000);
        await page.getByRole('button', { name: 'Julkaise' }).click();
    });

});
