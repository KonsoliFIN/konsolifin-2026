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
        await page.getByRole('textbox', { name: 'Salasana *' }).click();
        await page.getByRole('textbox', { name: 'Salasana *' }).fill('password');
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
    })
});
