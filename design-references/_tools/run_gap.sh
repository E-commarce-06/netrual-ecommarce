#!/bin/bash
export LD_LIBRARY_PATH=$(cat /home/user/design-references/_tools/libpath)
export PYTHONPATH=/home/user/design-references/_tools/lib
cd /home/user/design-references/_tools
EXTRA=1 timeout 900 python3 -u capture.py 03-walmart 2>&1 | grep --line-buffered -v pid=
EXTRA=1 timeout 900 python3 -u capture.py 07-home-depot 2>&1 | grep --line-buffered -v pid=
timeout 1200 python3 -u capture.py 09-gap 2>&1 | grep --line-buffered -v pid=
echo ALLDONE
