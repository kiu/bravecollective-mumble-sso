#!/bin/bash
screen -d -m -S mumble-sso-authenticator bash -c 'cd authenticator && ./run.sh'
screen -d -m -S mumble-sso-refresher bash -c 'cd refresher && ./run.sh'
