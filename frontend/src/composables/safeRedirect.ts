/**
 * Aceita só caminhos internos ("/algo"), nunca URLs externas ou "//dominio",
 * para o parâmetro ?redirect= não virar um redirecionamento aberto.
 */
export function safeRedirect(value: unknown, fallback = '/'): string {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : fallback
}
