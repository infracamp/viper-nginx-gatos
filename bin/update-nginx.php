#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 11:03
 */

namespace NginxUpdater;

use Infracamp\Gatos\DockerCmd;

require __DIR__ . "/../vendor/autoload.php";



$cmd = new DockerCmd();
$services = $cmd->getServiceList();


$sha = "";
if (file_exists(NGINX_CONF)) {
    $sha = sha1_file(NGINX_CONF);
}

$config = "server{listen 80; listen [::]:80; server_name default; location / { root /var/www/html/nginxroot; } }";
$config .= "\nserver{listen 80; listen [::]:80; server_name " . CONF_CLUSTER_HOSTNAME . "; location / { proxy_pass http://localhost:4000/; } }";

foreach ($services as $serviceName => $service) {
    $inspectData = $cmd->getServiceInspect($serviceName);

    if ( ! isset ($inspectData["Spec"]["Labels"][CONFIG_SERVICE_LABEL])) {
        echo "\nInvalid config in label " . CONFIG_SERVICE_LABEL;
        continue;
    }

    $serviceConfig = json_decode($inspectData["Spec"]["Labels"][CONFIG_SERVICE_LABEL], true);
    if ($serviceConfig === null){
        echo "\nInvalid (invalid json data) config in label " . CONFIG_SERVICE_LABEL;
        continue;
    }

    $serverNames = [];
    if (CONF_DEFAULT_HOSTNAME !== "") {
        $serverNames[] = "$serviceName" . CONF_DEFAULT_HOSTNAME;
    }

    if (isset ($serviceConfig["cloudfront"]) && isset($serviceConfig["cloudfront"]["hostnames"])) {
        foreach ($serviceConfig["cloudfront"]["hostnames"] as $curHostname) {
            $serverNames[] = $curHostname;
        }
    }

    if (count($serverNames) == 0) {
        continue;
    }

    if ($serviceName == gethostbyname($serviceName)) {
        // cannot resolve
        continue;
    }
    try {
        phore_http_request("http://$serviceName")->send(true);
    } catch (\Exception $e) {
        continue;
    }

    $config .= "
    server {
        listen 80; listen [::]:80; server_name " . implode (" ", $serverNames) . "; 
        location / {
            proxy_set_header Host \$host;   
            proxy_set_header X-Real-IP \$remote_addr;
            proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; 
            proxy_pass http://{$serviceName}:80/; 
        } 
    }
    ";
}

$localhostdown = false;
try {
    phore_http_request("http://localhost")->send(true);
} catch (\Exception $e) {
    $localhostdown = true;
    echo "Error: Localhost is down!";
}


if ($sha !== sha1($config) || $localhostdown) {
    echo "\nRestart required.";
    file_put_contents(NGINX_CONF, $config);
    phore_exec("sudo service nginx restart");
}




