#!/usr/bin/env bash

configs=(
    "upload_max_filesize"
    "max_file_uploads"
    "post_max_size"
    "error_reporting"
    "display_errors"
    "max_execution_time"
    "memory_limit"
)

for config in "${CONFIGS[@]}"; do
    env_name=PHP_${config^^}
    if [ ! -z "${!env_name}" ]; then
        echo "$config = ${!env_name}"
    fi
done
