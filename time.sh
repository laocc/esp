#!/bin/sh

function sync_time(){
    get=$(/usr/sbin/ntpdate -u $1 | grep "offset");
    getday=$(echo $get | awk '{print $1}');
    today=$(date -d now +"%d");
    now=$(date);
    if [ "$today" = "$getday" ]; then
        echo $now '    ' $1 ' >>>> '  $get  [is True];
        exit 0;
    else
        echo $now '    ' $1 ' >>>> '  $get  [is False];
    fi
}


sync_time ntp.api.bz;
sync_time time.nist.gov
sync_time time-nw.nist.gov
sync_time time-a.nist.gov
sync_time time-b.nist.gov
sync_time asia.pool.ntp.org
sync_time time.windows.com

