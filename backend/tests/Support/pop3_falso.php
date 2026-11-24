<?php
// Servidor POP3 falso para las pruebas: php pop3_falso.php <puerto> <archivo_mensajes.json>
[$_, $puerto, $archivo] = $argv;
$mensajes = json_decode(file_get_contents($archivo), true);
$srv = stream_socket_server("tcp://127.0.0.1:{$puerto}", $en, $es);
$borrados = [];
$c = stream_socket_accept($srv, 20);
$w = fn ($s) => fwrite($c, $s."\r\n");
$w('+OK POP3 falso listo');
while (($l = fgets($c)) !== false) {
    $l = trim($l);
    $cmd = strtoupper(strtok($l, ' '));
    switch ($cmd) {
        case 'USER': $w('+OK'); break;
        case 'PASS': $w(strpos($l, 'clave-ok') !== false ? '+OK sesion abierta' : '-ERR clave invalida'); break;
        case 'STAT': $w('+OK '.count($mensajes).' 100'); break;
        case 'LIST':
            $w('+OK');
            foreach ($mensajes as $i => $m) { $w(($i + 1).' '.strlen($m)); }
            $w('.'); break;
        case 'RETR':
            $n = (int) substr($l, 5);
            $w('+OK');
            foreach (preg_split("/\r?\n/", $mensajes[$n - 1]) as $linea) { $w(str_starts_with($linea, '.') ? '.'.$linea : $linea); }
            $w('.'); break;
        case 'DELE':
            $borrados[] = (int) substr($l, 5);
            file_put_contents($archivo.'.borrados', json_encode($borrados));
            $w('+OK borrado'); break;
        case 'QUIT': $w('+OK adios'); break 2;
        default: $w('-ERR comando desconocido');
    }
}
