<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Líneas de idioma de la API
    |--------------------------------------------------------------------------
    |
    | Las siguientes líneas de idioma se utilizan para mensajes de la API
    | específicos de la aplicación Carmot.
    |
    */

    'success' => [
        'created' => 'Recurso creado exitosamente.',
        'updated' => 'Recurso actualizado exitosamente.',
        'deleted' => 'Recurso eliminado exitosamente.',
        'retrieved' => 'Recurso obtenido exitosamente.',
    ],

    'error' => [
        'not_found' => 'Recurso no encontrado.',
        'unauthorized' => 'No autorizado para realizar esta acción.',
        'forbidden' => 'Acceso denegado.',
        'validation_failed' => 'Los datos proporcionados no son válidos.',
        'server_error' => 'Error interno del servidor.',
        'method_not_allowed' => 'Método no permitido.',
        'too_many_requests' => 'Demasiadas solicitudes. Inténtalo más tarde.',
    ],

    'user' => [
        'registered' => 'Usuario registrado exitosamente.',
        'logged_in' => 'Inicio de sesión exitoso.',
        'logged_out' => 'Sesión cerrada exitosamente.',
        'profile_updated' => 'Perfil actualizado exitosamente.',
        'password_changed' => 'Contraseña cambiada exitosamente.',
        'account_deleted' => 'Cuenta eliminada exitosamente.',
    ],

    'permissions' => [
        'granted' => 'Permiso otorgado exitosamente.',
        'revoked' => 'Permiso revocado exitosamente.',
        'role_assigned' => 'Rol asignado exitosamente.',
        'role_removed' => 'Rol removido exitosamente.',
    ],

    'messages' => [
        'welcome' => 'Bienvenido a la API de Carmot',
        'maintenance' => 'La aplicación está en mantenimiento. Inténtalo más tarde.',
        'feature_disabled' => 'Esta funcionalidad está deshabilitada temporalmente.',
    ],

];
