import { describe, expect, it, vi } from 'vitest'
import type { VisitOptions } from '@inertiajs/core'
import { router } from '@inertiajs/vue3'
import { mountWithUi } from '@/test/mountWithUi'
import type { FailedJob } from '@/types/admin'
import FailedJobsTable from './FailedJobsTable.vue'

const job: FailedJob = {
  uuid: '0b5a9a8e-6c1f-4a8e-9a43-1f2e3d4c5b6a',
  queue: 'mail',
  connection: 'database',
  job: 'App\\Notifications\\Auth\\SetPasswordNotification',
  exception: 'RuntimeException: SMTP refused',
  failedAt: '2026-11-18T11:00:00Z',
}

function buttonIn(root: ParentNode, label: string): HTMLButtonElement | undefined {
  return [...root.querySelectorAll('button')].find((button) => button.textContent?.trim() === label)
}

async function mountTable() {
  return mountWithUi(FailedJobsTable, { props: { failedJobs: [job] }, attachTo: document.body })
}

describe('FailedJobsTable', () => {
  it('asks for a confirmation before retrying a job', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountTable()

    await wrapper.findAll('button').find((button) => button.text() === 'Relancer')?.trigger('click')

    expect(post).not.toHaveBeenCalled()
    await vi.waitFor(() => expect(document.body.textContent).toContain('Relancer le job'))
    expect(document.body.textContent).toContain('SetPasswordNotification')

    buttonIn(document.querySelector('[role="dialog"]')!, 'Relancer')?.click()

    expect(post).toHaveBeenCalledWith(
      '/admin/failed-jobs/0b5a9a8e-6c1f-4a8e-9a43-1f2e3d4c5b6a/retry',
      {},
      expect.objectContaining({ preserveScroll: true }),
    )
  })

  it('asks for a confirmation before deleting a job', async () => {
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {})
    const wrapper = await mountTable()

    await wrapper.findAll('button').find((button) => button.text() === 'Supprimer')?.trigger('click')

    expect(destroy).not.toHaveBeenCalled()
    await vi.waitFor(() => expect(document.body.textContent).toContain('Supprimer le job en échec'))

    buttonIn(document.querySelector('[role="dialog"]')!, 'Supprimer')?.click()

    expect(destroy).toHaveBeenCalledWith(
      '/admin/failed-jobs/0b5a9a8e-6c1f-4a8e-9a43-1f2e3d4c5b6a',
      expect.objectContaining({ preserveScroll: true }),
    )
  })

  it('sends nothing when the confirmation is cancelled', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {})
    const wrapper = await mountTable()

    await wrapper.findAll('button').find((button) => button.text() === 'Relancer')?.trigger('click')
    await vi.waitFor(() => expect(document.querySelector('[role="dialog"]')).not.toBeNull())

    buttonIn(document.querySelector('[role="dialog"]')!, 'Annuler')?.click()

    await vi.waitFor(() => expect(document.querySelector('[role="dialog"]')).toBeNull())
    expect(post).not.toHaveBeenCalled()
  })

  it('closes the confirmation once the request succeeds and blocks a second click meanwhile', async () => {
    let options: VisitOptions | undefined
    const post = vi.spyOn(router, 'post').mockImplementation((_url, _data, visitOptions?: VisitOptions) => {
      options = visitOptions
      options?.onStart?.({} as never)
    })
    const wrapper = await mountTable()

    await wrapper.findAll('button').find((button) => button.text() === 'Relancer')?.trigger('click')
    await vi.waitFor(() => expect(document.querySelector('[role="dialog"]')).not.toBeNull())
    const confirm = () => buttonIn(document.querySelector('[role="dialog"]')!, 'Relancer')

    confirm()?.click()
    confirm()?.click()
    expect(post).toHaveBeenCalledOnce()

    options?.onSuccess?.({} as never)
    options?.onFinish?.({} as never)
    await vi.waitFor(() => expect(document.querySelector('[role="dialog"]')).toBeNull())
  })
})
