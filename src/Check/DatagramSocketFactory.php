<?php

namespace App\Check;

interface DatagramSocketFactory
{
    /**
     * @throws \RuntimeException si le socket ne peut pas être créé
     */
    public function open(): DatagramSocket;
}
