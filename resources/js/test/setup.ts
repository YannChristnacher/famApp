import { afterEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { enableAutoUnmount } from '@vue/test-utils'

enableAutoUnmount(afterEach)

afterEach(async () => {
  const { resetTestPage } = await import('./testPage')
  resetTestPage()
  vi.restoreAllMocks()
})

// Inertia's <Head> needs a live Inertia app; in unit tests it renders nothing.
// usePage() returns a static, mutable page (see testPage.ts).
vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const { testPage } = await import('./testPage')

  return {
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: defineComponent({ name: 'InertiaHead', render: () => null }),
    usePage: () => testPage,
  }
})

// Named routes normally injected by the @routes Blade directive (Ziggy).
globalThis.Ziggy = {
  url: 'http://localhost',
  port: null,
  defaults: {},
  routes: {
    home: { uri: '/', methods: ['GET', 'HEAD'] },
    logout: { uri: 'logout', methods: ['POST'] },
    search: { uri: 'search', methods: ['GET', 'HEAD'] },
    login: { uri: 'login', methods: ['GET', 'HEAD'] },
    'login.store': { uri: 'login', methods: ['POST'] },
    'password.request': { uri: 'forgot-password', methods: ['GET', 'HEAD'] },
    'password.email': { uri: 'forgot-password', methods: ['POST'] },
    'password.update': { uri: 'reset-password', methods: ['POST'] },
    'account-request.create': { uri: 'account-request', methods: ['GET', 'HEAD'] },
    'account-request.store': { uri: 'account-request', methods: ['POST'] },
    'library.index': { uri: 'library', methods: ['GET', 'HEAD'] },
    'library.bookshelves.index': { uri: 'library/bookshelves', methods: ['GET', 'HEAD'] },
    'library.bookshelves.store': { uri: 'library/bookshelves', methods: ['POST'] },
    'library.bookshelves.update': {
      uri: 'library/bookshelves/{bookshelf}',
      methods: ['PUT', 'PATCH'],
      parameters: ['bookshelf'],
    },
    'library.bookshelves.destroy': {
      uri: 'library/bookshelves/{bookshelf}',
      methods: ['DELETE'],
      parameters: ['bookshelf'],
    },
    'library.bookshelves.move': {
      uri: 'library/bookshelves/{bookshelf}/move',
      methods: ['PATCH'],
      parameters: ['bookshelf'],
    },
    'library.books.index': { uri: 'library/books', methods: ['GET', 'HEAD'] },
    'library.books.create': { uri: 'library/books/create', methods: ['GET', 'HEAD'] },
    'library.books.store': { uri: 'library/books', methods: ['POST'] },
    'library.books.show': { uri: 'library/books/{book}', methods: ['GET', 'HEAD'], parameters: ['book'] },
    'library.books.edit': { uri: 'library/books/{book}/edit', methods: ['GET', 'HEAD'], parameters: ['book'] },
    'library.books.update': { uri: 'library/books/{book}', methods: ['PUT', 'PATCH'], parameters: ['book'] },
    'library.books.destroy': { uri: 'library/books/{book}', methods: ['DELETE'], parameters: ['book'] },
    'library.google-books.lookup': { uri: 'library/google-books', methods: ['GET', 'HEAD'] },
    'garage.index': { uri: 'garage', methods: ['GET', 'HEAD'] },
    'garage.shelves.index': { uri: 'garage/shelves', methods: ['GET', 'HEAD'] },
    'garage.shelves.store': { uri: 'garage/shelves', methods: ['POST'] },
    'garage.shelves.update': { uri: 'garage/shelves/{shelf}', methods: ['PUT', 'PATCH'], parameters: ['shelf'] },
    'garage.shelves.destroy': { uri: 'garage/shelves/{shelf}', methods: ['DELETE'], parameters: ['shelf'] },
    'garage.shelves.move': { uri: 'garage/shelves/{shelf}/move', methods: ['PATCH'], parameters: ['shelf'] },
    'garage.tools.index': { uri: 'garage/tools', methods: ['GET', 'HEAD'] },
    'garage.tools.create': { uri: 'garage/tools/create', methods: ['GET', 'HEAD'] },
    'garage.tools.store': { uri: 'garage/tools', methods: ['POST'] },
    'garage.tools.show': { uri: 'garage/tools/{tool}', methods: ['GET', 'HEAD'], parameters: ['tool'] },
    'garage.tools.edit': { uri: 'garage/tools/{tool}/edit', methods: ['GET', 'HEAD'], parameters: ['tool'] },
    'garage.tools.update': { uri: 'garage/tools/{tool}', methods: ['PUT', 'PATCH'], parameters: ['tool'] },
    'garage.tools.destroy': { uri: 'garage/tools/{tool}', methods: ['DELETE'], parameters: ['tool'] },
    'family.index': { uri: 'family', methods: ['GET', 'HEAD'] },
    'family.members.show': { uri: 'family/{member}', methods: ['GET', 'HEAD'], parameters: ['member'] },
    'admin.account-requests.index': { uri: 'admin/account-requests', methods: ['GET', 'HEAD'] },
    'admin.account-requests.account.create': {
      uri: 'admin/account-requests/{accountRequest}/account',
      methods: ['GET', 'HEAD'],
      parameters: ['accountRequest'],
    },
    'admin.account-requests.account.store': {
      uri: 'admin/account-requests/{accountRequest}/account',
      methods: ['POST'],
      parameters: ['accountRequest'],
    },
    'admin.account-requests.reject': {
      uri: 'admin/account-requests/{accountRequest}/reject',
      methods: ['POST'],
      parameters: ['accountRequest'],
    },
    'admin.control': { uri: 'admin/control', methods: ['GET', 'HEAD'] },
    'admin.failed-jobs.retry': { uri: 'admin/failed-jobs/{uuid}/retry', methods: ['POST'], parameters: ['uuid'] },
    'admin.failed-jobs.destroy': { uri: 'admin/failed-jobs/{uuid}', methods: ['DELETE'], parameters: ['uuid'] },
  },
}
