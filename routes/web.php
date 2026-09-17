<?php

use Illuminate\Support\Facades\Route;

// La aplicación no tiene login: ninguna ruta usa middleware de autenticación
// (constitución v2, principio V). El acceso se restringe en el despliegue.

Route::redirect('/', '/dashboard');

Route::inertia('/dashboard', 'dashboard')->name('dashboard');
