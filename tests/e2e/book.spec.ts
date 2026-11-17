import { expect, test } from '@playwright/test'

const adminEmail = process.env.ADMIN_EMAIL ?? 'admin@famapp.test'
const adminPassword = process.env.ADMIN_PASSWORD ?? 'mot-de-passe-e2e'

test.describe('Ajout d’un livre', () => {
  test('crée un livre rangé sur une étagère et le retrouve dans la bibliothèque', async ({ page }) => {
    // Unique names: the e2e database is kept between local runs.
    const suffix = Date.now().toString(36)
    const bookshelf = `Romans ${suffix}`
    const title = `Le Petit Prince ${suffix}`

    await page.goto('/login')
    await page.getByLabel('Adresse email').fill(adminEmail)
    await page.getByLabel('Mot de passe', { exact: true }).fill(adminPassword)
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page).toHaveURL(/\/$/)

    await page.getByRole('link', { name: 'Bibliothèque' }).click()
    await expect(page).toHaveURL(/\/library\/books$/)

    await page.getByRole('link', { name: 'Mes étagères' }).click()
    await page.getByLabel('Nouvelle étagère').fill(bookshelf)
    await page.getByRole('button', { name: 'Ajouter' }).click()
    await expect(page.getByTestId('bookshelf').filter({ hasText: bookshelf })).toBeVisible()

    await page.getByRole('link', { name: 'Mes livres' }).click()
    await page.getByRole('link', { name: 'Ajouter un livre' }).first().click()
    await expect(page).toHaveURL(/\/library\/books\/create$/)

    await page.getByLabel('Titre').fill(title)
    await page.getByLabel('Auteur').fill('Antoine de Saint-Exupéry')
    await page.getByLabel('ISBN').fill('978-2-07-061275-8')
    await page.getByTestId('bookshelf-select').click()
    await page.getByRole('option', { name: bookshelf }).click()
    await page.keyboard.press('Escape')
    await expect(page.getByTestId('bookshelf-select')).toContainText(bookshelf)
    await page.getByRole('button', { name: 'Ajouter le livre' }).click()

    await expect(page).toHaveURL(/\/library\/books\/\d+$/)
    await expect(page.getByRole('heading', { name: title, level: 2 })).toBeVisible()
    await expect(page.getByTestId('isbn')).toHaveText('9782070612758')
    await expect(page.getByTestId('book-bookshelf')).toHaveText(bookshelf)

    await page.getByRole('link', { name: 'Mes livres' }).click()
    await expect(page.getByTestId('book').filter({ hasText: title })).toBeVisible()

    await page.getByTestId('bookshelf-filter').filter({ hasText: bookshelf }).click()
    await expect(page).toHaveURL(/\/library\/books\?bookshelf=\d+$/)
    await expect(page.getByTestId('book')).toHaveCount(1)
    await expect(page.getByTestId('book')).toContainText(title)
  })
})
