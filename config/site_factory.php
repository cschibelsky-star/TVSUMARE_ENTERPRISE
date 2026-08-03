<?php
declare(strict_types=1);

return [
    'token' => (string) (getenv('SITE_FACTORY_TOKEN') ?: ''),
    'allowed_products' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (getenv('SITE_FACTORY_ALLOWED_PRODUCTS') ?: ''))
    ))),
    'default_plan' => (string) (getenv('SITE_FACTORY_DEFAULT_PLAN') ?: 'enterprise'),
    'dry_run_default' => filter_var(
        getenv('SITE_FACTORY_DRY_RUN') ?: 'true',
        FILTER_VALIDATE_BOOL
    ),
];
