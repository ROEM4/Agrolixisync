<?php

// config/agrolixisync.php
// Configuración específica del sistema AGROlixisync

return [
    
    // ═══════════════════════════════════════════════════════════════
    // LIXIVIACIÓN - PARÁMETROS CIENTÍFICOS
    // ═══════════════════════════════════════════════════════════════
    
    'lixiviation' => [
        // Umbral de caída en CE (µS/cm)
        // Si Δ CE > 0.35 → LIXIVIACIÓN
        'caida_ce' => 0.35,
        
        // Umbral de ratio (adimensional)
        // Si CE_prof / CE_sup > 1.2 → LIXIVIACIÓN
        'ratio' => 1.2,
        
        // Umbrales de riesgo MEDIO
        'caida_ce_medio' => 0.15,
        'ratio_medio' => 1.1,
        
        // Máximo desfase entre lecturas (segundos)
        'max_reading_time_diff' => 300,  // 5 minutos
        
        // Configuración por crop (futuro)
        'crop_thresholds' => [
            'default' => ['caida_ce' => 0.35, 'ratio' => 1.2],
            'palto' => ['caida_ce' => 0.30, 'ratio' => 1.15],
            'viña' => ['caida_ce' => 0.40, 'ratio' => 1.25],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════
    // WEBSOCKETS / REVERB - TIEMPO REAL
    // ═══════════════════════════════════════════════════════════════
    
    'websockets' => [
        // Usar Reverb o Pusher
        'driver' => env('BROADCAST_DRIVER', 'reverb'),
        
        // Reverb configuration
        'reverb' => [
            'enabled' => true,
            'url' => env('REVERB_APP_URL', 'http://localhost:8000'),
            'port' => env('REVERB_PORT', 8080),
            'app_id' => env('REVERB_APP_ID', 'agrolixisync'),
            'app_key' => env('REVERB_APP_KEY', 'agrolixisync-key'),
            'app_secret' => env('REVERB_APP_SECRET', 'agrolixisync-secret'),
        ],
        
        // Pusher fallback
        'pusher' => [
            'enabled' => false,
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'host' => env('PUSHER_HOST'),
            'port' => env('PUSHER_PORT'),
            'scheme' => env('PUSHER_SCHEME'),
        ],
    ],

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD EN TIEMPO REAL
    // ═══════════════════════════════════════════════════════════════
    
    'dashboard' => [
        // Intervalo de refresco de historial (si no hay WebSocket)
        'fallback_polling_interval_ms' => 5000,
        
        // Horas de historial mostrado
        'history_hours' => 24,
        
        // Máximo de puntos en gráfico
        'max_chart_points' => 288,  // 5 minutos × 24 horas
        
        // Configuración de alertas
        'alerts' => [
            'show_notification' => true,
            'play_sound' => true,
            'sound_file' => '/audio/alert.mp3',
        ],
    ],

    // ═══════════════════════════════════════════════════════════════
    // ALMACENAMIENTO EN CACHÉ
    // ═══════════════════════════════════════════════════════════════
    
    'cache' => [
        // TTL para últimos datos en caché
        'latest_reading_ttl_minutes' => 5,
        'latest_analysis_ttl_minutes' => 15,
        'dashboard_data_ttl_minutes' => 5,
    ],

    // ═══════════════════════════════════════════════════════════════
    // LOGGING Y AUDITORÍA
    // ═══════════════════════════════════════════════════════════════
    
    'logging' => [
        'log_all_readings' => true,
        'log_api_requests' => true,
        'log_analysis_events' => true,
        'log_lixiviation_alerts' => true,
        
        // Retención de logs (días)
        'retention_days' => 90,
    ],

    // ═══════════════════════════════════════════════════════════════
    // SEGURIDAD & VALIDACIÓN
    // ═══════════════════════════════════════════════════════════════
    
    'security' => [
        // Rate limiting para API
        'api_rate_limit_per_minute' => 100,
        
        // Validación de dispositivos
        'validate_device_code' => true,
        
        // HTTPS requerido en producción
        'require_https_in_production' => true,
    ],

    // ═══════════════════════════════════════════════════════════════
    // NOTIFICACIONES
    // ═══════════════════════════════════════════════════════════════
    
    'notifications' => [
        // Enviar email cuando se detecta lixiviación
        'email_on_lixiviation' => true,
        'email_on_lixiviation_level' => 'ALTO',  // ALTO, MEDIO, BAJO
        
        // SMS via Twilio (futuro)
        'sms_on_lixiviation' => false,
        'sms_numbers' => [],
        
        // Slack notifications (futuro)
        'slack_on_lixiviation' => false,
        'slack_webhook' => env('SLACK_WEBHOOK_URL'),
    ],
];
