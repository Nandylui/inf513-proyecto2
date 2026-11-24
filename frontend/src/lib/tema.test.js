import { describe, expect, it } from 'vitest'
import { modoEfectivo, modoPorHorario, normalizar } from './tema.js'
import { luhn, reglas, reglasTarjeta, validar } from './validators.js'

describe('tema y accesibilidad', () => {
  it('modo automático: noche de 19:00 a 06:00', () => {
    expect(modoPorHorario(new Date(2026, 10, 24, 7, 0))).toBe('dia')
    expect(modoPorHorario(new Date(2026, 10, 24, 18, 59))).toBe('dia')
    expect(modoPorHorario(new Date(2026, 10, 24, 19, 0))).toBe('noche')
    expect(modoPorHorario(new Date(2026, 10, 24, 5, 59))).toBe('noche')
  })
  it('el modo manual ignora la hora', () => {
    expect(modoEfectivo('dia', new Date(2026, 10, 24, 23, 0))).toBe('dia')
    expect(modoEfectivo('noche', new Date(2026, 10, 24, 12, 0))).toBe('noche')
  })
  it('normaliza preferencias inválidas', () => {
    expect(normalizar({ tema: 'x', modo: 'y', fuente: 999, contraste: 1 })).toEqual({ tema: 'adultos', modo: 'auto', fuente: 160, contraste: true })
    expect(normalizar({ fuente: 10 }).fuente).toBe(80)
  })
})

describe('validación del cliente en español', () => {
  it('reglas básicas', () => {
    expect(reglas.requerido()('  ')).toBe('Este campo es obligatorio.')
    expect(reglas.correo()('mal@')).toContain('correo electrónico válido')
    expect(reglas.correo()('a@b.com')).toBeNull()
    expect(reglas.ci()('12')).toContain('CI')
    expect(reglas.ci()('1234567')).toBeNull()
    expect(reglas.mayorQue(0)('-3')).toContain('mayor que 0')
  })
  it('validar() devuelve el primer error de cada campo', () => {
    const e = validar({ correo: 'x', nombre: '' }, { correo: [reglas.requerido(), reglas.correo()], nombre: reglas.requerido() })
    expect(Object.keys(e)).toEqual(['correo', 'nombre'])
    expect(e.correo).toContain('correo electrónico')
  })
  it('tarjetas: Luhn, vencimiento y CVV', () => {
    expect(luhn('4242424242424242')).toBe(true)
    expect(luhn('4242424242424241')).toBe(false)
    expect(reglasTarjeta.numero('4242 4242 4242 4241')).toContain('no es válido')
    expect(reglasTarjeta.vencimiento('13/30')).toContain('MM/AA')
    expect(reglasTarjeta.vencimiento('01/20')).toBe('La tarjeta está vencida.')
    expect(reglasTarjeta.cvv('12')).toContain('3 o 4 dígitos')
    expect(reglasTarjeta.cvv('123')).toBeNull()
  })
})
