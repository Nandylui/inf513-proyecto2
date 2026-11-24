<?php

return [

    'grupo' => env('GRUPO', 'grupo07sa'),

    /*
    | Reglas de negocio basadas en el Estatuto Orgánico de la UAGRM.
    */
    'reglas' => [
        // RF-09: la documentación debe entregarse con al menos 48 h de anticipación a la sesión.
        'horas_anticipacion_documentos' => (int) env('HORAS_ANTICIPACION_DOCUMENTOS', 48),
        // Art. 64 incs. a) y b): el Decano promulga o veta en un máximo de 5 días hábiles.
        'dias_habiles_promulgacion' => (int) env('DIAS_HABILES_PROMULGACION', 5),
        // Art. 50: mandato de 2 años sin reelección al mismo cargo.
        'anios_mandato' => 2,
        // Art. 55: sesión extraordinaria a convocatoria del Presidente o de 3 o más miembros.
        'minimo_solicitantes_extraordinaria' => 3,
    ],

    /*
    | Procesador de correos (Sistema Vía Mail). El servidor de la cátedra usa POP3 (110) y SMTP (25) sin SSL.
    */
    'correo' => [
        'direccion' => env('CONSEJO_MAIL_DIRECCION', 'grupo07sa@tecnoweb.org.bo'),
        'pop3_host' => env('CONSEJO_POP3_HOST', 'mail.tecnoweb.org.bo'),
        'pop3_puerto' => (int) env('CONSEJO_POP3_PUERTO', 110),
        'pop3_usuario' => env('CONSEJO_POP3_USUARIO', 'grupo07sa'),   // sin dominio
        'pop3_clave' => env('CONSEJO_POP3_CLAVE'),
        'pop3_timeout' => (int) env('CONSEJO_POP3_TIMEOUT', 15),
        'intervalo_segundos' => (int) env('CONSEJO_MAIL_INTERVALO', 30),
        // Si es false, solo responden comandos los remitentes registrados en la tabla usuario.
        'permitir_anonimos' => (bool) env('CONSEJO_MAIL_ANONIMOS', false),
        // RF-19: enviar también por correo las notificaciones del sistema.
        'notificaciones_por_correo' => (bool) env('CONSEJO_NOTIFICAR_POR_CORREO', false),
    ],

    'admin' => [
        'ci' => env('ADMIN_CI', '1000000'),
        'correo' => env('ADMIN_CORREO', 'admin@consejo.test'),
        'clave' => env('ADMIN_CLAVE', 'Admin2026*'),
    ],
];
