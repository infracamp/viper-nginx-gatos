<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 19:01
 */

namespace Infracamp\Gatos;


use Phore\Html\Elements\RawHtmlNode;
use Phore\Html\Helper\Table;
use Phore\Theme\Bootstrap\Bootstrap4_Config;
use Phore\Theme\Bootstrap\Bootstrap4_Page;

class InfoCtrl
{


    public function parseTs($ts) : int
    {
        preg_match ("/([0-9\-]+)T([0-9\:]+)/", $ts, $matches);
        return strtotime($matches[1] . " " . $matches[2]);
    }



    public function on_get() {

        $config = new Bootstrap4_Config();
        $config->title = "viper auto deployer";

        $table = Table::Create(["Service", "Replicas", "Update", "Cloudfront", "Err"]);

        $cmd = new DockerCmd();
        foreach ($cmd->getServiceList() as $serviceName => $info){
            $inspect = $cmd->getServiceInspect($serviceName);
            $serviceConfig = $cmd->getParsedConfigLabel($serviceName);

            $links = fhtml();
            if (isset ($serviceConfig["cloudfront"]) && isset($serviceConfig["cloudfront"]["hostnames"])) {
                foreach ($serviceConfig["cloudfront"]["hostnames"] as $hostname) {
                    $links->elem("a @href=:url", ["url" => "http://" . $hostname])->content($hostname);
                    $links->content(" ");
                }
            }

            $err = null;
            if (isset ($inspect["UpdateStatus"])) {
                if ($inspect["UpdateStatus"]["State"] == "paused") {
                    $err = $inspect["UpdateStatus"]["Message"];
                }
            }

            $table->row([
                $serviceName,
                $info["Replicas"],
                date("Y-m-d H:i:s", $this->parseTs($inspect["UpdatedAt"])),
                $links,
                $err
            ], $err === null ? "@table-success" : "@table-warning");
        }


        $config->content = [
            "div @container" => [
                "h1" => CONF_CLUSTER_NAME,
                new RawHtmlNode($table->render()),
                "hr" => null,
                "p" => [
                    "viper cloudfont by",
                    "a @href=http://infracamp.org" => "infracamp.org",
                    " :: build " . VERSION_INFO
                ]

            ],

        ];
        $page = new Bootstrap4_Page($config);
        $page->out();
        return true;
    }
}
