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

        $table = Table::Create(["Service", "Replicas", "Update", "Cloudfront"]);

        $cmd = new DockerCmd();
        foreach ($cmd->getServiceList() as $serviceName => $info){
            $inspect = $cmd->getServiceInspect($serviceName);

            $link = fhtml("a @href=http://:url", ["url" => $serviceName . CONF_DEFAULT_HOSTNAME])->content($serviceName . CONF_DEFAULT_HOSTNAME);
            if (isset ($inspect["Spec"]["Labels"]["cf_domain"])) {
                $link = fhtml("a @href=http://:url", ["url" =>$inspect["Spec"]["Labels"]["cf_domain"]])->content($inspect["Spec"]["Labels"]["cf_domain"]);
            }
            $table->row([
                $serviceName,
                $info["Replicas"],
                date("Y-m-d H:i:s", $this->parseTs($inspect["UpdatedAt"])),
                $link
            ], $inspect["Spec"]["Mode"]["Replicated"]["Replicas"] > 0 ? "@table-success" : "@table-warning");
        }


        $config->content = [
            "div @container" => [
                "h1" => CONF_CLUSTER_NAME,
                new RawHtmlNode($table->render()),
                "hr" => null,
                "p" => [
                    "viper cloudfont by",
                    "a @href=http://infracamp.org" => "infracamp.org"
                ]

            ],

        ];
        $page = new Bootstrap4_Page($config);
        $page->out();
        return true;
    }
}
