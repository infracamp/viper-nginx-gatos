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
        $txtData = "[" . implode(",", phore_exec("sudo timeout 20 docker service ls --format '{{json . }}'", [], true)) . "]";
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


    public function getServiceInspect (string $service)
    {
        $inspectData = json_decode(phore_exec("sudo docker service inspect --format '{{json . }}' :ID", ["ID" => $service]), true);
        return $inspectData;
    }


    public function dockerLogin(string $user, string $pass, string $registryHost)
    {
        phore_exec("sudo docker login -u :user -p :pass :registry", [
            "registry" => $registryHost,
            "user" => $user,
            "pass" => $pass
        ]);
    }

    public function serviceDeploy (string $serviceName, string $image, string $label)
    {

        $runningServices = $this->getServiceList();

        $opts =  [
            "label"=> $label,
            "name" => $serviceName,
            "image" => $image,
            "network" => DOCKER_DEFAULT_NET
        ];

        if ( ! isset($runningServices[$serviceName])) {
            phore_exec("sudo docker service create -d --force --name :name --restart-max-attempts 3 --update-failure-action pause --with-registry-auth --label :label --network :network :image", $opts);
            $type="create";
        } else {
            phore_exec("sudo docker service update -d --force --restart-max-attempts 3 --update-failure-action pause --with-registry-auth --label-add :label --image :image :name", $opts);
            $type="update";
        }
        return $type;
    }


}
