import { expect, test } from '@playwright/test'

const adminEmail = process.env.ADMIN_EMAIL ?? 'admin@famapp.test'
const adminPassword = process.env.ADMIN_PASSWORD ?? 'mot-de-passe-e2e'

test.describe('Connexion', () => {
  test('redirige un visiteur vers la connexion puis l’amène à l’accueil', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login$/)

    await page.getByLabel('Adresse email').fill(adminEmail)
    await page.getByLabel('Mot de passe', { exact: true }).fill(adminPassword)
    await page.getByRole('button', { name: 'Se connecter' }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByText('Bienvenue sur famApp')).toBeVisible()
  })

  test('affiche une erreur en français avec un mauvais mot de passe', async ({ page }) => {
    await page.goto('/login')

    await page.getByLabel('Adresse email').fill(adminEmail)
    await page.getByLabel('Mot de passe', { exact: true }).fill('mauvais-mot-de-passe')
    await page.getByRole('button', { name: 'Se connecter' }).click()

    await expect(page.getByText('Ces identifiants ne correspondent pas à nos enregistrements.')).toBeVisible()
    await expect(page).toHaveURL(/\/login$/)
  })
})
