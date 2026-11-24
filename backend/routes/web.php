<?php

use Illuminate\Support\Facades\Route;

/*
| La aplicación React (compilada con Vite) vive en public/. Si el servidor web entrega index.html directamente,
| esta ruta no se usa; si prefiere index.php, Laravel entrega el mismo archivo.
*/
Route::get('/', function () {
    $spa = public_path('index.html');
    abort_unless(is_file($spa), 404, 'Aplicación no compilada. Ejecute "npm run build" en el frontend.');

    return response()->file($spa, ['Content-Type' => 'text/html; charset=UTF-8']);
});
