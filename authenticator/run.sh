#!/bin/bash

while true; do
    python -u mumble-sso-auth.py | tee -a mumble-sso-auth.log
    sleep 5
done
