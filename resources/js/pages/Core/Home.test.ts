import { describe, expect, it } from 'vitest'
import { mountWithUi } from '@/test/mountWithUi'
import Home from './Home.vue'

describe('Core/Home', () => {
  it('displays the application name inside Nuxt UI components', async () => {
    const wrapper = await mountWithUi(Home, { props: { appName: 'famApp' } })

    expect(wrapper.findComponent({ name: 'App' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'Card' }).exists()).toBe(true)
    expect(wrapper.find('h1').text()).toBe('famApp')
  })
})
