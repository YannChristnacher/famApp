import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import Home from './Home.vue'

describe('Core/Home', () => {
  it('displays the application name', async () => {
    const wrapper = await mountWithUi(Home, { props: { appName: 'famApp' } })

    expect(wrapper.text()).toContain('famApp')
  })
})
