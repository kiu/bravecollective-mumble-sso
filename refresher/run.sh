#!/bin/bash

while true; do
    php mumble-sso-runner.php | tee -a mumble-sso-runner.log
    sleep 5
done
