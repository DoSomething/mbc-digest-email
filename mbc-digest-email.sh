#!/bin/bash
echo "mbc-digest-email.sh START"

ssh mongo4
collectionName="digest-" + $(date +"%Y_%m_%d")
mongo --eval "db['mailchimp-users'].copyTo('$collectionName');"

echo "mbc-digest-email.sh END"