<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 14:30
 */

namespace Infracamp\Gatos;


class DockerCmd
{

    public function getServiceList () {
        $txtData = "[" . phore_exec("sudo timeout 20 docker service ls --format '{{json . }}'") . "]";
        $data = json_decode($txtData, true);
        if ($data === null)
            throw new \InvalidArgumentException("Cannot json decode output from docker service ls: $txtData");

        $services = [];
        foreach ($data as $cur) {
            $services[$cur["Name"]] = $cur;
        }

        ksort($services);
        return $services;
    }

}
