#!/usr/bin/env python3
"""Genera DOCUMENTACION_TECNICA.md combinando texto fijo con datos reales (rutas de la API, matriz RBAC, esquema de PostgreSQL)."""
import json, re, collections

routes = json.load(open('/tmp/docs_data/routes.json'))
cols = json.load(open('/tmp/docs_data/cols.json'))
matriz = [l.rstrip('\n').split('|') for l in open('/tmp/docs_data/matriz.txt') if l.strip()]
ROLES = ['ADMINISTRADOR', 'PRESIDENTE', 'SECRETARIO', 'TITULAR_DOCENTE', 'SUPLENTE_DOCENTE', 'TITULAR_ESTUDIANTE', 'SUPLENTE_ESTUDIANTE']
ABREV = {'ADMINISTRADOR': 'ADM', 'PRESIDENTE': 'PRE', 'SECRETARIO': 'SEC', 'TITULAR_DOCENTE': 'TDO', 'SUPLENTE_DOCENTE': 'SDO', 'TITULAR_ESTUDIANTE': 'TES', 'SUPLENTE_ESTUDIANTE': 'SES'}

def tabla(cab, filas):
    s = '| ' + ' | '.join(cab) + ' |\n|' + '|'.join(['---'] * len(cab)) + '|\n'
    for f in filas: s += '| ' + ' | '.join(str(x).replace('|', '/') for x in f) + ' |\n'
    return s + '\n'

# ---- endpoints
filas = []
for r in routes:
    if r['uri'] == 'api' or 'HEAD' in r['method'] and False: pass
    m = r['method'].split('|')[0]
    perm = next((x.split(':', 1)[1] for x in r['middleware'] if 'ExigirPermiso' in x), None)
    auth = any('Authenticate' in x for x in r['middleware'])
    acceso = f'permiso `{perm}`' if perm else ('usuario autenticado' if auth else 'público')
    ctl = r['action'].split('\\')[-1] if r['action'] != 'Closure' else 'Closure'
    filas.append((m, '/' + r['uri'], acceso, ctl))
filas.sort(key=lambda x: (x[1].split('/')[2] if len(x[1].split('/')) > 2 else '', x[1], x[0]))
ep = tabla(['Método', 'Ruta', 'Acceso', 'Controlador'], [(a, f'`{b}`', c, f'`{d}`') for a, b, c, d in filas])

# ---- matriz RBAC
rb = []
for modulo, cod, desc, roles in matriz:
    rs = set(x.strip() for x in roles.split(','))
    rb.append([modulo, f'`{cod}`'] + ['●' if r in rs else '' for r in ROLES])
rbac = tabla(['Módulo', 'Permiso'] + [ABREV[r] for r in ROLES], rb)

# ---- diccionario
grupos = collections.OrderedDict([
 ('Seguridad, RBAC y trazabilidad', ['usuario', 'rol', 'permiso', 'rol_permiso', 'menu', 'bitacora_acceso', 'visita_pagina', 'auditoria', 'notificacion']),
 ('Consejo: mandatos, sesiones y asistencia', ['mandato', 'sesion', 'punto_orden_dia', 'excusa', 'habilitacion', 'asistencia', 'acta']),
 ('Trámites, votación y resoluciones', ['tipo_tramite', 'flujo_paso', 'documento', 'documento_version', 'validacion', 'votacion', 'resolucion']),
 ('Pagos electrónicos', ['concepto_pago', 'metodo_pago', 'plan_pago', 'cuota', 'pago']),
])
dic = ''
for g, tabs in grupos.items():
    dic += f'#### {g}\n\n'
    for t in tabs:
        cs = ', '.join(('**%s**' % c if pk != '0' else c) for c, dt, pk in cols[t])
        dic += f'- `{t}`: {cs}.\n'
    dic += '\n'
dic += '- Tablas técnicas de Laravel y del primer parcial: `migrations`, `personal_access_tokens` (tokens Sanctum) y `correo_log` (bitácora del Sistema Vía Mail).\n\n'

TXT = open('texto_fijo.md', encoding='utf-8').read()
TXT = TXT.replace('{{ENDPOINTS}}', ep).replace('{{RBAC}}', rbac).replace('{{DICCIONARIO}}', dic).replace('{{NRUTAS}}', str(len([f for f in filas])))
open('DOCUMENTACION_TECNICA.md', 'w', encoding='utf-8').write(TXT)
print('ok', len(TXT))
