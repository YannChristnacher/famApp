import { expect, test } from '@playwright/test'

const adminEmail = process.env.ADMIN_EMAIL ?? 'admin@famapp.test'
const adminPassword = process.env.ADMIN_PASSWORD ?? 'mot-de-passe-e2e'

/** A 1×1 PNG: the server resizes and re-encodes it as a JPEG. */
const photo = {
  name: 'perceuse.png',
  mimeType: 'image/png',
  buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64'),
}

test.describe('Ajout d’un outil', () => {
  test('crée un outil avec une nouvelle marque, un accessoire, une étagère et une photo', async ({ page }) => {
    // Unique names: the e2e database is kept between local runs.
    const suffix = Date.now().toString(36)
    const shelf = `Électroportatif ${suffix}`
    const name = `Perceuse ${suffix}`
    const brand = `Marque ${suffix}`

    await page.goto('/login')
    await page.getByLabel('Adresse email').fill(adminEmail)
    await page.getByLabel('Mot de passe', { exact: true }).fill(adminPassword)
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page).toHaveURL(/\/$/)

    await page.getByRole('link', { name: 'Garage' }).click()
    await expect(page).toHaveURL(/\/garage\/tools$/)

    await page.getByRole('link', { name: 'Mes étagères' }).click()
    await page.getByLabel('Nouvelle étagère').fill(shelf)
    await page.getByRole('button', { name: 'Ajouter' }).click()
    await expect(page.getByTestId('tool-shelf').filter({ hasText: shelf })).toBeVisible()

    await page.getByRole('link', { name: 'Mes outils' }).click()
    await page.getByRole('link', { name: 'Ajouter un outil' }).first().click()
    await expect(page).toHaveURL(/\/garage\/tools\/create$/)

    await page.getByLabel('Nom', { exact: true }).fill(name)

    await page.getByTestId('brand-select').click()
    await page.getByPlaceholder('Rechercher ou créer…').fill(brand)
    await page.getByRole('option', { name: `Créer "${brand}"` }).click()
    await expect(page.getByTestId('brand-select')).toContainText(brand)

    await page.getByTestId('shelf-select').click()
    await page.getByRole('option', { name: shelf }).click()
    await page.keyboard.press('Escape')
    await expect(page.getByTestId('shelf-select')).toContainText(shelf)

    await page.getByTestId('accessory-add').click()
    await page.getByLabel('Accessoire 1', { exact: true }).fill('Mallette')

    await page.locator('input[type="file"]').setInputFiles(photo)
    await expect(page.getByTestId('photo-row')).toHaveCount(1)

    await page.getByRole('button', { name: 'Ajouter l’outil' }).click()

    await expect(page).toHaveURL(/\/garage\/tools\/\d+$/)
    await expect(page.getByRole('heading', { name, level: 2 })).toBeVisible()
    await expect(page.getByTestId('tool-brand')).toHaveText(brand)
    await expect(page.getByTestId('tool-accessory')).toHaveText('Mallette')
    await expect(page.getByTestId('tool-shelf')).toHaveText(shelf)

    await page.getByRole('link', { name: 'Mes outils' }).click()
    const card = page.getByTestId('tool').filter({ hasText: name })
    await expect(card).toBeVisible()
    await expect(card.getByTestId('tool-brand')).toHaveText(brand)
    const thumbnail = card.locator('img')
    await expect(thumbnail).toHaveAttribute('src', /\/storage\/tools\/[A-Za-z0-9]{40}\.jpg$/)
    // The resized photo is really served (public/storage link).
    await expect.poll(() => thumbnail.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth)).toBe(1)
  })
})
