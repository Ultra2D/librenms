<?php

if ($device['os'] == 'procurve') {
    echo 'DFB table : ';
    echo("\n");
    $datas = shell_exec($config['snmpbulkwalk'].' -M '.$config['mibdir'].' -m BRIDGE-MIB -OqsX'.snmp_gen_auth($device).' '.$device['hostname'].' dot1dTpFdbPort');
    foreach (explode("\n", $datas) as $data) {

        list($oid,$if) = explode(' ', $data);
        $oid       = str_replace('dot1dTpFdbPort[', '', $oid);
        $oid       = str_replace(']', '', $oid);
        list($a_a, $a_b, $a_c, $a_d, $a_e, $a_f) = explode(':', $oid);
        //$oid = "$a_a.$a_b.$a_c.$a_d.$a_e.$a_f";
        unset($interface);
        // only select edge interface
        $interface = dbFetchRow('SELECT * FROM `ports` LEFT JOIN `links` ON `links`.`local_port_id` = `ports`.`port_id` WHERE `links`.`local_port_id` is NULL AND `device_id` = ? AND `ifIndex` = ?', array($device['device_id'], $if));
        // WHERE `ifType` = `ethernetCsmacd`
        $ah_a      = zeropad($a_a);
        $ah_b      = zeropad($a_b);
        $ah_c      = zeropad($a_c);
        $ah_d      = zeropad($a_d);
        $ah_e      = zeropad($a_e);
        $ah_f      = zeropad($a_f);
        $mac       = "$ah_a:$ah_b:$ah_c:$ah_d:$ah_e:$ah_f";
        $mac_cisco = "$ah_a$ah_b.$ah_c$ah_d.$ah_e$ah_f";
        if ($interface) {
            echo($mac . " " .  $interface[port_id] . " " .  $interface[local_port_id] . "\n");
            $clean_mac = str_replace(':', '', $mac);
            // echo($interface['ifDescr'] . " ($if) -> $mac ($oid) -> $ip");
            if (dbFetchCell('SELECT COUNT(*) from ports_fdb WHERE port_id = ? AND mac_address = ?', array($interface['port_id'], $clean_mac))) {
                // $sql = "UPDATE `mac_accounting` SET `mac` = '$clean_mac' WHERE port_id = '".$interface['port_id']."' AND `mac` = '$clean_mac'";
                // mysql_query($sql);
                // if (mysql_affected_rows()) { echo("      UPDATED!"); }
                // echo($sql);
                dbUpdate(array('last_discovered' => array('NOW()')), 'ports_fdb', 'mac_address = ?', array($clean_mac));
                echo '.';

            }
            else if (dbFetchCell('SELECT COUNT(*) from ports_fdb WHERE mac_address = ?', array($clean_mac))) {
                echo '-';
                dbUpdate(array('first_discovered' => array('NOW()'), 'last_discovered' => array('NOW()'), 'port_id' => $interface['port_id']), 'ports_fdb', 'mac_address = ?', array($clean_mac));

            }
            else {
                // echo("      Not Exists!");
                dbInsert(array('first_discovered' => array('NOW()'), 'last_discovered' => array('NOW()'), 'port_id' => $interface['port_id'], 'mac_address' => $clean_mac), 'ports_fdb');
                echo '+';
            }

            // echo("\n");
        }
    }//end foreach

    echo "\n";
} //end if

