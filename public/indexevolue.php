<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $appEnv = $context['APP_ENV'] ?? 'prod';
    $appDebug = filter_var($context['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocalRequest = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    $allowUnsafeEnv = filter_var($context['APP_ALLOW_UNSAFE_ENV'] ?? false, FILTER_VALIDATE_BOOL);

    if (!$allowUnsafeEnv && !$isLocalRequest && $appEnv !== 'test') {
        $appEnv = 'prod';
        $appDebug = false;
    }

    return new Kernel($appEnv, $appDebug);
};
