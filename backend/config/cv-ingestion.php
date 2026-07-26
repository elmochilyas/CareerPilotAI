<?php

return [
    'max_file_size' => env('CV_MAX_FILE_SIZE', 20 * 1024 * 1024),
    'allowed_extensions' => ['pdf', 'docx'],
    'allowed_mime_types' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    'storage_disk' => env('CV_STORAGE_DISK', 'local'),
    'storage_path' => 'cv-ingestion',
    'queue' => env('CV_QUEUE', 'cv-ingestion'),
    'retry_attempts' => 3,
    'retry_backoff' => [5, 15, 30],
    'pipeline_version' => '1.0.0',
    'analysis_schema_version' => '1.1.0',
    'processing_timeout' => 300,
    'analysis_max_text_length' => env('CV_ANALYSIS_MAX_TEXT_LENGTH', 50000),
    'upload_rate_limit' => env('CV_UPLOAD_RATE_LIMIT', '10,1'),
    'download_rate_limit' => env('CV_DOWNLOAD_RATE_LIMIT', '60,1'),
    'import_rate_limit' => env('CV_IMPORT_RATE_LIMIT', '5,1'),
];
