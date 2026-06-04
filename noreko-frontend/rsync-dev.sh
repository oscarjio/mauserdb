#!/bin/bash
# Snabb rsync till dev (kräver att ng build --watch körs)
rsync -avz --delete \
  /home/clawd/clawd/mauserdb/noreko-frontend/dist/noreko-frontend/ \
  user@mauserdb.com:/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/ \
  -e "ssh -p 32546" --quiet
echo "Deployat!"
