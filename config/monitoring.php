<?php

return [
    // read by node-exporter (textfile collector), written by app:export-metrics every minute
    'textfile_path' => env('METRICS_TEXTFILE_PATH', storage_path('metrics/goat.prom')),

    'queues' => array_filter(explode(',', env('MONITORING_QUEUES', 'default,scoring'))),
];
