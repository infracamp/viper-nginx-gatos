<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 27.08.18
 * Time: 14:27
 */

namespace Infracamp\Gatos;


use Phore\MicroApp\Type\RouteParams;
use Phore\Theme\Bootstrap\Bootstrap4_Config;
use Phore\Theme\Bootstrap\Bootstrap4_Page;

class ServiceViewCtrl
{
    public function on_get(RouteParams $routeParams)
    {
        $serviceId = $routeParams->get("serviceId");
        $cmd = new DockerCmd();
        $logs = $cmd->getServiceLogs($serviceId);

        $config = new Bootstrap4_Config();
        $config->title = "viper auto deployer";

        $page = new Bootstrap4_Page($config);
        $page->addContent([
            "div @container" => [
                ["h1" => CONF_CLUSTER_NAME],
                ["h2" => "See logfiles of service {$serviceId}"],
                "hr" => null,
                "code" => [
                    "pre" => $logs
                ],
                ["hr"=>null],
                "p" => [
                    "viper cloudfont by",
                    "a @href=http://infracamp.org" => "infracamp.org",
                    " :: build " . VERSION_INFO
                ]
            ]
        ]);
        $page->out();
        return true;
    }
}
