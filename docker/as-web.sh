#!/usr/bin/env bash

# Forward all arguments safely using printf to quote them properly
CMD=$(printf "%q " "$@")
su www-data -g www-data -s /bin/sh -c "$CMD"
