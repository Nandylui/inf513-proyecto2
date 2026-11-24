import { describe, expect, it } from 'vitest'
import { luhn, reglas, reglasTarjeta, validar } from './validators'

describe('validadores en español', () => {
  it('requerido y correo', () => {
    const e = validar({ correo: 'mal', nombre: '' }, { correo: [reglas.requerido(), reglas.correo()], nombre: [reglas.requerido()] })
    expect(e.correo).toMatch(/correo electrónico válido/)
    expect(e.nombre).toBe('Este campo es obligatorio.')
    expect(validar({ correo: 'a@b.com' }, { correo: [reglas.correo()] })).toEqual({})
  })
  it('rangos, enteros y fechas', () => {
    expect(reglas.rango(1, 5)('9')).toMatch(/entre 1 y 5/)
    expect(reglas.entero()('1.5')).toMatch(/entero/)
    expect(reglas.fechaPosterior('ini')('2026-01-01', { ini: '2026-02-01' })).toMatch(/posterior/)
    expect(reglas.fechaPosterior('ini')('2026-03-01', { ini: '2026-02-01' })).toBeNull()
  })
  it('algoritmo de Luhn y tarjeta', () => {
    expect(luhn('4111 1111 1111 1111')).toBe(true)
    expect(luhn('4111 1111 1111 1112')).toBe(false)
    expect(reglasTarjeta.numero('4111 1111 1111 1112')).toMatch(/no es válido/)
    expect(reglasTarjeta.vencimiento('01/20')).toBe('La tarjeta está vencida.')
    expect(reglasTarjeta.vencimiento('13/30')).toMatch(/MM\/AA/)
    expect(reglasTarjeta.cvv('12')).toMatch(/3 o 4/)
  })
})
