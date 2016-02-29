<?php

if ($device['type'] == 'network') {
    echo 'FDB table : ';
    echo("\n");

    $datas = shell_exec($config['snmpbulkwalk'].' -M '.$config['mibdir'].' -m Q-BRIDGE-MIB -Oqs '.snmp_gen_auth($device).' '.$device['hostname'].' dot1qVlanFdbId');
    foreach (explode("\n", $datas) as $data) {
        unset($matches);
        if (preg_match("/^dot1qVlanFdbId\.\d+\.(?P<vlan_number>\d+) (?P<vlan_index>\d+)$/", $data, $matches)) {
            $vlan[$matches['vlan_index']] = $matches['vlan_number'];
        }
    }

    $datas = shell_exec($config['snmpbulkwalk'].' -M '.$config['mibdir'].' -m Q-BRIDGE-MIB -OqsX '.snmp_gen_auth($device).' '.$device['hostname'].' dot1qTpFdb');
    foreach (explode("\n", $datas) as $data) {
        unset($matches);
        if (!preg_match("/^dot1qTpFdbPort\[(?P<vlan_index>\d+)\]\[(?P<mac>[0-9a-f]{1,2}(:[0-9a-f]{1,2}){5})\] (?P<interface>[0-9]+)$/", $data, $matches)) {
            // unexpected input
            break;
        }
        list($a_a, $a_b, $a_c, $a_d, $a_e, $a_f) = explode(':', $matches['mac']);
        $ah_a      = zeropad($a_a);
        $ah_b      = zeropad($a_b);
        $ah_c      = zeropad($a_c);
        $ah_d      = zeropad($a_d);
        $ah_e      = zeropad($a_e);
        $ah_f      = zeropad($a_f);
        $mac       = "$ah_a:$ah_b:$ah_c:$ah_d:$ah_e:$ah_f";
        $mac_cisco = "$ah_a$ah_b.$ah_c$ah_d.$ah_e$ah_f";

        unset($interface);
        // only select edge ports
        if (!$no_edge_port[$matches['interface']]) { 
            $interface = dbFetchRow('SELECT * FROM `ports` LEFT JOIN `links` ON `links`.`local_port_id` = `ports`.`port_id` WHERE `links`.`local_port_id` is NULL AND `device_id` = ? AND `ifIndex` = ?', array($device['device_id'], $matches['interface']));
            // WHERE `ifType` = `ethernetCsmacd`
        }

        if ($interface) {
            $clean_mac = str_replace(':', '', $mac);
            if (dbFetchCell('SELECT COUNT(*) from ports_fdb WHERE port_id = ? AND mac_address = ?', array($interface['port_id'], $clean_mac))) {
                // same port
                dbUpdate(array('last_discovered' => array('NOW()')), 'ports_fdb', 'mac_address = ?', array($clean_mac));
                echo '.';

            }
            else if (dbFetchCell('SELECT COUNT(*) from ports_fdb WHERE mac_address = ?', array($clean_mac))) {
                // different port
                dbUpdate(array('first_discovered' => array('NOW()'), 'last_discovered' => array('NOW()'), 'port_id' => $interface['port_id']), 'ports_fdb', 'mac_address = ?', array($clean_mac));
                echo '.';

            }
            else {
                // previously unkown MAC address
                dbInsert(array('first_discovered' => array('NOW()'), 'last_discovered' => array('NOW()'), 'port_id' => $interface['port_id'], 'mac_address' => $clean_mac), 'ports_fdb');
                echo '+';
            }

            // if ($debug) {
                echo("   vlan: ". sprintf("%4s", $vlan[$matches['vlan_index']]). "   mac: ". $mac . "   if: " . sprintf("%6s", $interface[port_id]) );
                echo("\n");

            // }
        } 
        else {
            // remember this interface is not an edge port to prevent duplicate SQL queries
            $no_edge_port[$matches['interface']] = true;

        }
    }//end foreach

    echo "\n";
} //end if

