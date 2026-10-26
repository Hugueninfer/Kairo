import { flushPromises, mount } from '@vue/test-utils'
import { AxiosError, AxiosHeaders } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { authApi } from '@/services/auth'
import LoginView from '@/views/auth/LoginView.vue'

function apiError(status: number, data: unknown): AxiosError {
  const headers = new AxiosHeaders()
  return new AxiosError('erro', String(status), { headers }, null, {
    status,
    statusText: '',
    headers: {},
    config: { headers },
    data,
  })
}

async function mountLogin() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div>painel</div>' } },
      { path: '/login', component: LoginView },
      { path: '/cadastro', component: { template: '<div />' } },
      { path: '/esqueci-a-senha', component: { template: '<div />' } },
    ],
  })
  await router.push('/login')
  const wrapper = mount(LoginView, { global: { plugins: [router] } })
  return { wrapper, router }
}

describe('LoginView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('mostra a mensagem da API quando as credenciais são inválidas', async () => {
    vi.spyOn(authApi, 'login').mockRejectedValue(
      apiError(422, { message: 'E-mail ou senha inválidos.', code: 'invalid_credentials' }),
    )
    const { wrapper } = await mountLogin()

    await wrapper.find('#email').setValue('ana@kairo.test')
    await wrapper.find('#password').setValue('errada')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-test="form-alert"]').text()).toBe('E-mail ou senha inválidos.')
  })

  it('vai para o painel depois de entrar', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({
      id: 1,
      name: 'Ana',
      email: 'ana@kairo.test',
      email_verified: true,
      created_at: null,
    })
    const { wrapper, router } = await mountLogin()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/')
  })
})
