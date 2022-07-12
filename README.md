# Extend Configure Sentry to send more data.

## Configuration Steps

1. Configure Sentry Bundle.. see their documentation. 
   * Add `sentry.yaml` config file 
    ```yaml
    sentry:
      dsn: '%env(SENTRY_DSN)%'
      tracing:
        enabled: true
        dbal: # DB queries
          enabled: true
        cache:
          enabled: false
        twig: # templating engine
          enabled: false
      options:
        environment: '%env(APP_ENV)%'
        release: '%env(APP_VERSION)%'
        sample_rate: '%env(float:SENTRY_SAMPLE_RATE)%'
        traces_sample_rate: '%env(float:SENTRY_TRACE_SAMPLE_RATE)%'
        integrations:
      register_error_listener: false
    
    services:
      Sentry\Monolog\Handler:
        arguments:
          $hub: '@Sentry\State\HubInterface'
          $level: !php/const Monolog\Logger::ERROR    
    ```
    * Added monolog sentry handler
   ```yaml
        sentry:
            type: service
            id: Sentry\Monolog\Handler
    ```
2. Configure Env vars:
   ``` 
   SENTRY_DSN={URL PROVIDED BY SENTRY}
   SENTRY_SAMPLE_RATE=0.0
   SENTRY_TRACE_SAMPLE_RATE=0.0
   ```