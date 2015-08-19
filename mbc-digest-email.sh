#!/bin/bash
echo "mbc-digest-email.sh START"

collectionName="digest-" + $(date+"%Y_%m_%d")
mongo --host mongo4-aws --eval "rs.slaveOk(); use mb-users; db['mailchimp-users'].copyTo('$collectionName');"

echo "mbc-digest-email.sh END"
