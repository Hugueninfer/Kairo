import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { rolesApi } from '@/services/roles'
import type { PermissionGroup, Role } from '@/types/api'
import RolesView from '@/views/companies/RolesView.vue'

const catalog: PermissionGroup[] = [
  {
    group: 'Equipe',
    permissions: [
      { key: 'members.view', label: 'Ver membros' },
      { key: 'members.invite', label: 'Convidar membros' },
    ],
  },
]

function role(id: number, name: string, permissions: string[], extra: Partial<Role> = {}): Role {
  return { id, name, system_key: null, is_system: false, is_locked: false, permissions, members_count: 0, ...extra }
}

async function mountView() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { template: '<div />' } }] })
  await router.push('/')
  const wrapper = mount(RolesView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('RolesView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
    vi.spyOn(rolesApi, 'catalog').mockResolvedValue(catalog)
  })

  it('mostra o dono com tudo marcado e travado', async () => {
    vi.spyOn(rolesApi, 'list').mockResolvedValue([
      role(1, 'Dono', [], { system_key: 'owner', is_system: true, is_locked: true }),
      role(2, 'Membro', ['members.view'], { system_key: 'member', is_system: true }),
    ])

    const wrapper = await mountView()
    const ownerInvite = wrapper.get('[data-test="perm-1-members.invite"]').element as HTMLInputElement
    const memberInvite = wrapper.get('[data-test="perm-2-members.invite"]').element as HTMLInputElement

    expect(ownerInvite.checked).toBe(true)
    expect(ownerInvite.disabled).toBe(true)
    expect(memberInvite.checked).toBe(false)
  })

  it('salva só os papéis alterados', async () => {
    vi.spyOn(rolesApi, 'list').mockResolvedValue([
      role(2, 'Membro', ['members.view'], { system_key: 'member', is_system: true }),
      role(3, 'Financeiro', ['members.view'], { system_key: 'finance', is_system: true }),
    ])
    const update = vi.spyOn(rolesApi, 'update').mockResolvedValue(role(2, 'Membro', ['members.invite', 'members.view']))

    const wrapper = await mountView()
    const save = wrapper.get('[data-test="save-permissions"]')
    expect((save.element as HTMLButtonElement).disabled).toBe(true)

    await wrapper.get('[data-test="perm-2-members.invite"]').setValue(true)
    await save.trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    expect(update).toHaveBeenCalledWith(2, { name: 'Membro', permissions: ['members.view', 'members.invite'] })
    expect(wrapper.text()).toContain('Permissões salvas.')
  })
})
