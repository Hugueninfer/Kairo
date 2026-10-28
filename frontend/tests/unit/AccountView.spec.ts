import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import QRCode from 'qrcode'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { authApi } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import AccountView from '@/views/account/AccountView.vue'

async function mountView(twoFactorEnabled = false) {
  setActivePinia(createPinia())
  useAuthStore().user = { id: 1, name: 'Ana', email: 'ana@kairo.test', email_verified: true, two_factor_enabled: twoFactorEnabled, created_at: null }
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  const wrapper = mount(AccountView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('AccountView', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('liga o login em duas etapas: senha, QR code, código e códigos de recuperação', async () => {
    const start = vi.spyOn(authApi, 'startTwoFactor').mockResolvedValue({ secret: 'ABC', otpauth_url: 'otpauth://totp/Kairo:ana?secret=ABC' })
    vi.spyOn(QRCode, 'toDataURL').mockResolvedValue('data:image/png;base64,qr' as never)
    const confirm = vi.spyOn(authApi, 'confirmTwoFactor').mockResolvedValue(['aaaaa-bbbbb', 'ccccc-ddddd'])
    const wrapper = await mountView()

    await wrapper.get('#confirm_password').setValue('SenhaForte123')
    await wrapper.get('[data-test="two-factor"] form').trigger('submit')
    await flushPromises()

    expect(start).toHaveBeenCalledWith('SenhaForte123')
    expect(wrapper.get('[data-test="qr"]').attributes('src')).toBe('data:image/png;base64,qr')

    await wrapper.get('#two_factor_code').setValue('123 456')
    await wrapper.get('[data-test="two-factor"] form').trigger('submit')
    await flushPromises()

    expect(confirm).toHaveBeenCalledWith('123456')
    expect(wrapper.get('[data-test="recovery-codes"]').text()).toContain('aaaaa-bbbbb')
    expect(wrapper.get('[data-test="two-factor-status"]').text()).toBe('Ligado')
  })

  it('desliga com a senha', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const disable = vi.spyOn(authApi, 'disableTwoFactor').mockResolvedValue()
    const wrapper = await mountView(true)

    await wrapper.get('#confirm_password').setValue('SenhaForte123')
    await wrapper.get('[data-test="disable-two-factor"]').trigger('click')
    await flushPromises()

    expect(disable).toHaveBeenCalledWith('SenhaForte123')
    expect(wrapper.get('[data-test="two-factor-status"]').text()).toBe('Desligado')
  })

  it('troca a senha e limpa o formulário', async () => {
    const change = vi.spyOn(authApi, 'changePassword').mockResolvedValue()
    const wrapper = await mountView()

    await wrapper.get('#current_password').setValue('SenhaForte123')
    await wrapper.get('#new_password').setValue('NovaSenha456')
    await wrapper.get('#new_password_confirmation').setValue('NovaSenha456')
    await wrapper.get('[data-test="password-form"]').trigger('submit')
    await flushPromises()

    expect(change).toHaveBeenCalledWith({ current_password: 'SenhaForte123', password: 'NovaSenha456', password_confirmation: 'NovaSenha456' })
    expect(wrapper.text()).toContain('Senha alterada.')
    expect((wrapper.get('#current_password').element as HTMLInputElement).value).toBe('')
  })
})
