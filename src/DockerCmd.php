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
        $txtData = "[" . implode(",", phore_exec("sudo docker service ls --format '{{json . }}'", [], true)) . "]";
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


    public function getParsedConfigLabel (string $service) : array
    {
        $inspectData = $this->getServiceInspect($service);
        if ( ! isset ($inspectData["Spec"]["Labels"][CONFIG_SERVICE_LABEL])) {
            throw new \InvalidArgumentException("Invalid config in label " . CONFIG_SERVICE_LABEL );
        }

        $serviceConfig = json_decode($inspectData["Spec"]["Labels"][CONFIG_SERVICE_LABEL], true);
        if ($serviceConfig === null){
            throw new \InvalidArgumentException("Invalid (invalid json data) config in label " . CONFIG_SERVICE_LABEL);
        }
        return $serviceConfig;
    }


    public function dockerLogin(string $user, string $pass, string $registryHost)
    {
        phore_exec("sudo docker login -u :user -p :pass :registry", [
            "registry" => $registryHost,
            "user" => $user,
            "pass" => $pass
        ]);
    }


    public function getConfig(string $configName) : array
    {
        $ret = phore_exec("sudo docker config inspect ?", [$configName]);
        $ret = json_decode($ret, true);
        if ($ret === null)
            throw new \Exception("Can't decode response from docker config '$configName'");
        $config = json_decode(base64_decode($ret[0]["Spec"]["Data"]), true);
        if ($config === null)
            throw new \Exception("Can't decode docker config data '$configName'");
        return $config;
    }

    public function setConfig(string $configName, array $config)
    {
        try {
            phore_exec("sudo docker config rm ? -", [$configName]);
        } catch (\Exception $e) {
            // Ignore empty config
        }
        phore_exec("echo ? | sudo docker config create ? -", [json_encode($config), $configName]);
    }


    private function buildParams (array $params)
    {
        $opts = [];
        foreach ($params as $name => $value) {
            if (is_null($value)) {
                $opts[] = $name;
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $curVal) {
                    $opts[] = $name . " " . $curVal;
                }
                continue;
            }
            $opts[] = $name . " " . $curVal;
        }
        return implode(" ", $opts);
    }

    public function serviceDeploy (string $serviceName, string $image, string $label)
    {

        $runningServices = $this->getServiceList();

        $dockerOpts = [
            "--log-opt" => "max-size=1m",
            "--with-registry-auth" => null,
            "--update-failure-action" => "pause",
            "--restart-max-attempts" => 3,
        ];

        $opts =  [
            "label"=> $label,
            "name" => $serviceName,
            "image" => $image,
            "network" => DOCKER_DEFAULT_NET
        ];

        if ( ! isset($runningServices[$serviceName])) {
            phore_exec("sudo docker service create -d {$this->buildParams($dockerOpts)} --name :name --label :label --network :network :image", $opts);
            $type="create";
        } else {
            phore_exec("sudo docker service update -d --force {$this->buildParams($dockerOpts)} --label-add :label --image :image :name", $opts);
            $type="update";
        }
        return $type;
    }


}
