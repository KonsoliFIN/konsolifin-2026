// tests/drupal-smoke.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Drupal Core Päivityksen Regressiotestit', () => {


    test('test', async ({ page }) => {
        await page.goto('/');

        if (await page.getByRole('button', { name: 'HYVÄKSY' }).isVisible()) {
            page.getByRole('button', { name: 'HYVÄKSY' }).click();
        }

        await page.getByRole('link', { name: 'Etusivu' }).click();
        await page.getByRole('link', { name: 'Tämä klassikko ei näytä enää' }).click();
        await page.getByRole('link', { name: 'KonsoliFIN' }).click();
        await page.getByRole('link', { name: 'Pelit', exact: true }).click();
        await page.getByRole('link', { name: 'Arvostelut' }).click();
        await page.getByRole('link', { name: 'Uutiset' }).click();
        await page.getByRole('link', { name: 'Podcastit' }).click();
        await page.getByRole('link', { name: 'Muut jutut' }).click();
        await page.getByRole('link', { name: 'KonsoliFIN', exact: true }).click();
        await page.getByRole('link', { name: 'Do not be alarmed — vain' }).click();
        await page.getByRole('link', { name: 'Jyri Jokinen' }).click();
        await page.getByRole('link', { name: 'Peliala esitti toivelistan' }).click();
        await page.getByRole('link', { name: 'KonsoliFIN' }).click();
        await page.goto('/user/login?showcore');
        await page.getByRole('textbox', { name: 'Käyttäjätunnus *' }).click();
        await page.getByRole('textbox', { name: 'Käyttäjätunnus *' }).fill(process.env.LOCAL_USERNAME || 'admin');
        await page.getByRole('textbox', { name: 'Salasana *' }).click();
        await page.getByRole('textbox', { name: 'Salasana *' }).fill(process.env.LOCAL_PASSWORD || 'admin');
        await page.getByRole('button', { name: 'Kirjaudu sisään' }).click();
        await page.getByRole('link', { name: 'Sisältö', exact: true }).click();
        await page.getByRole('link', { name: 'Lohkot' }).click();
        await page.getByRole('link', { name: 'Kommentit' }).click();
        await page.getByRole('link', { name: 'Tiedostot' }).click();
        await page.getByRole('link', { name: 'Media' }).click();
        await page.getByRole('link', { name: 'Rakenne' }).click();
        await page.getByRole('link', { name: 'Asetukset' }).click();
        await page.getByRole('link', { name: 'Käyttäjät', exact: true }).click();
        await page.getByRole('link', { name: 'Raportit' }).click();
        await page.getByRole('link', { name: 'Tilanneraportti' }).click();
        await page.getByRole('link', { name: 'Raportit', description: 'Tarkastele raportteja, saatavilla olevia päivityksiä ja virheitä.' }).click();
        await page.getByRole('link', { name: 'Viimeisimmät lokimerkinnät' }).click();
        await page.getByRole('link', { name: 'Asetukset' }).click();
        await page.getByRole('link', { name: 'Games Page Settings' }).click();
        await page.getByRole('link', { name: 'Etusivu' }).click();
        await page.getByRole('link', { name: 'KonsoliFIN', exact: true }).click();
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
    })
});
