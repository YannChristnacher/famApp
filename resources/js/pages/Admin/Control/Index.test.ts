import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import { testPage, testUser } from '@/test/testPage'
import type { ControlCounts, ErrorLogEntry, FailedJobs, GoogleBooksActivity, RecentAddition, RecentLogin } from '@/types/admin'
import Index from './Index.vue'

const counts: ControlCounts = { members: 4, invited: 2, books: 31, tools: 12, bookshelves: 5, toolShelves: 3, brands: 7 }

const additions: RecentAddition[] = [
  { type: 'book', id: 3, label: 'Le Petit Prince', owner: 'Marie', createdAt: '2026-11-18T11:55:00Z', url: '/library/books/3' },
  { type: 'tool', id: 8, label: 'Perceuse', owner: 'Paul', createdAt: '2026-11-17T12:00:00Z', url: '/garage/tools/8' },
]

const logins: RecentLogin[] = [{ id: 2, name: 'Marie', lastLoginAt: '2026-11-18T09:00:00Z' }]

const failedJobs: FailedJobs = {
  total: 1,
  latest: [{
    uuid: '0b5a9a8e-6c1f-4a8e-9a43-1f2e3d4c5b6a',
    queue: 'mail',
    connection: 'database',
    job: 'App\\Notifications\\Auth\\SetPasswordNotification',
    exception: 'RuntimeException: SMTP refused',
    failedAt: '2026-11-18T11:00:00Z',
  }],
}

const errors: ErrorLogEntry[] = [
  { level: 'critical', message: 'Database down <script>alert(1)</script>', loggedAt: '2026-11-18T10:00:00Z' },
]

const googleBooks: GoogleBooksActivity = {
  periods: [
    { key: 'day', label: '24 dernières heures', total: 3, averageDurationMs: 300, statuses: [{ status: 'found', label: 'Trouvé', count: 2 }, { status: 'timeout', label: 'Délai dépassé', count: 1 }] },
    { key: 'week', label: '7 derniers jours', total: 0, averageDurationMs: null, statuses: [] },
  ],
  failures: [{ id: 5, type: 'Couverture', query: 'books.google.com/books/content', status: 'refused', statusLabel: 'Refusé', httpStatus: null, member: 'Marie', createdAt: '2026-11-18T10:30:00Z' }],
}

const emptyProps: {
  pendingAccountRequests: number
  counts: ControlCounts
  recentAdditions: RecentAddition[]
  recentLogins: RecentLogin[]
  failedJobs: FailedJobs
  errors: ErrorLogEntry[]
  googleBooks: GoogleBooksActivity
} = {
  pendingAccountRequests: 0,
  counts: { members: 1, invited: 0, books: 0, tools: 0, bookshelves: 0, toolShelves: 0, brands: 0 },
  recentAdditions: [],
  recentLogins: [],
  failedJobs: { total: 0, latest: [] },
  errors: [],
  googleBooks: {
    periods: [
      { key: 'day', label: '24 dernières heures', total: 0, averageDurationMs: null, statuses: [] },
      { key: 'week', label: '7 derniers jours', total: 0, averageDurationMs: null, statuses: [] },
    ],
    failures: [],
  },
}

const fullProps = { pendingAccountRequests: 3, counts, recentAdditions: additions, recentLogins: logins, failedJobs, errors, googleBooks }

function section(wrapper: Awaited<ReturnType<typeof mountWithUi>>, testId: string) {
  return wrapper.get(`[data-testid="${testId}"]`)
}

describe('Admin/Control/Index', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-18T12:00:00Z'))
    testPage.props.auth.user = { ...testUser, role: 'admin' }
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders in the dashboard layout', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })

    const layout = wrapper.findComponent({ name: 'DashboardLayout' })
    expect(layout.props('title')).toBe('Contrôle')
    expect(layout.props('breadcrumb')).toEqual([{ label: 'Administration' }, { label: 'Contrôle' }])
  })

  it('shows the pending requests with a link to their page', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const pending = section(wrapper, 'control-pending-requests')

    expect(pending.text()).toContain('3')
    expect(pending.text()).toContain('Demandes en attente')
    expect(pending.find('a[href="/admin/account-requests"]').exists()).toBe(true)
  })

  it('shows every counter', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const counters = section(wrapper, 'control-counters').text()

    for (const [label, value] of [['Membres', '4'], ['Invitations en attente', '2'], ['Livres', '31'], ['Outils', '12'], ['Étagères de livres', '5'], ['Étagères d\'outils', '3'], ['Marques', '7']]) {
      expect(counters).toContain(label)
      expect(counters).toContain(value)
    }
  })

  it('lists the latest additions with a link to their page and a relative time', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const recent = section(wrapper, 'control-recent-additions')

    expect(recent.find('a[href="/library/books/3"]').text()).toBe('Le Petit Prince')
    expect(recent.find('a[href="/garage/tools/8"]').text()).toBe('Perceuse')
    expect(recent.text()).toContain('Livre')
    expect(recent.text()).toContain('Outil')
    expect(recent.text()).toContain('Marie')
    expect(recent.text()).toContain('il y a 5 minutes')
    expect(recent.text()).toContain('hier')
    expect(recent.find('time[datetime="2026-11-18T11:55:00Z"]').attributes('title')).toContain('novembre 2026')
  })

  it('lists the latest logins', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const recent = section(wrapper, 'control-recent-logins')

    expect(recent.text()).toContain('Marie')
    expect(recent.text()).toContain('il y a 3 heures')
  })

  it('lists the failed jobs', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const jobs = section(wrapper, 'control-failed-jobs')

    expect(jobs.text()).toContain('1 job en échec')
    expect(jobs.text()).toContain('SetPasswordNotification')
    expect(jobs.text()).toContain('mail')
    expect(jobs.text()).toContain('RuntimeException: SMTP refused')
  })

  it('lists the latest errors as plain text', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const errorLog = section(wrapper, 'control-errors')

    expect(errorLog.text()).toContain('Database down <script>alert(1)</script>')
    expect(errorLog.find('script').exists()).toBe(false)
    expect(errorLog.text()).toContain('CRITICAL')
    expect(errorLog.text()).toContain('il y a 2 heures')
  })

  it('shows the Google Books activity', async () => {
    const wrapper = await mountWithUi(Index, { props: fullProps })
    const activity = section(wrapper, 'control-google-books')

    expect(activity.text()).toContain('24 dernières heures')
    expect(activity.text()).toContain('3 appels')
    expect(activity.text()).toContain('300 ms en moyenne')
    expect(activity.text()).toContain('Trouvé')
    expect(activity.text()).toContain('Délai dépassé')
    expect(activity.text()).toContain('7 derniers jours')
    expect(activity.text()).toContain('Aucun appel')
    expect(activity.text()).toContain('Refusé')
    expect(activity.text()).toContain('books.google.com/books/content')
  })

  it('shows an empty state for every empty block', async () => {
    const wrapper = await mountWithUi(Index, { props: emptyProps })

    expect(section(wrapper, 'control-pending-requests').text()).toContain('0')
    expect(section(wrapper, 'control-recent-additions').text()).toContain('Aucun livre ni outil ajouté pour le moment.')
    expect(section(wrapper, 'control-recent-logins').text()).toContain('Aucune connexion enregistrée.')
    expect(section(wrapper, 'control-failed-jobs').text()).toContain('Aucun job en échec.')
    expect(section(wrapper, 'control-errors').text()).toContain('Aucune erreur récente dans le journal.')
    expect(section(wrapper, 'control-google-books').text()).toContain('Aucun échec récent.')
  })
})
