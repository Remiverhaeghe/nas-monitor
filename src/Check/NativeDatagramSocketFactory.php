<?php

namespace App\Check;

/**
 * Socket ICMP « datagramme » : protocole 1, sans droits root quand le noyau l'autorise (net.ipv4.ping_group_range).
 */
final class NativeDatagramSocketFactory implements DatagramSocketFactory
{
    public function open(): DatagramSocket
    {
        $rSocket = new NativeDatagramSocket(1);

        return $rSocket;
    }
}
