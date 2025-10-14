<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths afetadas pelo CORS
    |--------------------------------------------------------------------------
    | Coloque todas as rotas da API aqui. O prefixo "api/*" cobre /api/...
    | Incluí também o endpoint do Sanctum caso você use no futuro.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Métodos permitidos
    |--------------------------------------------------------------------------
    | "*" permite todos (GET, POST, PUT, PATCH, DELETE, OPTIONS).
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Origens permitidas
    |--------------------------------------------------------------------------
    | Liste os hosts do seu front-end (dev e produção).
    | Se usar outra porta/local, adicione aqui também.
    */
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://apucce.com.br',
        'https://www.apucce.com.br',
    ],

    /*
    |--------------------------------------------------------------------------
    | Padrões de origens permitidas (regex)
    |--------------------------------------------------------------------------
    | Se quiser liberar subdomínios, você pode usar patterns como:
    | '^https:\/\/(.*\.)?apucce\.com\.br$'
    | Deixe vazio se não precisar.
    */
    'allowed_origins_patterns' => [
        // '^https:\/\/([a-z0-9-]+\.)?apucce\.com\.br$',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cabeçalhos permitidos
    |--------------------------------------------------------------------------
    | "*" libera todos. Inclui Authorization para enviar Bearer token.
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Cabeçalhos expostos ao browser
    |--------------------------------------------------------------------------
    | Cabeçalhos que o browser pode ler na resposta.
    | Authorization é útil se você precisar ler o header; Content-Disposition
    | é útil para downloads.
    */
    'exposed_headers' => [
        'Authorization',
        'Content-Disposition',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tempo de cache do preflight (OPTIONS)
    |--------------------------------------------------------------------------
    | Em segundos. 86400 = 24h.
    */
    'max_age' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Envia cookies/credenciais?
    |--------------------------------------------------------------------------
    | Deixe false (se você não usa cookies). Se mudar para true, você NÃO pode
    | usar "*" em allowed_origins — terá que listar cada origem explicitamente.
    */
    'supports_credentials' => false,
];
