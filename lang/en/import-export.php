<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Import / Export — English
    |--------------------------------------------------------------------------
    */

    'status' => [
        'pending' => 'Pending',
        'mapping' => 'Mapping',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'completed_with_errors' => 'Completed with errors',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'session' => [
        'created' => 'Import session created.',
        'updated' => 'Import session updated.',
        'cancelled' => 'Import session cancelled.',
        'not_found' => 'Import session not found.',
        'already_processing' => 'This import is already being processed.',
        'invalid_status_transition' => 'The requested status transition is not allowed.',
        'invalid_model' => 'The selected importable model is not registered.',
        'no_processor_registered' => 'No import processor is registered for the selected model.',
    ],

    'mapping' => [
        'updated' => 'Mapping updated.',
        'required_missing' => 'One or more required columns are not mapped: :fields',
        'unknown_target_field' => 'Unknown target field ":field" for :model.',
    ],

    'template' => [
        'created' => 'Template created.',
        'updated' => 'Template updated.',
        'deleted' => 'Template deleted.',
        'applied' => 'Template applied.',
        'set_default' => 'Template set as default.',
        'limit_reached' => 'You have reached the maximum number of templates (:max) for your account.',
        'not_found' => 'Template not found.',
    ],

    'export' => [
        'yes' => 'Yes',
        'no' => 'No',
        'started' => 'Export started.',
        'completed' => 'Export completed.',
    ],

    'errors' => [
        'file_not_readable' => 'The uploaded file is not readable.',
        'no_headers_detected' => 'No headers detected in the uploaded file.',
        'empty_file' => 'The uploaded file is empty.',
        'row_validation_failed' => 'Row :row failed validation.',
    ],

    /*
    | Field labels & aliases for column matching. Host apps add their own
    | model keys here via `lang/vendor/import-export/en/import-export.php`.
    */
    'fields' => [
        'common' => [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
    ],
];
