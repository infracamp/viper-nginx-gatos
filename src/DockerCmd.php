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


    public function getServiceLogs (string $service)
    {
        $logs = phore_exec("sudo docker service logs --no-task-ids :id", ["id"=>$service], false);
        return $logs;
    }

    public function getServicePs  (string $service)
    {
        $txtData = "[" . implode(",", phore_exec("sudo docker service ps --no-trunc --format '{{json . }}' :ID", ["ID"=>$service], true)) . "]";
        return json_decode($txtData, true);
    }


    public function getServiceState (string $serviceId) {
        $psData = $this->getServicePs($serviceId);
        $state = [
            "State" => null,
            "CurrentState" => null,
            "Error" => null
        ];
        if ($psData[0]["Error"] != "") {
            $state["Error"] = $psData[0]["Error"];
        }

        if (preg_match("/^([A-Za-z]+)/", $psData[0]["CurrentState"], $matches)) {
            $state["State"] = $matches[1];
        }

        $state["CurrentState"] = $psData[0]["CurrentState"];
        return $state;
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
            $opts[] = $name . " " . $value;
        }
        return implode(" ", $opts);
    }


    private function parseIntoDockerOpts (array $config, array $dockerOpts, $update=false)
    {
        if (isset ($config["restart_policy"])) {
            $rp = $config["restart_policy"];
            if (isset ($rp["condition"])) {
                if ( ! in_array($rp["condition"], ["any", "on-failure", "none"]))
                    throw new \InvalidArgumentException("restart_policy > condition must be any|on-failure|none.");
                $dockerOpts["--restart-condition"] = $rp["condition"];
            }
            if (isset ($rp["delay"])) {
                if ( ! preg_match("/^[0-9]+(ms|s|m|h)$/", $rp["delay"]))
                    throw new \InvalidArgumentException("restart_policy > delay must match [0-9]+(ms|s|m|h).");
                $dockerOpts["--restart-delay"] = $rp["delay"];
            }
            if (isset ($rp["max_attempts"])) {
                if ( ! preg_match("/^[0-9]$/", $rp["max_attempts"]))
                    throw new \InvalidArgumentException("restart_policy > max_attempts must match [0-9]+.");
                $dockerOpts["--restart-max-attempts"] = $rp["max_attempts"];
            }
            if (isset ($rp["window"])) {
                if ( ! preg_match("/^[0-9]+(ms|s|m|h)$/", $rp["window"]))
                    throw new \InvalidArgumentException("restart_policy > window must match [0-9]+(ms|s|m|h).");
                $dockerOpts["--restart-window"] = $rp["window"];
            }
        }
        if (isset ($config["environment"])) {
            $envName = "--env";
            if ($update)
                $envName = "--env-add";

            $env = $config["environment"];
            foreach ($env as $key => $value) {
                if (is_int($key)) {
                    if (is_array($value))
                        throw new \InvalidArgumentException("Invalid environment section: " . print_r($value, true). ": complex type not allowed (See handbook for environment: - section).");
                    $dockerOpts[$envName][] = escapeshellarg($value);
                    continue;
                }
                $dockerOpts[$envName][] = escapeshellarg($key . "=". $value);
            }
        }
        return $dockerOpts;
    }


    public function serviceDeploy (string $serviceName, string $image, array $config)
    {

        $runningServices = $this->getServiceList();

        $dockerOpts = [
            "--log-opt" => "max-size=1m",
            "--with-registry-auth" => null,
            "--update-failure-action" => "pause",
            "--restart-max-attempts" => 3,
            "--env" => [],
            "--env-add" => []
        ];

        $opts =  [
            "label"=> CONFIG_SERVICE_LABEL . "=" . json_encode($config),
            "name" => $serviceName,
            "image" => $image,
            "network" => DOCKER_DEFAULT_NET
        ];

        if ( ! isset($runningServices[$serviceName])) {
            $dockerOpts = $this->parseIntoDockerOpts($config, $dockerOpts);
            phore_exec("sudo docker service create -d {$this->buildParams($dockerOpts)} --name :name --label :label --network :network :image", $opts);
            $type="create";
        } else {
            $dockerOpts = $this->parseIntoDockerOpts($config, $dockerOpts, true);

            phore_exec("sudo docker service update -d --force {$this->buildParams($dockerOpts)} --label-add :label --image :image :name", $opts);
            $type="update";
        }
        return $type;
    }


}
